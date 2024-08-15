<?php

/**
 * Kpay Mobile Payments Gateway.
 *
 * Provides Kpay Payment Gateway.
 *
 * @class       WC_Gateway_Kpay
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @package     WooCommerce\Classes\Payment
 */

use Automattic\WooCommerce\StoreApi\Utilities\NoticeHandler;

class WC_Gateway_Kpay extends WC_Payment_Gateway
{

	public $mode, $enable_for_methods, $enable_for_virtual, $instructions, $username, $password, $details, $returl, $redirecturl, $retailerid, $api_url, $errorCodes, $check_count;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct()
	{
		// Setup general properties.
		$this->setup_properties();

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Get settings.
		$this->title              = $this->get_option('title');
		$this->description        = $this->get_option('description');
		$this->instructions       = $this->get_option('instructions');
		$this->mode        				= 	$this->get_option('mode');
		$this->username           = $this->get_option('username');
		$this->password           = $this->get_option('password');
		$this->details            = $this->get_option('details');
		$this->returl             = $this->get_option('returl');
		$this->redirecturl        = $this->get_option('redirecturl');
		$this->retailerid         = $this->get_option('retailerid');
		$this->check_count        = 0;

		$this->enable_for_methods = $this->get_option('enable_for_methods', array());
		$this->enable_for_virtual = $this->get_option('enable_for_virtual', 'yes') === 'yes';

		$this->api_url = $this->mode == 'test' ? 'pay.esicia.com' : 'pay.esicia.rw';

		$this->errorCodes = array(
			'02' => 'Payment failed',
			'03' => 'Pending transaction',
			'401' => 'Missing authentication header',
			'500' => 'Non HTTPS request',
			'600' => 'Invalid username / password combination',
			'601' => 'Invalid remote user',
			'602' => 'Location / IP not whitelisted',
			'603' => 'Empty parameter. - missing required parameters',
			'604' => 'Unknown retailer',
			'605' => 'Retailer not enabled',
			'606' => 'Error processing',
			'607' => 'Failed mobile money transaction',
			'608' => 'Used ref id – error uniqueness',
			'609' => 'Unknown Payment method',
			'610' => 'Unknown or not enabled Financial institution',
			'611' => 'Transaction not found',
		);

		// Actions.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
		add_action('woocommerce_api_wc_gateway_kpay', array($this, 'kpay_handle_webhook'));
		add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
		// action for validation
	}


	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties()
	{
		$this->id                 = 'kpay';
		$this->icon               = apply_filters('woocommerce_kpay_icon', '');
		$this->method_title       = __('Payment Online - Kpay', 'woocommerce');
		$this->method_description = __('Simple and straightforward.', 'woocommerce');
		$this->has_fields         = false;
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __('Enable/Disable', 'woocommerce'),
				'label'       => __('Enable Kpay', 'woocommerce'),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			// test or live
			'mode'        => array(
				'title'       => __('Mode', 'woocommerce'),
				'type'        => 'select',
				'description' => __('Select the environment to use for processing kpay payments.', 'woocommerce'),
				'default'     => 'test',
				'options'     => array(
					'test' => __('Test', 'woocommerce'),
					'live' => __('Live', 'woocommerce'),
				),
			),
			'title'              => array(
				'title'       => __('Title', 'woocommerce'),
				'type'        => 'text',
				'description' => __('Kpay description that the customer will see on your checkout.', 'woocommerce'),
				'default'     => __('Kpay', 'woocommerce'),
				'desc_tip'    => true,
			),

			'instructions'       => array(
				'title'       => __('Instructions', 'woocommerce'),
				'type'        => 'textarea',
				'description' => __('Instructions that will be added to the thank you page and emails.', 'woocommerce'),
				'default'     => __('Pay with Kpay.', 'woocommerce'),
				'desc_tip'    => true,
			),
			'username'           => array(
				'title'       => __('Username', 'woocommerce'),
				'type'        => 'text',
				'description' => __('This is the username for the Kpay account.', 'woocommerce'),
				'default'     => '',
				'desc_tip'    => true,
			),
			'password'           => array(
				'title'       => __('Password', 'woocommerce'),
				'type'        => 'password',
				'description' => __('This is the password for the Kpay account.', 'woocommerce'),
				'default'     => '',
				'desc_tip'    => true,
			),
			'retailerid'         => array(
				'title'       => __('Retailer ID', 'woocommerce'),
				'type'        => 'text',
				'description' => __('Retailer ID provided for live api.', 'woocommerce'),
				'default'     => 'woocommerce',
				'desc_tip'    => true,
			),
			'details'            => array(
				'title'       => __('Details', 'woocommerce'),
				'type'        => 'textarea',
				'description' => __('Transaction details.', 'woocommerce'),
				'default'     => __('Shopping.', 'woocommerce'),
				'desc_tip'    => true,
			),
			'enable_for_methods' => array(
				'title'             => __('Enable for shipping methods', 'woocommerce'),
				'type'              => 'multiselect',
				'class'             => 'wc-enhanced-select',
				'css'               => 'width: 400px;',
				'default'           => '',
				'description'       => __('If COD is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'woocommerce'),
				'options'           => $this->load_shipping_method_options(),
				'desc_tip'          => true,
				'custom_attributes' => array(
					'data-placeholder' => __('Select shipping methods', 'woocommerce'),
				),
			),
			'enable_for_virtual' => array(
				'title'   => __('Accept for virtual orders', 'woocommerce'),
				'label'   => __('Accept COD if the order is virtual', 'woocommerce'),
				'type'    => 'checkbox',
				'default' => 'yes',
			),
		);
	}

