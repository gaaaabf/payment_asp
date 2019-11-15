<?php

namespace Drupal\payment_asp\Plugin\Commerce\CheckoutPane;

use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce\InlineFormManager;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\ManualPaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the payment process pane for Payment ASP.
 *
 * @CommerceCheckoutPane(
 *   id = "payment_asp_payment_process",
 *   label = @Translation("Payment Process - Payment ASP"),
 *   default_step = "payment",
 * )
 */
class PaymentASPCheckoutPane_payment_process extends CheckoutPaneBase {

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new PaymentProcess object.
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
   * @param \Drupal\commerce\InlineFormManager $inline_form_manager
   *   The inline form manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow, EntityTypeManagerInterface $entity_type_manager, InlineFormManager $inline_form_manager, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);

    $this->inlineFormManager = $inline_form_manager;
    $this->logger = $logger;
   
  }

  /**
   * {@inheritdoc}
   */                    // ----------------------------TRIGGER DURING INIT
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_inline_form'),
      $container->get('logger.channel.commerce_payment')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'capture' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
ksm('buildConfigurationSummary');    
    if (!empty($this->configuration['capture'])) {
      $summary = $this->t('Transaction mode: Authorize and capture');
    }
    else {
      $summary = $this->t('Transaction mode: Authorize only');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['capture'] = [
      '#type' => 'radios',
      '#title' => $this->t('Transaction mode'),
      '#description' => $this->t('This setting is only respected if the chosen payment gateway supports authorizations.'),
      '#options' => [
        TRUE => $this->t('Authorize and capture'),
        FALSE => $this->t('Authorize only (requires manual capture after checkout)'),
      ],
      '#default_value' => (int) $this->configuration['capture'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    //ADDED BY RENDROID
    $this->order->unlock();

    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['capture'] = !empty($values['capture']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {         // RETURN TRUE DURING INIT
    
    if ($this->order->isPaid() || $this->order->getTotalPrice()->isZero()) {
      // No payment is needed if the order is free or has already been paid.
  return FALSE;
    }
    $payment_info_pane = $this->checkoutFlow->getPane('payment_information');
    if (!$payment_info_pane->isVisible() || $payment_info_pane->getStepId() == '_disabled') {
      // Hide the pane if the PaymentInformation pane has been disabled.     
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $error_step_id = $this->getErrorStepId();
    // The payment gateway is currently always required to be set.
    if ($this->order->get('payment_gateway')->isEmpty()) {
      $this->messenger()->addError($this->t('No payment gateway selected.'));
      $this->checkoutFlow->redirectToStep($error_step_id);
    }

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $this->order->payment_gateway->entity;
    $payment_gateway_plugin = $payment_gateway->getPlugin();

    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $payment_storage->create([
      'state' => 'new',
      'amount' => $this->order->getBalance(),
      'payment_gateway' => $payment_gateway->id(),
      'order_id' => $this->order->id(),
    ]);   
    $next_step_id = $this->checkoutFlow->getNextStepId($this->getStepId());

    if ($payment_gateway_plugin instanceof OnsitePaymentGatewayInterface) {
    // Custom Response handling for API/Onsite payment gateways
    //  $next_step_id = $this->checkoutFlow->getNextStepId($this->getStepId());
    //  $result = $payment_gateway_plugin->createPayment($payment);
   
    //  if($result == 'NG') {
    //    $this->redirectToCart();
    //  } elseif($result == 'OK') {
    //    // Create payment to save to database
    //    $payment->setState('Completed');
    //    // $payment->setRemoteId($response->transaction->id);
    //    $payment->save();
 
    //    $field_arr = [
    //      'p_fk_id' => $payment->id(),
    //      'tracking_id' => (string) $xml->res_tracking_id,
    //      'sps_transaction_id' => (string) $xml->res_sps_transaction_id,
    //      'processing_datetime' => (string) $xml->res_process_date,
    //    ];
    //    // Save to database
    //    $query = \Drupal::database();
    //    $query->insert('payment_asp_pd')
    //          ->fields($field_arr)
    //          ->execute();
    //    $order_id = $this->order->id();
    //    unset($_SESSION["cc_data_".$order_id]);
      try {

        $payment->payment_method = $this->order->payment_method->entity;
        $payment_gateway_plugin->createPayment($payment, $this->configuration['capture']);
        $this->checkoutFlow->redirectToStep($next_step_id);
      }
      catch (DeclineException $e) {
        $message = $this->t('We encountered an error processing your payment method. Please verify your details and try again.');
        $this->messenger()->addError($message);
        $this->checkoutFlow->redirectToStep($error_step_id);
      }
      catch (PaymentGatewayException $e) {
        $this->logger->error($e->getMessage());
        $message = $this->t('We encountered an unexpected error processing your payment method. Please try again later.');
        $this->messenger()->addError($message);
        $this->checkoutFlow->redirectToStep($error_step_id);
      }
    }
    
    //------------------------------------------------- For offsite payment method ----------------------------------

    elseif ($payment_gateway_plugin instanceof OffsitePaymentGatewayInterface) {
 
      $complete_form['actions']['next']['#value'] = $this->t('Proceed to @gateway', [
        '@gateway' => $payment_gateway_plugin->getDisplayLabel(),
      ]);
      // Make sure that the payment gateway's onCancel() method is invoked,
      // by pointing the "Go back" link to the cancel URL.
      $complete_form['actions']['next']['#suffix'] = Link::fromTextAndUrl($this->t('Go back'), $this->buildCancelUrl())->toString();
      // Actions are not needed by gateways that embed iframes or redirect
      // via GET. The inline form can show them when needed (redirect via POST).
      $complete_form['actions']['#access'] = TRUE;

      $inline_form = $this->inlineFormManager->createInstance('payment_gateway_form', [
        'operation' => 'offsite-payment',
        'catch_build_exceptions' => FALSE,
      ], $payment);
      $pane_form['offsite_payment'] = [
        '#parents' => array_merge($pane_form['#parents'], ['offsite_payment']),
        '#inline_form' => $inline_form,
        '#return_url' => $this->buildReturnUrl()->toString(),
        '#cancel_url' => $this->buildCancelUrl()->toString(),
        '#capture' => $this->configuration['capture'],
      ];
      try {      
        $pane_form['offsite_payment'] = $inline_form->buildInlineForm($pane_form['offsite_payment'], $form_state);
      }
      catch (PaymentGatewayException $e) {
        $this->logger->error($e->getMessage());
        $message = $this->t('We encountered an unexpected error processing your payment. Please try again later.');
        $this->messenger()->addError($message);
        $this->checkoutFlow->redirectToStep($error_step_id);
      }

      // To avoid locking order upon payment
      $order = \Drupal\commerce_order\Entity\Order::load($this->order->id());
      $order->unlock();
      $order->save();
      
      return $pane_form;
    }

  //------------------------------------------------- For offsite payment method ----------------------------------  
  

    elseif ($payment_gateway_plugin instanceof ManualPaymentGatewayInterface) {
      try {
        $payment_gateway_plugin->createPayment($payment);
        $this->checkoutFlow->redirectToStep($next_step_id);
      }
      catch (PaymentGatewayException $e) {
        $this->logger->error($e->getMessage());
        $message = $this->t('We encountered an unexpected error processing your payment. Please try again later.');
        $this->messenger()->addError($message);
        $this->checkoutFlow->redirectToStep($error_step_id);
      }
    }
    else {
      $this->checkoutFlow->redirectToStep($next_step_id);
    }
  }

  /**
   * Builds the URL to the "return" page.
   *
   * @return \Drupal\Core\Url
   *   The "return" page URL.
   */
  protected function buildReturnUrl() {
    return Url::fromRoute('commerce_payment.checkout.return', [
      'commerce_order' => $this->order->id(),
      'step' => 'payment',
    ], ['absolute' => TRUE]);
  }

  /**
   * Builds the URL to the "cancel" page.
   *
   * @return \Drupal\Core\Url
   *   The "cancel" page URL.
   */
  protected function buildCancelUrl() {
    return Url::fromRoute('commerce_payment.checkout.cancel', [
      'commerce_order' => $this->order->id(),
      'step' => 'payment',
    ], ['absolute' => TRUE]);
  }

  /**
   * Gets the step ID that the customer should be sent to on error.
   *
   * @return string
   *   The error step ID.
   */
  protected function getErrorStepId() {
    // Default to the step that contains the PaymentInformation pane.
    $step_id = $this->checkoutFlow->getPane('payment_information')->getStepId();
    if ($step_id == '_disabled') {
      // Can't redirect to the _disabled step. This could mean that isVisible()
      // was overridden to allow PaymentProcess to be used without a
      // payment_information pane, but this method was not modified.
      throw new \RuntimeException('Cannot get the step ID for the payment_information pane. The pane is disabled.');
    }

    return $step_id;
  }

  /**
   * Redirect to cart in case of a PaymentGatewayException exception.
   * UNUSED FOR NOW
   */
  protected function redirectToCart() {
 ksm('redirectToCart'); 
    drupal_set_message('Payment has not gone through. Please check you credit card detials', 'error');
    $this->order->get('checkout_flow')->setValue(NULL);
    $this->order->get('checkout_step')->setValue(NULL);
    $this->order->unlock();
    $this->order->save();
    throw new NeedsRedirectException(Url::fromRoute('commerce_cart.page')->toString());
  }


}