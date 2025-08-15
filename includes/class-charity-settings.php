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
            <h1 class="wp-heading-inline">Cài đặt Từ thiện</h1>
            <hr class="wp-header-end">

            <div class="charity-settings-wrapper">
                <form id="charity-settings-form" class="charity-form" method="post" action="">
                    <?php wp_nonce_field('charity_settings_nonce', 'charity_settings_nonce'); ?>

                    <h2>Cài đặt chung</h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="enable_anonymous_donations">Cho phép ủng hộ ẩn danh</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="enable_anonymous_donations" name="enable_anonymous_donations" value="yes"
                                           <?php checked($enable_anonymous_donations, 'yes'); ?> />
                                    Người dùng có thể chọn ủng hộ ẩn danh
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="default_goal_amount">Mục tiêu mặc định (VNĐ)</label>
                            </th>
                            <td>
                                <input type="number" id="default_goal_amount" name="default_goal_amount"
                                       value="<?php echo esc_attr($default_goal_amount); ?>"
                                       min="0" step="1000" class="regular-text" />
                                <p class="description">
                                    Số tiền mục tiêu mặc định khi tạo chiến dịch mới
                                </p>
                            </td>
                        </tr>
                    </table>

                    <h2>Quản lý sản phẩm</h2>

                    <!-- Thống kê sản phẩm -->
                    <div class="product-stats-box" style="background: #f1f1f1; padding: 15px; margin: 20px 0; border-radius: 5px;">
                        <h3>Thống kê sản phẩm hiện tại</h3>
                        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                            <div class="stat-card" style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                <h4>Tổng sản phẩm</h4>
                                <p class="stat-value" style="font-size: 24px; font-weight: bold; color: #2271b1;">
                                    <?php echo number_format($total_products); ?>
                                </p>
                            </div>
                            <div class="stat-card" style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                <h4>Chiến dịch từ thiện</h4>
                                <p class="stat-value" style="font-size: 24px; font-weight: bold; color: #00a32a;">
                                    <?php echo number_format($charity_products); ?>
                                </p>
                            </div>
                            <div class="stat-card" style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                <h4>Sản phẩm thường</h4>
                                <p class="stat-value" style="font-size: 24px; font-weight: bold; color: #757575;">
                                    <?php echo number_format($regular_products); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="auto_set_campaign">Tự động đặt làm chiến dịch</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="auto_set_campaign" name="auto_set_campaign" value="yes"
                                           <?php checked($auto_set_campaign, 'yes'); ?> />
                                    Tự động đặt sản phẩm mới thành chiến dịch từ thiện
                                </label>
                                <p class="description">
                                    Khi bật, tất cả sản phẩm mới sẽ được tự động đặt làm chiến dịch từ thiện
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="import_all_products">Chuyển đổi sản phẩm hiện tại</label>
                            </th>
                            <td>
                                <div class="import-products-section">
                                    <label>
                                        <input type="checkbox" id="import_all_products" name="import_all_products" value="yes"
                                               <?php checked($import_all_products, 'yes'); ?> />
                                        Chuyển đổi tất cả sản phẩm hiện tại thành chiến dịch từ thiện
                                    </label>

                                    <div id="import_options" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-left: 4px solid #007cba; display: <?php echo $import_all_products === 'yes' ? 'block' : 'none'; ?>;">
                                        <p><strong>Tùy chọn chuyển đổi:</strong></p>

                                        <?php if ($regular_products > 0) : ?>
                                            <p>Sẽ chuyển đổi <strong><?php echo $regular_products; ?> sản phẩm</strong> thành chiến dịch từ thiện.</p>
                                            <p class="description">
                                                Các sản phẩm sẽ được thiết lập với mục tiêu mặc định và số tiền đã quyên góp bằng 0.
                                            </p>

                                            <button type="button" id="start_import_btn" class="button button-secondary">
                                                <span class="dashicons dashicons-update"></span>
                                                Bắt đầu chuyển đổi
                                            </button>

                                            <div id="import_progress" style="display: none; margin-top: 15px;">
                                                <p><strong>Đang chuyển đổi...</strong></p>
                                                <div class="progress-bar" style="width: 100%; height: 20px; background: #ddd; border-radius: 10px; overflow: hidden;">
                                                    <div class="progress-bar-fill" id="import_progress_bar" style="width: 0%; height: 100%; background: #007cba; transition: width 0.3s; text-align: center; color: white; line-height: 20px;">0%</div>
                                                </div>
                                                <div id="import_status"></div>
                                            </div>
                                        <?php else : ?>
                                            <p class="description">
                                                Tất cả sản phẩm hiện tại đã là chiến dịch từ thiện.
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button id="charity-settings-submit" type="submit" class="button button-primary">
                            Lưu cài đặt
                        </button>
                        <button type="button" class="button" id="reset_settings_btn">
                            Khôi phục mặc định
                        </button>
                    </p>
                </form>

                <div id="charity-message" class="notice" style="display: none;"></div>
            </div>
        </div>

        <style>
        .charity-loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        #charity-message {
            margin: 15px 0;
            padding: 12px;
        }
        #charity-message.success {
            border-left-color: #00a32a;
            background: #d4edda;
            color: #155724;
        }
        #charity-message.error {
            border-left-color: #d63638;
            background: #f8d7da;
            color: #721c24;
        }
        </style>
        <?php
    }

    /**
     * Đếm số sản phẩm là chiến dịch từ thiện
     */
    private static function count_charity_products() {
        global $wpdb;

        $count = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_is_charity_campaign'
            AND pm.meta_value = 'yes'
        ");

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
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'charity_ajax_nonce')) {
            wp_send_json_error('Nonce verification failed');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Bạn không có quyền thực hiện hành động này.');
            return;
        }

        // Get and sanitize input
        $import_all_products = isset($_POST['import_all_products']) && $_POST['import_all_products'] === 'yes' ? 'yes' : 'no';
        $auto_set_campaign = isset($_POST['auto_set_campaign']) && $_POST['auto_set_campaign'] === 'yes' ? 'yes' : 'no';
        $enable_anonymous_donations = isset($_POST['enable_anonymous_donations']) && $_POST['enable_anonymous_donations'] === 'yes' ? 'yes' : 'no';
        $default_goal_amount = isset($_POST['default_goal_amount']) ? floatval($_POST['default_goal_amount']) : 1000000;

        // Validate default goal amount
        if ($default_goal_amount < 1000) {
            wp_send_json_error('Mục tiêu mặc định phải ít nhất 1,000 VNĐ.');
            return;
        }

        // Save settings
        update_option('charity_import_all_products', $import_all_products);
        update_option('charity_auto_set_campaign', $auto_set_campaign);
        update_option('charity_enable_anonymous_donations', $enable_anonymous_donations);
        update_option('charity_default_goal_amount', $default_goal_amount);

        // Log for debugging
        error_log('Charity Settings Saved: ' . json_encode(array(
            'import_all_products' => $import_all_products,
            'auto_set_campaign' => $auto_set_campaign,
            'enable_anonymous_donations' => $enable_anonymous_donations,
            'default_goal_amount' => $default_goal_amount
        )));

        wp_send_json_success(array(
            'message' => 'Đã lưu cài đặt thành công!'
        ));
    }

    /**
     * AJAX: Lấy số lượng sản phẩm cần chuyển đổi
     */
    public function ajax_get_products_count() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'charity_ajax_nonce')) {
            wp_send_json_error('Nonce verification failed');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Bạn không có quyền thực hiện hành động này.');
            return;
        }

        global $wpdb;

        $products = $wpdb->get_results("
            SELECT p.ID, p.post_title
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_is_charity_campaign'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value != 'yes')
            LIMIT 10
        ");

        $total_count = $wpdb->get_var("
            SELECT COUNT(p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_is_charity_campaign'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value != 'yes')
        ");

        wp_send_json_success(array(
            'count' => intval($total_count),
            'products' => $products
        ));
    }

    /**
     * AJAX: Chuyển đổi sản phẩm thành chiến dịch từ thiện
     */
    public function ajax_import_products() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'charity_ajax_nonce')) {
            wp_send_json_error('Nonce verification failed');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Bạn không có quyền thực hiện hành động này.');
            return;
        }

        $batch_size = 10; // Xử lý 10 sản phẩm mỗi lần
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $default_goal = get_option('charity_default_goal_amount', 1000000);

        global $wpdb;

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
                $errors[] = sprintf('Lỗi khi chuyển đổi sản phẩm %s: %s', $product_data->post_title, $e->getMessage());
            }
        }

        $remaining = $wpdb->get_var("
            SELECT COUNT(p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_is_charity_campaign'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value != 'yes')
        ");

        wp_send_json_success(array(
            'processed' => $processed,
            'remaining' => intval($remaining),
            'errors' => $errors,
            'completed' => intval($remaining) === 0,
            'message' => sprintf('Đã chuyển đổi %d sản phẩm. Còn lại %d sản phẩm.', $processed, $remaining)
        ));
    }
}
