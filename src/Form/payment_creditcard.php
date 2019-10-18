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


class payment_creditcard extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'payment_creditcard';
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Merge default values into the default array.
    $months = array(
      '01' => '01',
      '02' => '02', 
      '03' => '03',
      '04' => '04',
      '05' => '05',
      '06' => '06',
      '07' => '07',
      '08' => '08',
      '09' => '09',
      '10' => '10',
      '11' => '11',
      '12' => '12',
    );

    $default = array(
      'type' => '',
      'owner' => '',
      'number' => '',
      'start_month' => '',
      'start_year' => date('Y') - 5,
      'exp_month' => date('m'),
      'exp_year' => date('Y'),
      'issue' => '',
      'code' => '',
      'bank' => '',
    );

    $current_year_2 = date('y');
    $current_year_4 = date('Y');

    // Always add a field for the credit card number.
    $form['credit_card']['number'] = array(
      '#type' => 'textfield',
      '#title' => t('Card number'),
      '#default_value' => $default['number'],
      '#attributes' => array('autocomplete' => 'off'),
      '#required' => TRUE,
      '#maxlength' => 19,
      '#size' => 20,
    );

    // Always add fields for the credit card expiration date.
    $form['credit_card']['exp_month'] = array(
      '#type' => 'select',
      '#title' => t('Expiration'),
      '#options' => $months,
      '#default_value' => strlen($default['exp_month']) == 1 ? '0' . $default['exp_month'] : $default['exp_month'],
      '#required' => TRUE,
      '#prefix' => '<div class="commerce-credit-card-expiration">',
      '#suffix' => '<span class="commerce-month-year-divider">/</span>',
    );

    // Build a year select list that uses a 4 digit key with a 2 digit value.
    $options = array();

    for ($i = 0; $i < 20; $i++) {
      $options[$current_year_4 + $i] = str_pad($current_year_2 + $i, 2, '0', STR_PAD_LEFT);
    }

    $form['credit_card']['exp_year'] = array(
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $default['exp_year'],
      '#suffix' => '</div>',
    );

    // Add a field for the security code if specified.
    if (isset($fields['code'])) {
      $form['credit_card']['code'] = array(
        '#type' => 'textfield',
        '#title' => !empty($fields['code']) ? $fields['code'] : t('Security code'),
        '#default_value' => $default['code'],
        '#attributes' => array('autocomplete' => 'off'),
        '#required' => TRUE,
        '#maxlength' => 4,
        '#size' => 4,
      );
    }

    // $form['submit'] = array(
    //   '#type' => 'submit',
    //   '#value' => $this->t('Submita'),
    //   '#button_type' => 'primary',
    // );
    
    // $form['#action'] = 'https://stbfep.sps-system.com/api/xmlapi.do';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    drupal_set_message("Why won't this message show?   ");
  }
}