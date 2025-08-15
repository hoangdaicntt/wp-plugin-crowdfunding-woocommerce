<?php
/**
 * Class quản lý Cài đặt từ thiện
 */

if (!defined('ABSPATH')) {
    exit;
}

class CharitySettings {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Khởi tạo hooks
     */
    private function init_hooks() {
        // AJAX handlers cho settings
        add_action('wp_ajax_charity_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_charity_import_products', array($this, 'ajax_import_products'));
        add_action('wp_ajax_charity_get_products_count', array($this, 'ajax_get_products_count'));

        // Hook để tự động set sản phẩm mới thành chiến dịch
        add_action('wp_insert_post', array($this, 'auto_set_new_product_as_campaign'), 10, 3);
    }

    /**
     * Render trang cài đặt
     */
    public static function render_settings_page() {
        // Lấy các thiết lập hiện tại
        $import_all_products = get_option('charity_import_all_products', 'no');
        $auto_set_campaign = get_option('charity_auto_set_campaign', 'no');
        $default_goal_amount = get_option('charity_default_goal_amount', 1000000);
        $enable_anonymous_donations = get_option('charity_enable_anonymous_donations', 'yes');

        // Lấy thống kê sản phẩm
        $total_products = wp_count_posts('product')->publish;
        $charity_products = self::count_charity_products();
        $regular_products = $total_products - $charity_products;
        ?>

        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Cài đặt Từ thiện', 'charity-woocommerce'); ?></h1>
            <hr class="wp-header-end">

            <div class="charity-settings-wrapper">
                <form id="charity-settings-form" class="charity-form">
                    <input type="hidden" name="action" value="charity_save_settings" />
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('charity_ajax_nonce'); ?>" />

                    <h2><?php _e('Cài đặt chung', 'charity-woocommerce'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="enable_anonymous_donations"><?php _e('Cho phép ủng hộ ẩn danh', 'charity-woocommerce'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="enable_anonymous_donations" name="enable_anonymous_donations" value="yes"
                                           <?php checked($enable_anonymous_donations, 'yes'); ?> />
                                    <?php _e('Người dùng có thể chọn ủng hộ ẩn danh', 'charity-woocommerce'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="default_goal_amount"><?php _e('Mục tiêu mặc định (VNĐ)', 'charity-woocommerce'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="default_goal_amount" name="default_goal_amount"
                                       value="<?php echo esc_attr($default_goal_amount); ?>"
                                       min="0" step="1000" class="regular-text" />
                                <p class="description">
                                    <?php _e('Số tiền mục tiêu mặc định khi tạo chiến dịch mới', 'charity-woocommerce'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <h2><?php _e('Quản lý sản phẩm', 'charity-woocommerce'); ?></h2>

                    <!-- Thống kê sản phẩm -->
                    <div class="product-stats-box">
                        <h3><?php _e('Thống kê sản phẩm hiện tại', 'charity-woocommerce'); ?></h3>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <h4><?php _e('Tổng sản phẩm', 'charity-woocommerce'); ?></h4>
                                <p class="stat-value" id="total-products-count"><?php echo number_format($total_products); ?></p>
                            </div>
                            <div class="stat-card">
                                <h4><?php _e('Chiến dịch từ thiện', 'charity-woocommerce'); ?></h4>
                                <p class="stat-value" id="charity-products-count"><?php echo number_format($charity_products); ?></p>
                            </div>
                            <div class="stat-card">
                                <h4><?php _e('Sản phẩm thường', 'charity-woocommerce'); ?></h4>
                                <p class="stat-value" id="regular-products-count"><?php echo number_format($regular_products); ?></p>
                            </div>
                        </div>
                    </div>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="auto_set_campaign"><?php _e('Tự động đặt làm chiến dịch', 'charity-woocommerce'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="auto_set_campaign" name="auto_set_campaign" value="yes"
                                           <?php checked($auto_set_campaign, 'yes'); ?> />
                                    <?php _e('Tự động đặt sản phẩm mới thành chiến dịch từ thiện', 'charity-woocommerce'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Khi bật, tất cả sản phẩm mới sẽ được tự động đặt làm chiến dịch từ thiện', 'charity-woocommerce'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="import_all_products"><?php _e('Chuyển đổi sản phẩm hiện tại', 'charity-woocommerce'); ?></label>
                            </th>
                            <td>
                                <div class="import-products-section">
                                    <label>
                                        <input type="checkbox" id="import_all_products" name="import_all_products" value="yes"
                                               <?php checked($import_all_products, 'yes'); ?> />
                                        <?php _e('Chuyển đổi tất cả sản phẩm hiện tại thành chiến dịch từ thiện', 'charity-woocommerce'); ?>
                                    </label>

                                    <div id="import_options" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-left: 4px solid #007cba; display: <?php echo $import_all_products === 'yes' ? 'block' : 'none'; ?>;">
                                        <p><strong><?php _e('Tùy chọn chuyển đổi:', 'charity-woocommerce'); ?></strong></p>

                                        <?php if ($regular_products > 0) : ?>
                                            <p><?php printf(__('Sẽ chuyển đổi <strong>%d sản phẩm</strong> thành chiến dịch từ thiện.', 'charity-woocommerce'), $regular_products); ?></p>
                                            <p class="description">
                                                <?php _e('Các sản phẩm sẽ được thiết lập với mục tiêu mặc định và số tiền đã quyên góp bằng 0.', 'charity-woocommerce'); ?>
                                            </p>

                                            <button type="button" id="start_import_btn" class="button button-secondary">
                                                <span class="dashicons dashicons-update"></span>
                                                <?php _e('Bắt đầu chuyển đổi', 'charity-woocommerce'); ?>
                                            </button>

                                            <div id="import_progress" style="display: none; margin-top: 15px;">
                                                <p><strong><?php _e('Đang chuyển đổi...', 'charity-woocommerce'); ?></strong></p>
                                                <div class="progress-bar">
                                                    <div class="progress-bar-fill" id="import_progress_bar" style="width: 0%;">0%</div>
                                                </div>
                                                <div id="import_status"></div>
                                            </div>
                                        <?php else : ?>
                                            <p class="description">
                                                <?php _e('Tất cả sản phẩm hiện tại đã là chiến dịch từ thiện.', 'charity-woocommerce'); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php _e('Lưu cài đặt', 'charity-woocommerce'); ?>
                        </button>
                        <button type="button" class="button" id="reset_settings_btn">
                            <?php _e('Khôi phục mặc định', 'charity-woocommerce'); ?>
                        </button>
                    </p>
                </form>

                <div id="charity-message"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Đếm số sản phẩm là chiến dịch từ thiện
     */
    private static function count_charity_products() {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_is_charity_campaign'
            AND pm.meta_value = 'yes'
        "));

        return intval($count);
    }

    /**
     * Tự động đặt sản phẩm mới thành chiến dịch từ thiện
     */
    public function auto_set_new_product_as_campaign($post_id, $post, $update) {
        // Chỉ áp dụng cho sản phẩm mới (không phải update)
        if ($update || $post->post_type !== 'product' || $post->post_status !== 'publish') {
            return;
        }

        // Kiểm tra setting có bật không
        if (get_option('charity_auto_set_campaign', 'no') !== 'yes') {
            return;
        }

        // Kiểm tra sản phẩm chưa phải là chiến dịch
        if (get_post_meta($post_id, '_is_charity_campaign', true) === 'yes') {
            return;
        }

        $default_goal = get_option('charity_default_goal_amount', 1000000);

        // Đặt làm chiến dịch từ thiện
        update_post_meta($post_id, '_is_charity_campaign', 'yes');
        update_post_meta($post_id, '_charity_goal', $default_goal);
        update_post_meta($post_id, '_charity_raised', 0);

        // Cập nhật thông tin sản phẩm
        $product = wc_get_product($post_id);
        if ($product) {
            $product->set_price(0);
            $product->set_regular_price(0);
            $product->set_manage_stock(false);
            $product->set_sold_individually(true);
            $product->save();
        }
    }

    /**
     * AJAX: Lưu cài đặt
     */
    public function ajax_save_settings() {
        check_ajax_referer('charity_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Bạn không có quyền thực hiện hành động này.', 'charity-woocommerce'));
        }

        $import_all_products = isset($_POST['import_all_products']) ? 'yes' : 'no';
        $auto_set_campaign = isset($_POST['auto_set_campaign']) ? 'yes' : 'no';
        $enable_anonymous_donations = isset($_POST['enable_anonymous_donations']) ? 'yes' : 'no';
        $default_goal_amount = floatval($_POST['default_goal_amount']) ?: 1000000;

        // Validate default goal amount
        if ($default_goal_amount < 1000) {
            wp_send_json_error(__('Mục tiêu mặc định phải ít nhất 1,000 VNĐ.', 'charity-woocommerce'));
        }

        // Lưu các thiết lập
        update_option('charity_import_all_products', $import_all_products);
        update_option('charity_auto_set_campaign', $auto_set_campaign);
        update_option('charity_enable_anonymous_donations', $enable_anonymous_donations);
        update_option('charity_default_goal_amount', $default_goal_amount);

        wp_send_json_success(array(
            'message' => __('Đã lưu cài đặt thành công!', 'charity-woocommerce')
        ));
    }

    /**
     * AJAX: Lấy số lượng sản phẩm cần chuyển đổi
     */
    public function ajax_get_products_count() {
        check_ajax_referer('charity_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Bạn không có quyền thực hiện hành động này.', 'charity-woocommerce'));
        }

        global $wpdb;

        // Lấy các sản phẩm chưa phải là chiến dịch từ thiện
        $products = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_is_charity_campaign'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value != 'yes')
            LIMIT 10
        "));

        $total_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_is_charity_campaign'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value != 'yes')
        "));

        wp_send_json_success(array(
            'count' => intval($total_count),
            'products' => $products
        ));
    }

    /**
     * AJAX: Chuyển đổi sản phẩm thành chiến dịch từ thiện
     */
    public function ajax_import_products() {
        check_ajax_referer('charity_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Bạn không có quyền thực hiện hành động này.', 'charity-woocommerce'));
        }

        $batch_size = 10; // Xử lý 10 sản phẩm mỗi lần
        $offset = intval($_POST['offset']) ?: 0;
        $default_goal = get_option('charity_default_goal_amount', 1000000);

        global $wpdb;

        // Lấy các sản phẩm chưa phải là chiến dịch từ thiện
        $products = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_is_charity_campaign'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value != 'yes')
            LIMIT %d OFFSET %d
        ", $batch_size, $offset));

        $processed = 0;
        $errors = array();

        foreach ($products as $product_data) {
            try {
                $product = wc_get_product($product_data->ID);
                if ($product) {
                    // Đặt làm chiến dịch từ thiện
                    $product->update_meta_data('_is_charity_campaign', 'yes');
                    $product->update_meta_data('_charity_goal', $default_goal);
                    $product->update_meta_data('_charity_raised', 0);

                    // Đặt giá = 0 cho chiến dịch từ thiện và các thiết lập khác
                    $product->set_price(0);
                    $product->set_regular_price(0);
                    $product->set_sale_price('');
                    $product->set_manage_stock(false);
                    $product->set_sold_individually(true);

                    $product->save();
                    $processed++;
                }
            } catch (Exception $e) {
                $errors[] = sprintf(__('Lỗi khi chuyển đổi sản phẩm %s: %s', 'charity-woocommerce'), $product_data->post_title, $e->getMessage());
            }
        }

        // Lấy tổng số sản phẩm còn lại cần xử lý
        $remaining = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_is_charity_campaign'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value != 'yes')
        "));

        wp_send_json_success(array(
            'processed' => $processed,
            'remaining' => intval($remaining),
            'errors' => $errors,
            'completed' => intval($remaining) === 0,
            'message' => sprintf(__('Đã chuyển đổi %d sản phẩm. Còn lại %d sản phẩm.', 'charity-woocommerce'), $processed, $remaining)
        ));
    }

    /**
     * Lấy danh sách sản phẩm để preview
     */
    public static function get_regular_products_preview($limit = 5) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title, p.post_date
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_is_charity_campaign'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value != 'yes')
            ORDER BY p.post_date DESC
            LIMIT %d
        ", $limit));
    }

    /**
     * Kiểm tra xem có sản phẩm nào cần chuyển đổi không
     */
    public static function has_products_to_convert() {
        $total_products = wp_count_posts('product')->publish;
        $charity_products = self::count_charity_products();

        return ($total_products > $charity_products);
    }

    /**
     * Reset tất cả sản phẩm về trạng thái sản phẩm thường (không phải chiến dịch)
     */
    public static function reset_all_products() {
        global $wpdb;

        // Lấy tất cả sản phẩm là chiến dịch
        $charity_products = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND pm.meta_key = '_is_charity_campaign'
            AND pm.meta_value = 'yes'
        "));

        $reset_count = 0;
        foreach ($charity_products as $product_data) {
            // Xóa các meta của chiến dịch
            delete_post_meta($product_data->ID, '_is_charity_campaign');
            delete_post_meta($product_data->ID, '_charity_goal');
            delete_post_meta($product_data->ID, '_charity_raised');

            $reset_count++;
        }

        return $reset_count;
    }
}
