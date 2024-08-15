<?php

add_filter('woocommerce_gateway_description', 'esicia_kpay_description_fields', 20, 2);
add_action('woocommerce_checkout_process', 'esicia_kpay_description_fields_validation');
add_action('woocommerce_checkout_update_order_meta', 'esicia_checkout_update_order_meta', 10, 1);
add_action('woocommerce_admin_order_data_after_billing_address', 'esicia_order_data_after_billing_address', 10, 1);
add_action('woocommerce_order_item_meta_end', 'esicia_order_item_meta_end', 10, 3);

function esicia_kpay_description_fields($description, $payment_id)
{

    if ('kpay' !== $payment_id) {
        return $description;
    }

    ob_start();

    echo '<link rel="stylesheet" href="' . plugin_dir_url(__FILE__) . 'css/kpay.css' . '">';
    echo '<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>';
    // wc input field
?>
    <div class="flex flex-column" id="kpay-form">
        <div>
            <label for="name">Payment Methods</label>
            <br />
            <div class="payment_methods">
                <div>
                    <input type="radio" id="momo" name="kpay_payment_method" value="momo" checked />
                    <label for="momo" class="method_radio_label">
                        <img src="<?php echo plugin_dir_url(__FILE__) . 'images/momo.svg'; ?>" class="method_icons" />
                        <img src="<?php echo plugin_dir_url(__FILE__) . 'images/airtel.svg'; ?>" class="method_icons" />
                    </label>
                </div>
                <div>
                    <input type="radio" id="card" name="kpay_payment_method" value="cc" />
                    <label for="card" class="method_radio_label">
                        <img src="<?php echo plugin_dir_url(__FILE__) . 'images/visa.svg'; ?>" class="method_icons" />
                        <img src="<?php echo plugin_dir_url(__FILE__) . 'images/mastercard.svg'; ?>" class="method_icons" />
                    </label>
                </div>
            </div>
        </div>
    </div>
<?php

    $description .= ob_get_clean();
    return $description;
}

function esicia_kpay_description_fields_validation()
{
    if ("kpay" === $_POST['payment_method'] && !isset($_POST['kpay_payment_method'])  || empty($_POST['kpay_payment_method'])) {
        wc_add_notice('Please choose a Kpay Payment Method', 'error');
    }
}

function esicia_checkout_update_order_meta($order_id)
{
    if (isset($_POST['kpay_payment_method']) || !empty($_POST['kpay_payment_method'])) {
        update_post_meta($order_id, 'kpay_payment_method', sanitize_text_field($_POST['kpay_payment_method']));
    }
}

function esicia_order_data_after_billing_address($order)
{
    echo '<p><strong>' . __('KPAY', 'woocommerce') . '</strong><br></p>';
}

function esicia_order_item_meta_end($item_id, $item, $order)
{
    echo '<p><strong>' . __('KPAY', 'woocommerce') . '</strong><br></p>';
}
