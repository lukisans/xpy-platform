<?php
/**
 * Lafapay Payment Gateway
 */

defined('ABSPATH') || exit;

class WC_Lafapay_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'lafapay';
        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = 'Lafapay';
        $this->method_description = 'Accept crypto payments with USDC and USDT via Lafapay';

        $this->supports = array(
            'products'
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->wallet_address = get_option('lafapay_wallet_address', '');
        $this->sdk_key = get_option('lafapay_sdk_key', '');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $setup_url = admin_url('admin.php?page=lafapay-setup');

        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable Lafapay Payment',
                'default' => 'no'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title shown during checkout.',
                'default'     => 'Crypto Payment (USDC/USDT)',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'Payment method description shown during checkout.',
                'default'     => 'Pay with USDC or USDT cryptocurrency.',
            ),
            'setup_notice' => array(
                'title'       => 'Setup Required',
                'type'        => 'title',
                'description' => 'Please complete the setup process first: <a href="' . $setup_url . '">Go to Lafapay Setup</a>',
            ),
        );
    }

    public function is_available() {
        if ($this->enabled === 'no') {
            return false;
        }

        // Check if setup is complete
        $wallet = get_option('lafapay_wallet_address', '');
        $sdk_key = get_option('lafapay_sdk_key', '');

        return !empty($wallet) && !empty($sdk_key);
    }

    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }

        // Show crypto selection
        echo '<fieldset id="wc-' . esc_attr($this->id) . '-form">';
        echo '<p class="form-row form-row-wide">';
        echo '<label for="lafapay_crypto">Choose Cryptocurrency <span class="required">*</span></label>';
        echo '<select id="lafapay_crypto" name="lafapay_crypto" required>';
        echo '<option value="">Select crypto...</option>';
        echo '<option value="USDC">USDC</option>';
        echo '<option value="USDT">USDT</option>';
        echo '</select>';
        echo '</p>';
        echo '</fieldset>';
    }

    public function validate_fields() {
        if (empty($_POST['lafapay_crypto'])) {
            wc_add_notice('Please select a cryptocurrency.', 'error');
            return false;
        }
        return true;
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $crypto = sanitize_text_field($_POST['lafapay_crypto']);

        // Save crypto choice to order
        $order->update_meta_data('_lafapay_crypto', $crypto);
        $order->update_meta_data('_lafapay_wallet', $this->wallet_address);

        // Mark as on-hold for manual verification
        $order->update_status('on-hold', __('Awaiting crypto payment verification.', 'lafapay'));

        // Reduce stock
        wc_reduce_stock_levels($order_id);

        // Remove cart
        WC()->cart->empty_cart();

        $order->save();

        // Return thankyou redirect
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }
}
