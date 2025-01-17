<?php

namespace Drupal\payment_asp\Plugin\Commerce\CheckoutPane;

use Drupal\commerce\InlineFormManager;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_payment\PaymentOption;
use Drupal\commerce_payment\PaymentOptionsBuilderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\payment_asp\Plugin\Commerce\PaymentGateway\PaymentASPCommerce_link_type;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Provides the payment information pane.
 *
 * Disabling this pane will automatically disable the payment process pane,
 * since they are always used together. Developers subclassing this pane
 * should use hook_commerce_checkout_pane_info_alter(array &$panes) to
 * point $panes['payment_information']['class'] to the new child class.
 *
 * @CommerceCheckoutPane(
 *   id = "payment_asp_payment_information",
 *   label = @Translation("Payment information - Payment ASP"),
 *   wrapper_element = "fieldset",
 * )
 */
class PaymentASPCheckoutPane_payment_information extends CheckoutPaneBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * The payment options builder.
   *
   * @var \Drupal\commerce_payment\PaymentOptionsBuilderInterface
   */
  protected $paymentOptionsBuilder;

  /**
   * Constructs a new PaymentInformation object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface $checkout_flow
   *   The parent checkout flow.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\commerce\InlineFormManager $inline_form_manager
   *   The inline form manager.
   * @param \Drupal\commerce_payment\PaymentOptionsBuilderInterface $payment_options_builder
   *   The payment options builder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, InlineFormManager $inline_form_manager, PaymentOptionsBuilderInterface $payment_options_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);

    $this->currentUser = $current_user;
    $this->inlineFormManager = $inline_form_manager;
    $this->paymentOptionsBuilder = $payment_options_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('plugin.manager.commerce_inline_form'),
      $container->get('commerce_payment.options_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $billing_profile = $this->order->getBillingProfile();
    if ($this->order->isPaid() || $this->order->getTotalPrice()->isZero()) {
      if ($billing_profile) {
        // Only the billing information was collected.
        $view_builder = $this->entityTypeManager->getViewBuilder('profile');
        $summary = [
          '#title' => $this->t('Billing information'),
          'profile' => $view_builder->view($billing_profile, 'default'),
        ];
        return $summary;
      }
    }

    $summary = [];
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $this->order->get('payment_gateway')->entity;
    if (!$payment_gateway) {
      return $summary;
    }
    $payment_method = $this->order->get('payment_method')->entity;
    if ($payment_method) {
      $view_builder = $this->entityTypeManager->getViewBuilder('commerce_payment_method');
      $summary = $view_builder->view($payment_method, 'default');
    }
    else {
      $summary = [
        'payment_gateway' => [
          '#markup' => $payment_gateway->getPlugin()->getDisplayLabel(),
        ],
      ];
      if ($billing_profile) {
        $view_builder = $this->entityTypeManager->getViewBuilder('profile');
        $summary['profile'] = $view_builder->view($billing_profile, 'default');
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {

    if ($this->order->isPaid() || $this->order->getTotalPrice()->isZero()) {
      // No payment is needed if the order is free or has already been paid.
      // In that case, collect just the billing information.
      $pane_form['#title'] = $this->t('Billing information');
      $pane_form = $this->buildBillingProfileForm($pane_form, $form_state);
      return $pane_form;
    }

    /** @var \Drupal\commerce_payment\PaymentGatewayStorageInterface $payment_gateway_storage */
    $payment_gateway_storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');
    // Load the payment gateways. This fires an event for filtering the
    // available gateways, and then evaluates conditions on all remaining ones.
    $payment_gateways = $payment_gateway_storage->loadMultipleForOrder($this->order);
    // Can't proceed without any payment gateways.
    if (empty($payment_gateways)) {
      $this->messenger()->addError($this->noPaymentGatewayErrorMessage());
      return $pane_form;
    }

    // Core bug #1988968 doesn't allow the payment method add form JS to depend
    // on an external library, so the libraries need to be preloaded here.
    foreach ($payment_gateways as $payment_gateway) {
      if ($js_library = $payment_gateway->getPlugin()->getJsLibrary()) {
        $pane_form['#attached']['library'][] = $js_library;
      }
    }

    $options = $this->paymentOptionsBuilder->buildOptions($this->order, $payment_gateways);
    $option_labels = array_map(function (PaymentOption $option) {
      return $option->getLabel();
    }, $options);
    $parents = array_merge($pane_form['#parents'], ['payment_method']);
    $default_option_id = NestedArray::getValue($form_state->getUserInput(), $parents);
    if ($default_option_id && isset($options[$default_option_id])) {
      $default_option = $options[$default_option_id];
    }
    else {
      $default_option = $this->paymentOptionsBuilder->selectDefaultOption($this->order, $options);
    }

    $pane_form['payment_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Payment method'),
      '#options' => $option_labels,
      '#default_value' => $default_option->getId(),
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefresh'],
        'wrapper' => $pane_form['#id'],
      ],
      '#access' => count($options) > 1,
    ];
    // Add a class to each individual radio, to help themers.
    foreach ($options as $option) {
      $class_name = $option->getPaymentMethodId() ? 'stored' : 'new';
      $pane_form['payment_method'][$option->getId()]['#attributes']['class'][] = "payment-method--$class_name";
    }
    // Store the options for submitPaneForm().
    $pane_form['#payment_options'] = $options;

    $default_payment_gateway_id = $default_option->getPaymentGatewayId();
    $payment_gateway = $payment_gateways[$default_payment_gateway_id];

    if ($payment_gateway->getPlugin() instanceof SupportsStoredPaymentMethodsInterface) {
      $pane_form = $this->buildPaymentMethodForm($pane_form, $form_state, $default_option);
    } elseif ($payment_gateway->getPlugin()->collectsBillingInformation()) {
      $pane_form = $this->buildBillingProfileForm($pane_form, $form_state);
      if ($payment_gateway->get('configuration')['method_type'] == 'offsite') {
        $pane_form['fieldset'] = [
          '#title' => t($default_option->getId()),
          '#type' => 'textfield',
          '#default_value' => '',
        ];
      } elseif ($payment_gateway->get('configuration')['method_type'] == 'credit3d') {
        $pane_form['fieldset'] = [
          '#type' => 'markup',
          '#markup' => t('<b>Note:</b> Installment payment is only available for amounts 10,000 above'),
          '#weight' => 0,
        ];
      } elseif ($payment_gateway->get('configuration')['method_type'] == 'webcvs') {
        $pane_form['fieldset'] = [
          '#title' => t('Telephone'),
          '#type' => 'textfield',
          '#weight' => 0,
          '#maxlength' => '11',
          '#size' => '20',
          '#required' => TRUE,
        ];
      } elseif ($payment_gateway->get('configuration')['method_type'] == NULL) {
           $pane_form['fieldset'] = [
          '#type' => 'markup',
          '#markup' => t('<b>Note:</b> Only Available in JAPAN'),
          '#weight' => 0,
        ];
      }
    }
    
    return $pane_form;
  }

  /**
   * Builds the payment method form for the selected payment option.
   *
   * @param array $pane_form
   *   The pane form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   * @param \Drupal\commerce_payment\PaymentOption $payment_option
   *   The payment option.
   *
   * @return array
   *   The modified pane form.
   */
  protected function buildPaymentMethodForm(array $pane_form, FormStateInterface $form_state, PaymentOption $payment_option) {
    if ($payment_option->getPaymentMethodId() && !$payment_option->getPaymentMethodTypeId()) {
      // Editing payment methods at checkout is not supported.
      return $pane_form;
    }

    /** @var \Drupal\commerce_payment\PaymentMethodStorageInterface $payment_method_storage */
    $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
    $payment_method = $payment_method_storage->create([
      'type' => $payment_option->getPaymentMethodTypeId(),
      'payment_gateway' => $payment_option->getPaymentGatewayId(),
      'uid' => $this->order->getCustomerId(),
      'billing_profile' => $this->order->getBillingProfile(),
    ]);
    $inline_form = $this->inlineFormManager->createInstance('payment_gateway_form', [
      'operation' => 'add-payment-method',
    ], $payment_method);

    $pane_form['add_payment_method'] = [
      '#parents' => array_merge($pane_form['#parents'], ['add_payment_method']),
      '#inline_form' => $inline_form,
    ];
    $pane_form['add_payment_method'] = $inline_form->buildInlineForm($pane_form['add_payment_method'], $form_state);

    return $pane_form;
  }

  /**
   * Builds the billing profile form.
   *
   * @param array $pane_form
   *   The pane form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   *
   * @return array
   *   The modified pane form.
   */
  protected function buildBillingProfileForm(array $pane_form, FormStateInterface $form_state) {
    $billing_profile = $this->order->getBillingProfile();
    if (!$billing_profile) {
      $billing_profile = $this->entityTypeManager->getStorage('profile')->create([
        'type' => 'customer',
        'uid' => 0,
      ]);
    }
    $inline_form = $this->inlineFormManager->createInstance('customer_profile', [
      'profile_scope' => 'billing',
      'available_countries' => $this->order->getStore()->getBillingCountries(),
      'address_book_uid' => $this->order->getCustomerId(),
      // Don't copy the profile to address book until the order is placed.
      'copy_on_save' => FALSE,
    ], $billing_profile);

    $pane_form['billing_information'] = [
      '#parents' => array_merge($pane_form['#parents'], ['billing_information']),
      '#inline_form' => $inline_form,
    ];
    $pane_form['billing_information'] = $inline_form->buildInlineForm($pane_form['billing_information'], $form_state);
    return $pane_form;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#parents'];
    array_pop($parents);
    return NestedArray::getValue($form, $parents);
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    if ($this->order->isPaid() || $this->order->getTotalPrice()->isZero()) {
      return;
    }

    $values = $form_state->getValue($pane_form['#parents']);
    if (!isset($values['payment_method'])) {
      $form_state->setError($complete_form, $this->noPaymentGatewayErrorMessage());
    }
    
    $billing_profile = $this->order->getBillingProfile();
    $current_country = $pane_form['billing_information']['#inline_form'];
    $country_code = $current_country->getEntity()->get('address')->getValue();

    $payment_gateway_storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');
    $payment_gateways = $payment_gateway_storage->loadMultipleForOrder($this->order);

    $default_payment_gateway_id = $pane_form['payment_method']['#default_value'];
    $payment_gateway = $payment_gateways[$default_payment_gateway_id];
      
    if ($country_code[0]['country_code'] != 'JP' && ($payment_gateway->get('plugin') == 'manual' || $payment_gateway->get('configuration')['method_type'] == 'webcvs')) {
        $form_state->setError($complete_form, 'THIS BILLING ADDRESS only AVAILABLE IN JAPAN');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {

    
    if (isset($pane_form['billing_information'])) {
      /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
      $inline_form = $pane_form['billing_information']['#inline_form'];
      /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
      $billing_profile = $inline_form->getEntity();
      $this->order->setBillingProfile($billing_profile);
      // The billing profile is provided either because the order is free,
      // or the selected gateway is off-site. If it's the former, stop here.
      if ($this->order->isPaid() || $this->order->getTotalPrice()->isZero()) {
        return;
      }
    }
//------------------------------- ADDED BY RENIER -------------------------------
    $value_address  =  $this->order->getBillingProfile()->get('address');
    $givenName   =  $value_address->first()->getGivenName();
    $familyName  =  $value_address->first()->getFamilyName();

    $values = $form_state->getValue($pane_form['#parents']);
    /** @var \Drupal\commerce_payment\PaymentOption $selected_option */
    $selected_option = $pane_form['#payment_options'][$values['payment_method']];
    /** @var \Drupal\commerce_payment\PaymentGatewayStorageInterface $payment_gateway_storage */
    $payment_gateway_storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $payment_gateway_storage->load($selected_option->getPaymentGatewayId());
 

    if (!$payment_gateway) {
      return;
    }

    if ($payment_gateway->getPlugin() instanceof SupportsStoredPaymentMethodsInterface) {
      if (!empty($selected_option->getPaymentMethodTypeId())) {
        /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
        $inline_form = $pane_form['add_payment_method']['#inline_form'];
        // The payment method was just created.
        $payment_method = $inline_form->getEntity();
      }
      else {
        /** @var \Drupal\commerce_payment\PaymentMethodStorageInterface $payment_method_storage */
        $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
        $payment_method = $payment_method_storage->load($selected_option->getPaymentMethodId());
      }

      /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
      $this->order->set('payment_gateway', $payment_method->getPaymentGateway());
      $this->order->set('payment_method', $payment_method);
      /** ADDED BY RENDROID  */
      $this->order->setData('payment_gateway_parameter', $pane_form['fieldset']['#value']); 
      $this->order->setData('billing_profile_givenName',$givenName);
      $this->order->setData('billing_profile_familyName',$familyName);
      // Copy the billing information to the order.
      $payment_method_profile = $payment_method->getBillingProfile();

      if ($payment_method_profile) {
        $billing_profile = $this->order->getBillingProfile();
        if (!$billing_profile) {
          $billing_profile = $this->entityTypeManager->getStorage('profile')->create([
            'type' => 'customer',
            'uid' => 0,
          ]);
        }
        $billing_profile->populateFromProfile($payment_method_profile);
        // The address_book_profile_id flag need to be transferred as well.
        $address_book_profile_id = $payment_method_profile->getData('address_book_profile_id');
        if ($address_book_profile_id) {
          $billing_profile->setData('address_book_profile_id', $address_book_profile_id);
        }
        $billing_profile->save();
        $this->order->setBillingProfile($billing_profile);
      }
    }
    else {


      $this->order->set('payment_gateway', $payment_gateway);
      //$this->order->set('payment_method', NULL);
      /** ADDED BY RENDROID  */
      $this->order->setData('payment_gateway_parameter', $pane_form['fieldset']['#value']); 
      $this->order->setData('billing_profile_givenName',$givenName);
      $this->order->setData('billing_profile_familyName',$familyName);
       
    }
  }

  /**
   * Returns an error message in case there are no available payment gateways.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The error message.
   */
  protected function noPaymentGatewayErrorMessage() {
    if ($this->currentUser->hasPermission('administer commerce_payment_gateway')) {
      $message = $this->t('There are no <a href=":url"">payment gateways</a> available for this order.', [
        ':url' => Url::fromRoute('entity.commerce_payment_gateway.collection')->toString(),
      ]);
    }
    else {
      $message = $this->t('There are no payment gateways available for this order. Please try again later.');
    }
    return $message;
  }

}
