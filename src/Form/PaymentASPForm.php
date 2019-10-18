<?php
/**
 * @file
 * Contains \Drupal\payment_asp\Form\WorkForm.
 */
namespace Drupal\payment_asp\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\payment_asp\Controller\PaymentASPController;

class PaymentASPForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'payment_method_selection';
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // kint($order['order_id']);

    $payment_methods = array(
      '1' => 'Credit Card',
      '2' => 'Alipay Cross-border Payment',
      '3' => 'Convenience Store',
      '4' => 'Paypal',
      '5' => 'Bank Transfer / General Wire Transfer',
      '6' => 'Union Pay / GINREN Net Payment'
    );

    $form['payment_method'] = array(
      '#title' => t('Payment Methods:'),
      '#type' => 'radios',
      '#required' => TRUE,
      '#options' => $payment_methods,
      '#ajax' => array(
        'callback' => '::setPaymentForm',
        'event' => 'change',
        'progress' => array(
          'type' => 'throbber',
          'message' => 'Please wait',
        ),
      ),
    );

    // $form['actions'] = [
    //   '#type' => 'button',
    //   '#value' => $this->t('Submit'),
    //   '#ajax' => [
    //     'callback' => '::setPaymentForm',
    //   ]
    // ];

    // $form['payment_method_choosen'] = array(
    //   '#type' => 'markup',
    //   '#markup' => '<div class="payment_method_choosen"></div>',
    // );

    // $form['submit'] = array(
    //   '#type' => 'submit',
    //   '#value' => $this->t('REGULARSubmit'),
    //   '#button_type' => 'primary',
    // );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function setPaymentForm(array &$form, FormStateInterface $form_state) {

    $payment_selected = $form_state->getValue('payment_method');
    switch ($payment_selected) {
      case 1:
        $payment_selected = \Drupal::formBuilder()->getForm('Drupal\payment_asp\Form\payment_creditcard');
        break;
      case 2:
        $payment_selected = \Drupal::formBuilder()->getForm('Drupal\payment_asp\Form\payment_paypal');
        break;
      case 6:
        $payment_selected = \Drupal::formBuilder()->getForm('Drupal\payment_asp\Form\payment_unionpay');
        break;
    }


    $response = new AjaxResponse();
    $response->addCommand(
      new HtmlCommand(
        '.payment_method_choosen',
        $payment_selected,
      )
    );

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // die('asdasd');
    // drupal_set_message($form_state->getValue('pay_method'));
  }
}