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

class payment_paypal extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'payment_paypal';
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $pc = new PaymentASPController();
    // $order = $pc->getOrderDetails();
    $order['pay_method'] = 'paypal';

    $form['payment_method_choosen'] = array(
      '#type' => 'markup',
      '#markup' => '<div>Proceed PAYPAL!!</div>',
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Go!'),
      '#button_type' => 'warning',
    );

    $form['#attached']['library'][] = 'payment_asp/payment_aspjs';
    $form['#attached']['drupalSettings']['payment_asp']['orderData'] = $order;
    $form['#action'] = 'https://stbfep.sps-system.com/Extra/PayRequestAction.do';

      return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }
}