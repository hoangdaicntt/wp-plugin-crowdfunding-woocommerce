<?php
/**
 * Class quản lý Chiến dịch từ thiện
 */

if (!defined('ABSPATH')) {
    exit;
}

class CharityCampaigns {

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
        // AJAX handlers cho campaigns
        add_action('wp_ajax_charity_create_campaign', array($this, 'ajax_create_campaign'));
        add_action('wp_ajax_charity_update_campaign', array($this, 'ajax_update_campaign'));
        add_action('wp_ajax_charity_delete_campaign', array($this, 'ajax_delete_campaign'));
        add_action('wp_ajax_charity_get_campaign', array($this, 'ajax_get_campaign'));
        add_action('wp_ajax_charity_load_campaigns', array($this, 'ajax_load_campaigns'));
        add_action('wp_ajax_charity_recalculate_raised', array($this, 'ajax_recalculate_raised'));
    }

    /**
     * Render trang quản lý chiến dịch
     */
    public static function render_campaigns_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Quản lý Chiến dịch từ thiện', 'charity-woocommerce'); ?></h1>

            <?php if ($action === 'list') : ?>
                <a href="<?php echo admin_url('admin.php?page=charity&action=add'); ?>" class="page-title-action">
                    <?php _e('Thêm chiến dịch mới', 'charity-woocommerce'); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo admin_url('admin.php?page=charity'); ?>" class="page-title-action">
                    <?php _e('Quay lại danh sách', 'charity-woocommerce'); ?>
                </a>
            <?php endif; ?>

            <hr class="wp-header-end">

            <?php
            switch ($action) {
                case 'add':
                    self::render_campaign_form();
                    break;
                case 'edit':
                    self::render_campaign_form($campaign_id);
                    break;
                default:
                    self::render_campaigns_list();
                    break;
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render danh sách chiến dịch
     */
    private static function render_campaigns_list() {
        ?>
        <div class="charity-campaigns-list">
            <!-- Bộ lọc -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select id="filter_campaign_status">
                        <option value=""><?php _e('Tất cả trạng thái', 'charity-woocommerce'); ?></option>
                        <option value="publish"><?php _e('Đang hoạt động', 'charity-woocommerce'); ?></option>
                        <option value="draft"><?php _e('Nháp', 'charity-woocommerce'); ?></option>
                    </select>
                    <button type="button" class="button" id="filter_campaigns_btn">
                        <?php _e('Lọc', 'charity-woocommerce'); ?>
                    </button>
                </div>
                <div class="alignleft actions">
                    <button type="button" class="button" id="refresh_campaigns_btn">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Làm mới', 'charity-woocommerce'); ?>
                    </button>
                </div>
            </div>

            <!-- Bảng danh sách -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th style="width: 80px;"><?php _e('Ảnh', 'charity-woocommerce'); ?></th>
                        <th><?php _e('Tên chiến dịch', 'charity-woocommerce'); ?></th>
                        <th><?php _e('Danh mục', 'charity-woocommerce'); ?></th>
                        <th><?php _e('Mục tiêu', 'charity-woocommerce'); ?></th>
                        <th><?php _e('Đã quyên góp', 'charity-woocommerce'); ?></th>
                        <th><?php _e('Tiến độ', 'charity-woocommerce'); ?></th>
                        <th><?php _e('Trạng thái', 'charity-woocommerce'); ?></th>
                        <th><?php _e('Ngày tạo', 'charity-woocommerce'); ?></th>
                        <th style="width: 150px;"><?php _e('Hành động', 'charity-woocommerce'); ?></th>
                    </tr>
                </thead>
                <tbody id="campaigns-tbody">
                    <?php self::display_campaigns_table(); ?>
                </tbody>
            </table>

            <!-- Thống kê -->
            <div class="charity-stats-box">
                <h2><?php _e('Thống kê tổng quan', 'charity-woocommerce'); ?></h2>
                <?php self::display_campaigns_stats(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render form thêm/sửa chiến dịch
     */
    private static function render_campaign_form($campaign_id = 0) {
        $campaign = null;
        $is_edit = false;

        if ($campaign_id > 0) {
            $campaign = wc_get_product($campaign_id);
            if ($campaign && $campaign->get_meta('_is_charity_campaign') === 'yes') {
                $is_edit = true;
            } else {
                echo '<div class="notice notice-error"><p>' . __('Chiến dịch không tồn tại!', 'charity-woocommerce') . '</p></div>';
                return;
            }
        }
        ?>

        <div class="charity-campaign-form-wrapper">
            <h2><?php echo $is_edit ? __('Sửa chiến dịch', 'charity-woocommerce') : __('Thêm chiến dịch mới', 'charity-woocommerce'); ?></h2>

            <form id="charity-campaign-form" class="charity-form">
                <input type="hidden" id="campaign_id" name="campaign_id" value="<?php echo $campaign_id; ?>" />
                <input type="hidden" id="form_action" name="form_action" value="<?php echo $is_edit ? 'update' : 'create'; ?>" />

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="campaign_title"><?php _e('Tên chiến dịch', 'charity-woocommerce'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="campaign_title" name="campaign_title" class="regular-text"
                                   value="<?php echo $is_edit ? esc_attr($campaign->get_name()) : ''; ?>" required />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="campaign_description"><?php _e('Mô tả', 'charity-woocommerce'); ?></label>
                        </th>
                        <td>
                            <?php
                            $content = $is_edit ? $campaign->get_description() : '';
                            wp_editor($content, 'campaign_description', array(
                                'textarea_name' => 'campaign_description',
                                'textarea_rows' => 10,
                                'media_buttons' => true
                            ));
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="campaign_short_desc"><?php _e('Mô tả ngắn', 'charity-woocommerce'); ?></label>
                        </th>
                        <td>
                            <textarea id="campaign_short_desc" name="campaign_short_desc" class="large-text" rows="3"><?php
                                echo $is_edit ? esc_textarea($campaign->get_short_description()) : '';
                            ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="campaign_category"><?php _e('Danh mục', 'charity-woocommerce'); ?></label>
                        </th>
                        <td>
                            <?php
                            $categories = get_terms(array(
                                'taxonomy' => 'product_cat',
                                'hide_empty' => false,
                            ));
                            $selected_cats = $is_edit ? $campaign->get_category_ids() : array();
                            ?>
                            <select id="campaign_category" name="campaign_category[]" multiple style="width: 300px;">
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo $category->term_id; ?>"
                                            <?php echo in_array($category->term_id, $selected_cats) ? 'selected' : ''; ?>>
                                        <?php echo $category->name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Giữ Ctrl để chọn nhiều danh mục', 'charity-woocommerce'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="campaign_goal"><?php _e('Mục tiêu (VNĐ)', 'charity-woocommerce'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="campaign_goal" name="campaign_goal" class="regular-text"
                                   min="0" step="1000"
                                   value="<?php echo $is_edit ? $campaign->get_meta('_charity_goal') : ''; ?>" />
                        </td>
                    </tr>

                    <?php if ($is_edit) : ?>
                    <tr>
                        <th scope="row">
                            <label for="campaign_raised"><?php _e('Tiền đã quyên góp (VNĐ)', 'charity-woocommerce'); ?></label>
                        </th>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <input type="number" id="campaign_raised" name="campaign_raised" class="regular-text"
                                       min="0" step="1000"
                                       value="<?php echo $campaign->get_meta('_charity_raised'); ?>" />
                                <button type="button" class="button" id="recalculate_raised_btn"
                                        data-campaign-id="<?php echo $campaign_id; ?>">
                                    <span class="dashicons dashicons-update-alt"></span>
                                    <?php _e('Tính lại từ ủng hộ', 'charity-woocommerce'); ?>
                                </button>
                            </div>
                            <p class="description">
                                <?php _e('Bạn có thể nhập số tiền thủ công hoặc nhấn "Tính lại từ ủng hộ" để tự động tính toán dựa trên các khoản ủng hộ đã hoàn thành.', 'charity-woocommerce'); ?>
                            </p>
                            <div id="raised-calculation-info" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #007cba; display: none;">
                                <strong><?php _e('Thông tin tính toán:', 'charity-woocommerce'); ?></strong>
                                <div id="raised-details"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <tr>
                        <th scope="row">
                            <label for="campaign_status"><?php _e('Trạng thái', 'charity-woocommerce'); ?></label>
                        </th>
                        <td>
                            <select id="campaign_status" name="campaign_status">
                                <option value="publish" <?php echo ($is_edit && $campaign->get_status() === 'publish') ? 'selected' : ''; ?>>
                                    <?php _e('Đang hoạt động', 'charity-woocommerce'); ?>
                                </option>
                                <option value="draft" <?php echo ($is_edit && $campaign->get_status() === 'draft') ? 'selected' : ''; ?>>
                                    <?php _e('Nháp', 'charity-woocommerce'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label><?php _e('Ảnh đại diện', 'charity-woocommerce'); ?></label>
                        </th>
                        <td>
                            <div id="campaign_image_preview">
                                <?php if ($is_edit && $campaign->get_image_id()) : ?>
                                    <?php echo wp_get_attachment_image($campaign->get_image_id(), 'thumbnail'); ?>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" id="campaign_image_id" name="campaign_image_id"
                                   value="<?php echo $is_edit ? $campaign->get_image_id() : ''; ?>" />
                            <button type="button" class="button" id="upload_image_button">
                                <?php _e('Chọn ảnh', 'charity-woocommerce'); ?>
                            </button>
                            <button type="button" class="button" id="remove_image_button"
                                    style="<?php echo (!$is_edit || !$campaign->get_image_id()) ? 'display:none;' : ''; ?>">
                                <?php _e('Xóa ảnh', 'charity-woocommerce'); ?>
                            </button>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $is_edit ? __('Cập nhật chiến dịch', 'charity-woocommerce') : __('Tạo chiến dịch', 'charity-woocommerce'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=charity'); ?>" class="button">
                        <?php _e('Hủy', 'charity-woocommerce'); ?>
                    </a>
                </p>
            </form>

            <div id="charity-message"></div>
        </div>
        <?php
    }

    /**
     * Hiển thị bảng danh sách chiến dịch
     */
    private static function display_campaigns_table($status = '') {
        $campaigns = CharityWooCommerce::get_all_campaigns();

        if ($campaigns->have_posts()) {
            while ($campaigns->have_posts()) {
                $campaigns->the_post();
                $product = wc_get_product(get_the_ID());

                // Skip if status filter doesn't match
                if ($status && $product->get_status() !== $status) {
                    continue;
                }

                $goal = floatval($product->get_meta('_charity_goal'));
                $raised = floatval($product->get_meta('_charity_raised'));
                $progress = CharityWooCommerce::calculate_percentage($raised, $goal);

                $categories = $product->get_category_ids();
                $category_names = array();
                foreach ($categories as $cat_id) {
                    $term = get_term($cat_id, 'product_cat');
                    if ($term) {
                        $category_names[] = $term->name;
                    }
                }
                ?>
                <tr data-campaign-id="<?php echo get_the_ID(); ?>">
                    <td><?php echo get_the_ID(); ?></td>
                    <td>
                        <?php
                        if (has_post_thumbnail()) {
                            echo get_the_post_thumbnail(get_the_ID(), array(50, 50));
                        } else {
                            echo '<img src="' . wc_placeholder_img_src() . '" width="50" height="50" />';
                        }
                        ?>
                    </td>
                    <td>
                        <strong><?php the_title(); ?></strong>
                    </td>
                    <td><?php echo implode(', ', $category_names); ?></td>
                    <td><?php echo CharityWooCommerce::format_currency($goal); ?></td>
                    <td><?php echo CharityWooCommerce::format_currency($raised); ?></td>
                    <td>
                        <div class="progress-bar">
                            <div class="progress-bar-fill" style="width: <?php echo $progress; ?>%; background: <?php echo $progress >= 100 ? '#28a745' : '#007cba'; ?>;">
                                <?php echo $progress; ?>%
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php
                        $status = $product->get_status();
                        $status_text = $status == 'publish' ? __('Đang hoạt động', 'charity-woocommerce') : __('Nháp', 'charity-woocommerce');
                        $status_class = $status == 'publish' ? 'status-active' : 'status-draft';
                        ?>
                        <span class="campaign-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                    </td>
                    <td><?php echo get_the_date('d/m/Y'); ?></td>
                    <td>
                        <a href="<?php echo get_permalink(); ?>" target="_blank" class="button button-small" title="<?php _e('Xem', 'charity-woocommerce'); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=charity&action=edit&campaign_id=' . get_the_ID()); ?>"
                           class="button button-small" title="<?php _e('Sửa', 'charity-woocommerce'); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </a>
                        <button type="button" class="button button-small delete-campaign-btn"
                                data-campaign-id="<?php echo get_the_ID(); ?>"
                                title="<?php _e('Xóa', 'charity-woocommerce'); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </td>
                </tr>
                <?php
            }
            wp_reset_postdata();
        } else {
            ?>
            <tr>
                <td colspan="10" style="text-align: center;">
                    <?php _e('Chưa có chiến dịch nào.', 'charity-woocommerce'); ?>
                    <a href="<?php echo admin_url('admin.php?page=charity&action=add'); ?>">
                        <?php _e('Tạo chiến dịch mới', 'charity-woocommerce'); ?>
                    </a>
                </td>
            </tr>
            <?php
        }
    }

    /**
     * Hiển thị thống kê chiến dịch
     */
    private static function display_campaigns_stats() {
        $campaigns = CharityWooCommerce::get_all_campaigns();
        $total_campaigns = $campaigns->found_posts;
        $total_raised = 0;
        $total_goal = 0;
        $active_campaigns = 0;

        if ($campaigns->have_posts()) {
            while ($campaigns->have_posts()) {
                $campaigns->the_post();
                $product = wc_get_product(get_the_ID());
                $total_goal += floatval($product->get_meta('_charity_goal'));
                $total_raised += floatval($product->get_meta('_charity_raised'));

                if ($product->get_status() === 'publish') {
                    $active_campaigns++;
                }
            }
            wp_reset_postdata();
        }

        $overall_progress = CharityWooCommerce::calculate_percentage($total_raised, $total_goal);
        ?>
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php _e('Tổng chiến dịch', 'charity-woocommerce'); ?></h3>
                <p class="stat-value"><?php echo $total_campaigns; ?></p>
            </div>
            <div class="stat-card">
                <h3><?php _e('Đang hoạt động', 'charity-woocommerce'); ?></h3>
                <p class="stat-value"><?php echo $active_campaigns; ?></p>
            </div>
            <div class="stat-card">
                <h3><?php _e('Tổng đã quyên góp', 'charity-woocommerce'); ?></h3>
                <p class="stat-value"><?php echo CharityWooCommerce::format_currency($total_raised); ?></p>
            </div>
            <div class="stat-card">
                <h3><?php _e('Tổng mục tiêu', 'charity-woocommerce'); ?></h3>
                <p class="stat-value"><?php echo CharityWooCommerce::format_currency($total_goal); ?></p>
            </div>
            <div class="stat-card">
                <h3><?php _e('Tiến độ chung', 'charity-woocommerce'); ?></h3>
                <p class="stat-value"><?php echo $overall_progress; ?>%</p>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Tạo chiến dịch mới
     */
    public function ajax_create_campaign() {
        check_ajax_referer('charity_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Bạn không có quyền thực hiện hành động này.', 'charity-woocommerce'));
        }

        $title = sanitize_text_field($_POST['title']);
        $description = wp_kses_post($_POST['description']);
        $short_desc = sanitize_textarea_field($_POST['short_desc']);
        $categories = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : array();
        $image_id = intval($_POST['image_id']);
        $goal = floatval($_POST['goal']);
        $status = sanitize_text_field($_POST['status']);

        // Tạo sản phẩm WooCommerce
        $product = new WC_Product_Simple();
        $product->set_name($title);
        $product->set_description($description);
        $product->set_short_description($short_desc);
        $product->set_status($status);
        $product->set_catalog_visibility('visible');
        $product->set_price(0);
        $product->set_regular_price(0);
        $product->set_manage_stock(false);
        $product->set_sold_individually(true);

        if (!empty($categories)) {
            $product->set_category_ids($categories);
        }

        if ($image_id) {
            $product->set_image_id($image_id);
        }

        // Thêm meta để đánh dấu đây là chiến dịch từ thiện
        $product->add_meta_data('_is_charity_campaign', 'yes', true);
        $product->add_meta_data('_charity_goal', $goal, true);
        $product->add_meta_data('_charity_raised', 0, true);

        $product_id = $product->save();

        if ($product_id) {
            wp_send_json_success(array(
                'message' => __('Chiến dịch đã được tạo thành công!', 'charity-woocommerce'),
                'product_id' => $product_id,
                'redirect_url' => admin_url('admin.php?page=charity')
            ));
        } else {
            wp_send_json_error(__('Có lỗi xảy ra khi tạo chiến dịch.', 'charity-woocommerce'));
        }
    }

    /**
     * AJAX: Cập nhật chiến dịch
     */
    public function ajax_update_campaign() {
        check_ajax_referer('charity_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Bạn không có quyền thực hiện hành động này.', 'charity-woocommerce'));
        }

        $campaign_id = intval($_POST['campaign_id']);
        $product = wc_get_product($campaign_id);

        if (!$product || $product->get_meta('_is_charity_campaign') !== 'yes') {
            wp_send_json_error(__('Chiến dịch không tồn tại.', 'charity-woocommerce'));
        }

        $title = sanitize_text_field($_POST['title']);
        $description = wp_kses_post($_POST['description']);
        $short_desc = sanitize_textarea_field($_POST['short_desc']);
        $categories = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : array();
        $image_id = intval($_POST['image_id']);
        $goal = floatval($_POST['goal']);
        $status = sanitize_text_field($_POST['status']);

        // Cập nhật thông tin sản phẩm
        $product->set_name($title);
        $product->set_description($description);
        $product->set_short_description($short_desc);
        $product->set_status($status);

        if (!empty($categories)) {
            $product->set_category_ids($categories);
        }

        if ($image_id) {
            $product->set_image_id($image_id);
        } else {
            $product->set_image_id(0);
        }

        // Cập nhật meta
        $product->update_meta_data('_charity_goal', $goal);

        // Cập nhật số tiền đã quyên góp nếu có
        if (isset($_POST['raised'])) {
            $raised = floatval($_POST['raised']);
            $product->update_meta_data('_charity_raised', $raised);
        }

        $product->save();

        wp_send_json_success(array(
            'message' => __('Chiến dịch đã được cập nhật thành công!', 'charity-woocommerce'),
            'redirect_url' => admin_url('admin.php?page=charity')
        ));
    }

    /**
     * AJAX: Xóa chiến dịch
     */
    public function ajax_delete_campaign() {
        check_ajax_referer('charity_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Bạn không có quyền thực hiện hành động này.', 'charity-woocommerce'));
        }

        $campaign_id = intval($_POST['campaign_id']);
        $product = wc_get_product($campaign_id);

        if (!$product || $product->get_meta('_is_charity_campaign') !== 'yes') {
            wp_send_json_error(__('Chiến dịch không tồn tại.', 'charity-woocommerce'));
        }

        // Kiểm tra xem có đơn hàng nào liên quan không
        $orders = wc_get_orders(array(
            'limit' => 1,
            'meta_key' => '_charity_campaign_id',
            'meta_value' => $campaign_id,
            'meta_compare' => '='
        ));

        if (!empty($orders)) {
            wp_send_json_error(__('Không thể xóa chiến dịch đã có người ủng hộ.', 'charity-woocommerce'));
        }

        // Xóa sản phẩm
        $product->delete(true);

        wp_send_json_success(array(
            'message' => __('Chiến dịch đã được xóa thành công!', 'charity-woocommerce')
        ));
    }

    /**
     * AJAX: Lấy thông tin chiến dịch
     */
    public function ajax_get_campaign() {
        check_ajax_referer('charity_ajax_nonce', 'nonce');

        $campaign_id = intval($_POST['campaign_id']);
        $product = wc_get_product($campaign_id);

        if (!$product || $product->get_meta('_is_charity_campaign') !== 'yes') {
            wp_send_json_error(__('Chiến dịch không tồn tại.', 'charity-woocommerce'));
        }

        $data = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'categories' => $product->get_category_ids(),
            'image_id' => $product->get_image_id(),
            'image_url' => wp_get_attachment_url($product->get_image_id()),
            'goal' => $product->get_meta('_charity_goal'),
            'raised' => $product->get_meta('_charity_raised'),
            'status' => $product->get_status(),
            'url' => get_permalink($product->get_id())
        );

        wp_send_json_success($data);
    }

    /**
     * AJAX: Load danh sách chiến dịch
     */
    public function ajax_load_campaigns() {
        check_ajax_referer('charity_ajax_nonce', 'nonce');

        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        ob_start();
        self::display_campaigns_table($status);
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    /**
     * AJAX: Tính toán lại số tiền đã quyên góp từ ủng hộ
     */
    public function ajax_recalculate_raised() {
        check_ajax_referer('charity_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Bạn không có quyền thực hiện hành động này.', 'charity-woocommerce'));
        }

        $campaign_id = intval($_POST['campaign_id']);
        $product = wc_get_product($campaign_id);

        if (!$product || $product->get_meta('_is_charity_campaign') !== 'yes') {
            wp_send_json_error(__('Chiến dịch không tồn tại.', 'charity-woocommerce'));
        }

        // Lấy tất cả các khoản ủng hộ liên quan đến chiến dịch này
        $donations = wc_get_orders(array(
            'limit' => -1,
            'status' => 'completed', // Chỉ lấy các đơn đã hoàn thành
            'meta_key' => '_charity_campaign_id',
            'meta_value' => $campaign_id,
            'meta_compare' => '='
        ));

        $total_raised = 0;
        $details = array();
        $donation_count = 0;

        if (!empty($donations)) {
            foreach ($donations as $donation) {
                $amount = floatval($donation->get_total());
                $total_raised += $amount;
                $donation_count++;

                // Lưu chi tiết từng khoản ủng hộ (chỉ hiển thị 10 khoản gần nhất)
                if (count($details) < 10) {
                    $donor_name = $donation->get_billing_first_name() . ' ' . $donation->get_billing_last_name();
                    if ($donation->get_meta('_is_anonymous') === 'yes') {
                        $donor_name = __('Ẩn danh', 'charity-woocommerce');
                    }

                    $details[] = array(
                        'order_id' => $donation->get_id(),
                        'donor_name' => $donor_name,
                        'amount' => $amount,
                        'date' => $donation->get_date_created()->format('d/m/Y H:i')
                    );
                }
            }
        }

        // Cập nhật số tiền đã quyên góp vào sản phẩm
        $product->update_meta_data('_charity_raised', $total_raised);
        $product->save();

        wp_send_json_success(array(
            'message' => sprintf(__('Đã tính toán lại từ %d khoản ủng hộ hoàn thành!', 'charity-woocommerce'), $donation_count),
            'total_raised' => $total_raised,
            'donation_count' => $donation_count,
            'details' => $details
        ));
    }
}
