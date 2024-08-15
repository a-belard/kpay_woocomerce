<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Kpay_Blocks extends AbstractPaymentMethodType
{

  private $gateway;
  protected $name = 'kpay'; // your payment gateway name

  public function initialize()
  {
    $this->settings = get_option('woocommerce_kpay_settings', []);
    $this->gateway = new WC_Gateway_Kpay();
  }

  public function is_active()
  {
    return $this->gateway->is_available();
  }

  public function get_payment_method_script_handles()
  {

    wp_register_script(
      'wc-kpay-blocks-integration',
      plugin_dir_url(__FILE__) . 'block/checkout.js',
      [
        'wc-blocks-registry',
        'wc-settings',
        'wp-element',
        'wp-html-entities',
        'wp-i18n',
      ],
      null,
      true
    );
    // if (function_exists('wp_set_script_translations')) {
    //   wp_set_script_translations('wc-kpay-blocks-integration', 'wc-phonepe', SGPPY_PLUGIN_PATH . 'languages/');
    // }
    return ['wc-kpay-blocks-integration'];
  }

  public function get_payment_method_data()
  {
    $description = esicia_kpay_description_fields("", "kpay");
    return [
      'title'       => $this->get_setting('title'),
      'description' => $description,
      'supports'    => $this->get_supported_features(),
    ];
  }
}
