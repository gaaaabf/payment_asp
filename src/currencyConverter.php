<?php

namespace Drupal\commerce_asp;

use Drupal\commerce_currency_resolver\CurrencyHelper;
use Drupal\commerce_price\Price;

class currencyConverter {

  /**
  * Gets current currency
  */
  public function getCurrentCurrency() {
    // Get all active currencies.
    $active_currencies = CurrencyHelper::getEnabledCurrency();

    // Get cookies.
    $cookies = \Drupal::request()->cookies;

    // Get values from cookie.
    if ($cookies->has('commerce_currency') && isset($active_currencies[$cookies->get('commerce_currency')])) {
      $current_currency = $cookies->get('commerce_currency');
    } else {
      $current_currency = \Drupal::config('commerce_currency_resolver.settings')->get('currency_default');
    }

    return $current_currency;
  }

  /**
   * Converts the current price to the given currency.
   *
   * @param \Drupal\commerce_price\Price $price
   *   The price.
   * @param string $currency_code
   *   The currency code.
   *
   * @return static
   *   The resulting price.
   */
  public function currencyConverter(Price $price, $currency_code = 'JPY') {
    // Get all active currencies.
    $active_currencies = CurrencyHelper::getEnabledCurrency();
    // Get currency configurations
    $config = $this->config('commerce_currency_resolver.currency_conversion');
    // Get current currency used
    $order_currency_orig = $price->getCurrencyCode();
    if ($order_currency_orig == $currency_code) {
      $conversion_rate = 1;
    } else {
      $conversion_rate = $config->get('exchange')[$order_currency_orig][$currency_code]['value'];
    }
    $totalPrice = $price->convert($currency_code, $conversion_rate);
    $totalPrice = $totalPrice->getNumber();
    $totalPrice = number_format((float)$totalPrice, 0, '.', '');

    // SBPS only accepts JPY Currency and whole number
    // if ($order_currency_orig != 'JPY') {
    //   foreach ($active_currencies as $key => $value) {
    //     if ($key == $order_currency_orig) {
    //       $conversion_rate = $config->get('exchange')[$key]['JPY']['value'];   
    //     }
    //   }
    //   $totalPrice = $amount->convert('JPY', $conversion_rate);
    //   $totalPrice = $totalPrice->getNumber();
    //   $totalPrice = number_format((float)$totalPrice, 0, '.', '');
    // } else {
    //   $totalPrice = $amount->getNumber();
    //   $totalPrice = number_format((float)$totalPrice, 0, '.', '');
    // }

    return $totalPrice;
  }


}