	/**
	 * Check If The Gateway Is Available For Use.
	 *
	 * @return bool
	 */
	public function is_available()
	{
		$order          = null;
		$needs_shipping = false;

		// Test if shipping is needed first.
		if (WC()->cart && WC()->cart->needs_shipping()) {
			$needs_shipping = true;
		} elseif (is_page(wc_get_page_id('checkout')) && 0 < get_query_var('order-pay')) {
			$order_id = absint(get_query_var('order-pay'));
			$order    = wc_get_order($order_id);

			// Test if order needs shipping.
			if ($order && 0 < count($order->get_items())) {
				foreach ($order->get_items() as $item) {
					$_product = $item->get_product();
					if ($_product && $_product->needs_shipping()) {
						$needs_shipping = true;
						break;
					}
				}
			}
		}

		$needs_shipping = apply_filters('woocommerce_cart_needs_shipping', $needs_shipping);

		// Virtual order, with virtual disabled.
		if (!$this->enable_for_virtual && !$needs_shipping) {
			return false;
		}

		// Only apply if all packages are being shipped via chosen method, or order is virtual.
		if (!empty($this->enable_for_methods) && $needs_shipping) {
			$order_shipping_items            = is_object($order) ? $order->get_shipping_methods() : false;
			$chosen_shipping_methods_session = WC()->session->get('chosen_shipping_methods');

			if ($order_shipping_items) {
				$canonical_rate_ids = $this->get_canonical_order_shipping_item_rate_ids($order_shipping_items);
			} else {
				$canonical_rate_ids = $this->get_canonical_package_rate_ids($chosen_shipping_methods_session);
			}

			if (!count($this->get_matching_rates($canonical_rate_ids))) {
				return false;
			}
		}

		return parent::is_available();
	}

	/**
	 * Checks to see whether or not the admin settings are being accessed by the current request.
	 *
	 * @return bool
	 */
	private function is_accessing_settings()
	{
		if (is_admin()) {
			// phpcs:disable WordPress.Security.NonceVerification
			if (!isset($_REQUEST['page']) || 'wc-settings' !== $_REQUEST['page']) {
				return false;
			}
			if (!isset($_REQUEST['tab']) || 'checkout' !== $_REQUEST['tab']) {
				return false;
			}
			if (!isset($_REQUEST['section']) || 'kpay' !== $_REQUEST['section']) {
				return false;
			}
			// phpcs:enable WordPress.Security.NonceVerification

			return true;
		}

		return false;
	}

