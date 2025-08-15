<?php
/**
 * Plugin Name: Từ thiện WooCommerce
 * Plugin URI: https://yourwebsite.com/
 * Description: Plugin Từ thiện tích hợp với WooCommerce
 * Version: 1.0.1
 * Author: Your Name
 * Text Domain: charity-woocommerce
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Ngăn truy cập trực tiếp
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CHARITY_WC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CHARITY_WC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CHARITY_WC_VERSION', '1.0.2');

/**
 * Class chính của plugin
 */
class CharityWooCommerce {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include các file cần thiết
     */
    private function includes() {
        // Include các class quản lý
        require_once CHARITY_WC_PLUGIN_PATH . 'includes/class-charity-campaigns.php';
        require_once CHARITY_WC_PLUGIN_PATH . 'includes/class-charity-donations.php';
        require_once CHARITY_WC_PLUGIN_PATH . 'includes/class-charity-settings.php';
    }

    /**
     * Khởi tạo hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Khởi tạo các module
        add_action('init', array($this, 'init_modules'));
    }

    /**
     * Khởi tạo plugin
     */
    public function init() {
        // Kiểm tra WooCommerce đã được kích hoạt
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Load text domain
        load_plugin_textdomain('charity-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Khởi tạo các module
     */
    public function init_modules() {
        if (class_exists('WooCommerce')) {
            // Khởi tạo module Campaigns

            // Khởi tạo module Settings
            CharitySettings::get_instance();
            CharityCampaigns::get_instance();

            // Khởi tạo module Settings
            CharitySettings::get_instance();

            // Khởi tạo module Donations

            // Khởi tạo module Settings
            CharitySettings::get_instance();
            CharityDonations::get_instance();
        }
    }

    /**
     * Thông báo khi WooCommerce chưa được cài đặt
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Plugin Từ thiện WooCommerce yêu cầu WooCommerce phải được cài đặt và kích hoạt!', 'charity-woocommerce'); ?></p>
        </div>
        <?php
    }

    /**
     * Thêm menu admin
     */
    public function add_admin_menu() {
        // Menu chính
        add_menu_page(
            __('Từ thiện', 'charity-woocommerce'),
            __('Từ thiện', 'charity-woocommerce'),
            'manage_options',
            'charity',
            array('CharityCampaigns', 'render_campaigns_page'),
            'dashicons-heart',
            30
        );

        // Submenu Chiến dịch
        add_submenu_page(
            'charity',
            __('Chiến dịch', 'charity-woocommerce'),
            __('Chiến dịch', 'charity-woocommerce'),
            'manage_options',
            'charity',
            array('CharityCampaigns', 'render_campaigns_page')
        );

        // Submenu Cài đặt
        add_submenu_page(
            'charity',
            __('Cài đặt', 'charity-woocommerce'),
            __('Cài đặt', 'charity-woocommerce'),
            'manage_options',
            'charity-settings',
            array('CharitySettings', 'render_settings_page')
        );
        // Submenu Ủng hộ
        add_submenu_page(
            'charity',
            __('Ủng hộ', 'charity-woocommerce'),
            __('Ủng hộ', 'charity-woocommerce'),
            'manage_options',
            'charity-donations',
            array('CharityDonations', 'render_donations_page')
        );
    }

    /**
     * Enqueue scripts và styles cho admin
     */
    public function enqueue_admin_scripts($hook) {
        // Chỉ load trên các trang của plugin
        if (strpos($hook, 'charity') === false) {
            return;
        }

        // Enqueue media uploader
        wp_enqueue_media();

        // Enqueue styles
        wp_enqueue_style(
            'charity-admin-style',
            CHARITY_WC_PLUGIN_URL . 'assets/admin-style.css',
            array(),
            CHARITY_WC_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'charity-admin-script',
            CHARITY_WC_PLUGIN_URL . 'assets/admin-script.js',
            array('jquery'),
            CHARITY_WC_VERSION,
            true
        );

        // Localize script
        wp_localize_script('charity-admin-script', 'charity_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('charity_ajax_nonce'),
            'confirm_delete' => __('Bạn có chắc chắn muốn xóa?', 'charity-woocommerce'),
            'loading_text' => __('Đang xử lý...', 'charity-woocommerce'),
            'success_text' => __('Thành công!', 'charity-woocommerce'),
            'error_text' => __('Có lỗi xảy ra!', 'charity-woocommerce')
        ));
    }

    /**
     * Helper function để lấy tất cả chiến dịch
     */
    public static function get_all_campaigns() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_is_charity_campaign',
                    'value' => 'yes',
                    'compare' => '='
                )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        );

        return new WP_Query($args);
    }

    /**
     * Helper function để format tiền
     */
    public static function format_currency($amount) {
        return wc_price($amount);
    }

    /**
     * Helper function để tính phần trăm
     */
    public static function calculate_percentage($raised, $goal) {
        if ($goal <= 0) {
            return 0;
        }
        return min(round(($raised / $goal) * 100, 2), 100);
    }
}

/**
 * Hook khi kích hoạt plugin
 */
register_activation_hook(__FILE__, function() {
    // Tạo database tables nếu cần
    flush_rewrite_rules();

    // Set default options
    add_option('charity_wc_version', CHARITY_WC_VERSION);
});

/**
 * Hook khi tắt plugin
 */
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

/**
 * Khởi tạo plugin
 */
add_action('plugins_loaded', function() {
    CharityWooCommerce::get_instance();
});

/**
 * Helper functions toàn cục
 */
if (!function_exists('charity_get_campaign')) {
    function charity_get_campaign($campaign_id) {
        $product = wc_get_product($campaign_id);
        if ($product && $product->get_meta('_is_charity_campaign') === 'yes') {
            return $product;
        }
        return false;
    }
}

if (!function_exists('charity_get_campaign_raised')) {
    function charity_get_campaign_raised($campaign_id) {
        $product = wc_get_product($campaign_id);
        if ($product) {
            return floatval($product->get_meta('_charity_raised'));
        }
        return 0;
    }
}

if (!function_exists('charity_get_campaign_goal')) {
    function charity_get_campaign_goal($campaign_id) {
        $product = wc_get_product($campaign_id);
        if ($product) {
            return floatval($product->get_meta('_charity_goal'));
        }
        return 0;
    }
}
