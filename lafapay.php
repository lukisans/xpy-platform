<?php
/**
 * Plugin Name: Lafapay
 * Description: Crypto payment gateway plugin for WooCommerce using USDC and USDT.
 * Version: 0.1.0
 * Author: The WordPress Contributors
 * Author URI: https://woo.com
 * Text Domain: lafapay
 * Domain Path: /languages
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package lafapay
 */

defined('ABSPATH') || exit;

if (!defined('LAFAPAY_PLUGIN_FILE')) {
    define('LAFAPAY_PLUGIN_FILE', __FILE__);
}

if (!defined('LAFAPAY_PLUGIN_URL')) {
    define('LAFAPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('LAFAPAY_PLUGIN_PATH')) {
    define('LAFAPAY_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

/**
 * Display an admin notice if WooCommerce is not active.
 */
function lafapay_missing_wc_notice() {
    echo '<div class="error"><p><strong>' .
        sprintf(
            esc_html__('Lafapay requires WooCommerce to be installed and active. You can download %s here.', 'lafapay'),
            '<a href="https://woo.com/" target="_blank">WooCommerce</a>'
        ) .
        '</strong></p></div>';
}

/**
 * Activation hook: check WooCommerce dependency.
 */
function lafapay_activate() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'lafapay_missing_wc_notice');
    }
}
register_activation_hook(__FILE__, 'lafapay_activate');

if (!class_exists('Lafapay')) :
    /**
     * Main Lafapay class.
     */
    class Lafapay {
        /**
         * Plugin instance.
         *
         * @var Lafapay
         */
        private static $instance;

        /**
         * Constructor.
         */
        public function __construct() {
            add_action('plugins_loaded', array($this, 'init_gateway'));
            add_filter('woocommerce_payment_gateways', array($this, 'add_gateway'));
            add_action('admin_menu', array($this, 'add_setup_menu'));
        }

        /**
         * Initialize the gateway.
         */
        public function init_gateway() {
            if (!class_exists('WC_Payment_Gateway')) return;

            include_once('includes/class-lafapay-gateway.php');
        }

        /**
         * Add gateway to WooCommerce.
         */
        public function add_gateway($gateways) {
            $gateways[] = 'WC_Lafapay_Gateway';
            return $gateways;
        }

        /**
         * Add setup menu in admin.
         */
        public function add_setup_menu() {
            add_submenu_page(
                'woocommerce',
                'Lafapay Setup',
                'Lafapay Setup',
                'manage_options',
                'lafapay-setup',
                array($this, 'setup_page')
            );
        }

        /**
         * Setup page content.
         */
        public function setup_page() {
            include_once('includes/admin-setup-page.php');
        }

        /**
         * Disable cloning.
         */
        public function __clone() {
            if (function_exists('wc_doing_it_wrong')) {
                wc_doing_it_wrong(__FUNCTION__, __('Cloning is forbidden.', 'lafapay'), '0.1.0');
            }
        }

        /**
         * Disable unserialization.
         */
        public function __wakeup() {
            if (function_exists('wc_doing_it_wrong')) {
                wc_doing_it_wrong(__FUNCTION__, __('Unserializing instances is forbidden.', 'lafapay'), '0.1.0');
            }
        }

        /**
         * Singleton access.
         *
         * @return Lafapay
         */
        public static function instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }
    }
endif;

/**
 * Initialize plugin.
 */
function lafapay_init() {
    load_plugin_textdomain('lafapay', false, dirname(plugin_basename(__FILE__)) . '/languages');

    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'lafapay_missing_wc_notice');
        return;
    }

    Lafapay::instance();
}
add_action('plugins_loaded', 'lafapay_init', 10);