	/**
	 * Loads all of the shipping method options for the enable_for_methods field.
	 *
	 * @return array
	 */
	private function load_shipping_method_options()
	{
		// Since this is expensive, we only want to do it if we're actually on the settings page.
		if (!$this->is_accessing_settings()) {
			return array();
		}

		$data_store = WC_Data_Store::load('shipping-zone');
		$raw_zones  = $data_store->get_zones();
		$zones      = array();

		foreach ($raw_zones as $raw_zone) {
			$zones[] = new WC_Shipping_Zone($raw_zone);
		}

		$zones[] = new WC_Shipping_Zone(0);

		$options = array();
		foreach (WC()->shipping()->load_shipping_methods() as $method) {

			$options[$method->get_method_title()] = array();

			// Translators: %1$s shipping method name.
			$options[$method->get_method_title()][$method->id] = sprintf(__('Any &quot;%1$s&quot; method', 'woocommerce'), $method->get_method_title());

			foreach ($zones as $zone) {

				$shipping_method_instances = $zone->get_shipping_methods();

				foreach ($shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance) {

					if ($shipping_method_instance->id !== $method->id) {
						continue;
					}

					$option_id = $shipping_method_instance->get_rate_id();

					// Translators: %1$s shipping method title, %2$s shipping method id.
					$option_instance_title = sprintf(__('%1$s (#%2$s)', 'woocommerce'), $shipping_method_instance->get_title(), $shipping_method_instance_id);

					// Translators: %1$s zone name, %2$s shipping method instance name.
					$option_title = sprintf(__('%1$s &ndash; %2$s', 'woocommerce'), $zone->get_id() ? $zone->get_zone_name() : __('Other locations', 'woocommerce'), $option_instance_title);

					$options[$method->get_method_title()][$option_id] = $option_title;
				}
			}
		}

		return $options;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
	 * @return array $canonical_rate_ids    Rate IDs in a canonical format.
	 */
	private function get_canonical_order_shipping_item_rate_ids($order_shipping_items)
	{

		$canonical_rate_ids = array();

		foreach ($order_shipping_items as $order_shipping_item) {
			$canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
		}

		return $canonical_rate_ids;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
	 * @return array $canonical_rate_ids  Rate IDs in a canonical format.
	 */
	private function get_canonical_package_rate_ids($chosen_package_rate_ids)
	{

		$shipping_packages  = WC()->shipping()->get_packages();
		$canonical_rate_ids = array();

		if (!empty($chosen_package_rate_ids) && is_array($chosen_package_rate_ids)) {
			foreach ($chosen_package_rate_ids as $package_key => $chosen_package_rate_id) {
				if (!empty($shipping_packages[$package_key]['rates'][$chosen_package_rate_id])) {
					$chosen_rate          = $shipping_packages[$package_key]['rates'][$chosen_package_rate_id];
					$canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
				}
			}
		}

		return $canonical_rate_ids;
	}

	/**
	 * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
	 *
	 * @since  3.4.0
	 *
	 * @param array $rate_ids Rate ids to check.
	 * @return array
	 */
	private function get_matching_rates($rate_ids)
	{
		// First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
		return array_unique(array_merge(array_intersect($this->enable_for_methods, $rate_ids), array_intersect($this->enable_for_methods, array_unique(array_map('wc_get_string_before_colon', $rate_ids)))));
	}


	public function exchange($amount)
	{
		// call this for exchange in rwf https://esicia.rw/fx/?curr=USD
		/**
		 * {"lastmodified":"2024-06-25 10:15:04","fx":{"date":"2024-06-25","name":"USD","buy":"1,296.420742","sell":"1,322.346538","middle":"1,309.383640"}}
		 */
		$currency = get_woocommerce_currency();
		if ($currency === "RWF" || $currency === "Fr" || $currency === "Frw" || $currency === "RF") {
			return $amount;
		}
		if ($currency === "Rs") {
			$currency = "INR";
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://esicia.rw/fx/?curr=$currency");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$response = curl_exec($ch);
		curl_close($ch);
		$data = json_decode($response);
		$rate = $data->fx->sell;
		// parse the exchange rate
		$rate = str_replace(',', '', $rate);
		$rate = str_replace($currency, '', $rate);
		$rate = str_replace(' ', '', $rate);
		$rate = str_replace(' ', '', $rate);
		$rate = str_replace('-', '', $rate);
		$rate = str_replace('(', '', $rate);
		return $amount * $rate;
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment($order_id)
	{

		$order = wc_get_order($order_id);
		//exchange
		$amount = $this->exchange($order->get_total());

		if ($order->get_total() > 0) {
			$req = array(
				"msisdn" => $order->get_billing_phone(),
				"details" => $this->details,
				"refid" => $order_id . '-' . rand(100000000, 9999999999),
				"retailerid" => $this->retailerid,
				"returl" => esc_url(home_url('/')) . '?wc-api=wc_gateway_kpay',
				"redirecturl" => $this->get_return_url($order),
				"amount" => (int) $amount,
				"currency" => "RWF",
				"pmethod" => get_post_meta($order_id, 'kpay_payment_method', true),
				"cnumber" => $order->get_billing_phone(),
				"email" => $order->get_billing_email(),
				"cname" => $order->get_billing_first_name() . " " . $order->get_billing_last_name(),
				"cemail" => $order->get_billing_email(),
			);
			// make order_id at least 5 characters
			$bankid = "";
			if ($req["pmethod"] === "momo") {
				if ($req["msisdn"][2] == "8" || $req["msisdn"][2] == "9") {
					$bankid = '63510';
				} else {
					$bankid = '63514';
				}
			} else if ($req["pmethod"] === "spenn") {
				$bankid = '63502';
			} else if ($req["pmethod"] === "bank") {
				$bankid = '192';
			} else if ($req["pmethod"] === "cc" || $req["pmethod"] === "smartcash") {
				$bankid = '000';
			}
			$req["bankid"] = $bankid;

			$req_json = json_encode($req);
			$ch = curl_init("https://" . $this->api_url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $req_json);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					'Content-Type: application/json',
					'Accept: application/json',
					'Content-Length: ' . strlen($req_json),
				)
			);
			curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

			$response = curl_exec($ch);
			curl_close($ch);

			$responseData = json_decode($response);
			if ($responseData->success == 1) {
				// set refid to order meta
				update_post_meta($order_id, 'kpay_refid', $req["refid"]);

				// if kpay payment method is cc, redirect to the payment page
				if ($req["pmethod"] == "cc") {
					return array(
						'result' => 'success',
						'redirect' => $responseData->url,
					);
				}
				$failed = false;
				check:
				sleep(3);
				$check_req = array(
					"refid" => $req["refid"],
					"action" => "checkstatus",
				);
				$check_req = json_encode($check_req);
				$ch = curl_init("https://" . $this->api_url);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
				curl_setopt($ch, CURLOPT_POSTFIELDS, $check_req);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt(
					$ch,
					CURLOPT_HTTPHEADER,
					array(
						'Content-Type: application/json',
						'Accept: application/json',
						'Content-Length: ' . strlen($check_req),
					)
				);
				curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
				curl_setopt($ch, CURLOPT_TIMEOUT, 30);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

				$check_response = curl_exec($ch);
				$check_responseData = json_decode($check_response);
				$check_responseCode = $check_responseData->statusid;
				if ($check_responseCode == '03') {
					$this->check_count++;
					if ($this->check_count > 40) {
						$failed = true;
					} else {
						goto check;
					}
				} else if ($check_responseCode == '01') {
					$failed = false;
				} else {
					$failed = true;
				}
				if (!$failed) {
					wc_reduce_stock_levels($order_id);
					WC()->cart->empty_cart();

					// update order status
					$order->update_status('completed', __('Payment completed.', 'woocommerce'));
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url($order)
					);
				} else {
					wc_add_notice('Payment failed', 'error');
				}
			} else {
				$error = "";
				if (isset($responseData->statusmsg)) {
					$error = $responseData->statusmsg;
					if ($error == "Not enough funds") {
						$error = "Insufficient fund on your mobile account";
					}
				}
				wc_add_notice('Payment failed - ' . $error, 'error');
				NoticeHandler::convert_notices_to_exceptions();
			}
		} else {
			$order->update_status('completed', __('Payment completed.', 'woocommerce'));
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url($order),
			);
		}
	}

	public function capture_payment($order_id)
	{
		$order = wc_get_order($order_id);

		if ('kpay' === $order->get_payment_method()) {
			$refid = get_post_meta($order_id, 'kpay_refid', true);
			$req = array(
				"refid" => $refid,
				"action" => "checkstatus",
			);

			$req_json = json_encode($req);
			$ch = curl_init("https://" . $this->api_url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $req_json);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					'Content-Type: application/json',
					'Accept: application/json',
					'Content-Length: ' . strlen($req_json),
				)
			);
			curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

			$response = curl_exec($ch);

			$result = json_decode($response);

			if (!empty($result->statusid)) {
				switch ($result->statusid) {
					case '01':
						$order->add_order_note(__('Payment completed.', 'woocommerce'));
						$order->update_status('completed');
						$order->set_transaction_id($result->tid);
						$order->save();
						break;
					default:
						$order->add_order_note(__('Payment failed.', 'woocommerce'));
						$order->update_status('failed');
						$order->save();
						break;
				}
			} else {
				$order->add_order_note(__('Payment failed.', 'woocommerce'));
				wc_add_notice('Payment failed', 'error');
				$order->update_status('failed');
				$order->save();
			}
		}
	}

	public function kpay_handle_webhook()
	{
		$body = file_get_contents('php://input');
		$data = json_decode($body);
		$refid = $data->refid;
		$order_id = explode('-', $refid)[0];
		$order = wc_get_order($order_id);
		if ($data->statusid == '01') {
			wc_reduce_stock_levels($order_id);
			$order->update_status('completed', __('KPAY Payment completed.', 'woocommerce'));
		} else {
			$order->update_status('failed', __('Payment failed.', 'woocommerce'));
		}
	}

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page()
	{
		if ($this->instructions) {
			echo wp_kses_post(wpautop(wptexturize($this->instructions)));
		}
	}


	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin  Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions($order, $sent_to_admin, $plain_text = false)
	{
		if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
			echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
		}
	}
}
