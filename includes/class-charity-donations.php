<?php
/**
 * Class quản lý Ủng hộ từ thiện
 */

if (!defined('ABSPATH')) {
    exit;
}

class CharityDonations {

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
        // AJAX handlers cho donations
        add_action('wp_ajax_charity_add_donation', array($this, 'ajax_add_donation'));
        add_action('wp_ajax_charity_update_donation', array($this, 'ajax_update_donation'));
        add_action('wp_ajax_charity_delete_donation', array($this, 'ajax_delete_donation'));
        add_action('wp_ajax_charity_get_donation', array($this, 'ajax_get_donation'));
        add_action('wp_ajax_charity_load_donations', array($this, 'ajax_load_donations'));
        add_action('wp_ajax_charity_export_donations', array($this, 'ajax_export_donations'));
    }

    /**
     * Render trang quản lý ủng hộ
     */
    public static function render_donations_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $donation_id = isset($_GET['donation_id']) ? intval($_GET['donation_id']) : 0;

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Quản lý Ủng hộ', 'charity-woocommerce'); ?></h1>

            <?php if ($action === 'list') : ?>
                <a href="<?php echo admin_url('admin.php?page=charity-donations&action=add'); ?>" class="page-title-action">
                    <?php _e('Thêm ủng hộ mới', 'charity-woocommerce'); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo admin_url('admin.php?page=charity-donations'); ?>" class="page-title-action">
                    <?php _e('Quay lại danh sách', 'charity-woocommerce'); ?>
                </a>
            <?php endif; ?>

            <hr class="wp-header-end">

            <?php
            switch ($action) {
                case 'add':
                    self::render_donation_form();
                    break;
                case 'edit':
                    self::render_donation_form($donation_id);
                    break;
                default:
                    self::render_donations_list();
                    break;
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render danh sách ủng hộ
     */
    private static function render_donations_list() {
        ?>
        <div class="charity-donations-list">
            <!-- Bộ lọc -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select id="filter_donation_campaign">
                        <option value=""><?php _e('Tất cả chiến dịch', 'charity-woocommerce'); ?></option>
                        <?php
                        $campaigns = CharityWooCommerce::get_all_campaigns();
                        if ($campaigns->have_posts()) {
                            while ($campaigns->have_posts()) {
                                $campaigns->the_post();
                                ?>
                                <option value="<?php echo get_the_ID(); ?>">
                                    <?php the_title(); ?>
                                </option>
                                <?php
                            }
                            wp_reset_postdata();
                        }
                        ?>
                    </select>

                    <select id="filter_donation_status">
                        <option value=""><?php _e('Tất cả trạng thái', 'charity-woocommerce'); ?></option>
                        <option value="completed"><?php _e('Hoàn thành', 'charity-woocommerce'); ?></option>
                        <option value="processing"><?php _e('Đang xử lý', 'charity-woocommerce'); ?></option>
                        <option value="pending"><?php _e('Chờ xử lý', 'charity-woocommerce'); ?></option>
                        <option value="cancelled"><?php _e('Đã hủy', 'charity-woocommerce'); ?></option>
                    </select>

                    <input type="date" id="filter_date_from" placeholder="<?php _e('Từ ngày', 'charity-woocommerce'); ?>" />
                    <input type="date" id="filter_date_to" placeholder="<?php _e('Đến ngày', 'charity-woocommerce'); ?>" />

                    <button type="button" class="button" id="filter_donations_btn">
                        <?php _e('Lọc', 'charity-woocommerce'); ?>
                    </button>
                    <button type="button" class="button" id="reset_filter_btn">
                        <?php _e('Xóa lọc', 'charity-woocommerce'); ?>
                    </button>
                </div>

                <div class="alignleft actions">
                    <button type="button" class="button" id="export_donations_btn">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Xuất Excel', 'charity-woocommerce'); ?>
                    </button>
                    <button type="button" class="button" id="refresh_donations_btn">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Làm mới', 'charity-woocommerce'); ?>
                    </button>
                </div>
            </div>

            <!-- Bảng danh sách -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 80px;"><?php _e('ID', 'charity-woocommerce'); ?></th>
                        <th><?php _e('Chiến dịch', 'charity-woocommerce'); ?></th>
                        <th><?php _e('Người ủng hộ', 'charity-woocommerce'); ?></th>
                        <th><?php _e('Email', 'charity-woocommerce'); ?></th>
                        <th><?php _e('Điện thoại', 'charity-woocommerce'); ?></th>
                        <th><?php _e('Số tiền', 'charity-woocommerce'); ?></th>
                        <th><?php _e('Phương thức', 'charity-woocommerce'); ?></th>
                        <th><?php _e('Trạng thái', 'charity-woocommerce'); ?></th>
                        <th><?php _e('Ngày ủng hộ', 'charity-woocommerce'); ?></th>
                        <th style="width: 150px;"><?php _e('Hành động', 'charity-woocommerce'); ?></th>
                    </tr>
                </thead>
                <tbody id="donations-tbody">
                    <?php self::display_donations_table(); ?>
                </tbody>
            </table>

            <!-- Thống kê -->
            <div class="charity-stats-box">
                <h2><?php _e('Thống kê ủng hộ', 'charity-woocommerce'); ?></h2>
                <?php self::display_donations_stats(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render form thêm/sửa ủng hộ
     */
    private static function render_donation_form($donation_id = 0) {
        $order = null;
        $is_edit = false;

        if ($donation_id > 0) {
            $order = wc_get_order($donation_id);
            if ($order && $order->get_meta('_charity_campaign_id')) {
                $is_edit = true;
            } else {
                echo '<div class="notice notice-error"><p>' . __('Đơn ủng hộ không tồn tại!', 'charity-woocommerce') . '</p></div>';
                return;
            }
        }
        ?>

        <div class="charity-donation-form-wrapper">
            <h2><?php echo $is_edit ? __('Sửa thông tin ủng hộ', 'charity-woocommerce') : __('Thêm ủng hộ mới', 'charity-woocommerce'); ?></h2>

            <form id="charity-donation-form" class="charity-form">
                <input type="hidden" id="donation_id" name="donation_id" value="<?php echo $donation_id; ?>" />
                <input type="hidden" id="form_action" name="form_action" value="<?php echo $is_edit ? 'update' : 'create'; ?>" />

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="donation_campaign"><?php _e('Chiến dịch', 'charity-woocommerce'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <select id="donation_campaign" name="donation_campaign" required <?php echo $is_edit ? 'disabled' : ''; ?>>
                                <option value=""><?php _e('-- Chọn chiến dịch --', 'charity-woocommerce'); ?></option>
                                <?php
                                $campaigns = CharityWooCommerce::get_all_campaigns();
                                $selected_campaign = $is_edit ? $order->get_meta('_charity_campaign_id') : '';

                                if ($campaigns->have_posts()) {
                                    while ($campaigns->have_posts()) {
                                        $campaigns->the_post();
                                        ?>
                                        <option value="<?php echo get_the_ID(); ?>"
                                                <?php selected($selected_campaign, get_the_ID()); ?>>
                                            <?php the_title(); ?>
                                        </option>
                                        <?php
                                    }
                                    wp_reset_postdata();
                                }
                                ?>
                            </select>
                            <?php if ($is_edit) : ?>
                                <input type="hidden" name="donation_campaign" value="<?php echo $selected_campaign; ?>" />
                                <p class="description"><?php _e('Không thể thay đổi chiến dịch sau khi đã tạo ủng hộ.', 'charity-woocommerce'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="donor_name"><?php _e('Tên người ủng hộ', 'charity-woocommerce'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="donor_name" name="donor_name" class="regular-text"
                                   value="<?php echo $is_edit ? esc_attr($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) : ''; ?>"
                                   required />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="donor_email"><?php _e('Email', 'charity-woocommerce'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="donor_email" name="donor_email" class="regular-text"
                                   value="<?php echo $is_edit ? esc_attr($order->get_billing_email()) : ''; ?>" />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="donor_phone"><?php _e('Số điện thoại', 'charity-woocommerce'); ?></label>
                        </th>
                        <td>
                            <input type="tel" id="donor_phone" name="donor_phone" class="regular-text"
                                   value="<?php echo $is_edit ? esc_attr($order->get_billing_phone()) : ''; ?>" />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="donor_address"><?php _e('Địa chỉ', 'charity-woocommerce'); ?></label>
                        </th>
                        <td>
                            <textarea id="donor_address" name="donor_address" class="large-text" rows="3"><?php
                                if ($is_edit) {
                                    echo esc_textarea($order->get_billing_address_1() . ' ' . $order->get_billing_address_2());
                                }
                            ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="donation_amount"><?php _e('Số tiền ủng hộ (VNĐ)', 'charity-woocommerce'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="number" id="donation_amount" name="donation_amount" class="regular-text"
                                   min="1000" step="1000"
                                   value="<?php echo $is_edit ? $order->get_total() : ''; ?>"
                                   required />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="donation_method"><?php _e('Phương thức ủng hộ', 'charity-woocommerce'); ?></label>
                        </th>
                        <td>
                            <select id="donation_method" name="donation_method">
                                <option value="manual" <?php echo ($is_edit && $order->get_meta('_is_manual_donation') === 'yes') ? 'selected' : ''; ?>>
                                    <?php _e('Tiền mặt', 'charity-woocommerce'); ?>
                                </option>
                                <option value="bank_transfer" <?php echo ($is_edit && $order->get_payment_method() === 'bacs') ? 'selected' : ''; ?>>
                                    <?php _e('Chuyển khoản', 'charity-woocommerce'); ?>
                                </option>
                                <option value="online" <?php echo ($is_edit && !in_array($order->get_payment_method(), ['', 'bacs'])) ? 'selected' : ''; ?>>
                                    <?php _e('Online', 'charity-woocommerce'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="donation_status"><?php _e('Trạng thái', 'charity-woocommerce'); ?></label>
                        </th>
                        <td>
                            <select id="donation_status" name="donation_status">
                                <option value="completed" <?php echo ($is_edit && $order->get_status() === 'completed') ? 'selected' : ''; ?>>
                                    <?php _e('Hoàn thành', 'charity-woocommerce'); ?>
                                </option>
                                <option value="processing" <?php echo ($is_edit && $order->get_status() === 'processing') ? 'selected' : ''; ?>>
                                    <?php _e('Đang xử lý', 'charity-woocommerce'); ?>
                                </option>
                                <option value="pending" <?php echo ($is_edit && $order->get_status() === 'pending') ? 'selected' : ''; ?>>
                                    <?php _e('Chờ xử lý', 'charity-woocommerce'); ?>
                                </option>
                                <option value="cancelled" <?php echo ($is_edit && $order->get_status() === 'cancelled') ? 'selected' : ''; ?>>
                                    <?php _e('Đã hủy', 'charity-woocommerce'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="donation_note"><?php _e('Ghi chú', 'charity-woocommerce'); ?></label>
                        </th>
                        <td>
                            <textarea id="donation_note" name="donation_note" class="large-text" rows="4"><?php
                                if ($is_edit) {
                                    echo esc_textarea($order->get_customer_note());
                                }
                            ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="is_anonymous"><?php _e('Ủng hộ ẩn danh', 'charity-woocommerce'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_anonymous" name="is_anonymous" value="1"
                                       <?php echo ($is_edit && $order->get_meta('_is_anonymous') === 'yes') ? 'checked' : ''; ?> />
                                <?php _e('Người ủng hộ muốn giữ ẩn danh', 'charity-woocommerce'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $is_edit ? __('Cập nhật ủng hộ', 'charity-woocommerce') : __('Thêm ủng hộ', 'charity-woocommerce'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=charity-donations'); ?>" class="button">
                        <?php _e('Hủy', 'charity-woocommerce'); ?>
                    </a>
                </p>
            </form>

            <div id="charity-message"></div>
        </div>
        <?php
    }

    /**
     * Hiển thị bảng danh sách ủng hộ
     */
    private static function display_donations_table($filters = array()) {
        $args = array(
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_charity_campaign_id',
                    'compare' => 'EXISTS'
                )
            )
        );

        // Apply filters
        if (!empty($filters['campaign_id'])) {
            $args['meta_query'][] = array(
                'key' => '_charity_campaign_id',
                'value' => $filters['campaign_id'],
                'compare' => '='
            );
        }

        if (!empty($filters['status'])) {
            $args['status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $args['date_created'] = '>=' . $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $args['date_created'] = '<=' . $filters['date_to'];
        }

        $orders = wc_get_orders($args);

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $campaign_id = $order->get_meta('_charity_campaign_id');
                if (!$campaign_id) continue;

                $product = wc_get_product($campaign_id);
                if (!$product) continue;

                $payment_method = $order->get_payment_method();
                $is_manual = $order->get_meta('_is_manual_donation') === 'yes';

                if ($is_manual) {
                    $method_text = __('Tiền mặt', 'charity-woocommerce');
                } elseif ($payment_method === 'bacs') {
                    $method_text = __('Chuyển khoản', 'charity-woocommerce');
                } else {
                    $method_text = __('Online', 'charity-woocommerce');
                }

                $status_text = wc_get_order_status_name($order->get_status());
                $status_class = 'status-' . $order->get_status();
                ?>
                <tr data-donation-id="<?php echo $order->get_id(); ?>">
                    <td>#<?php echo $order->get_id(); ?></td>
                    <td><?php echo $product->get_name(); ?></td>
                    <td>
                        <?php
                        if ($order->get_meta('_is_anonymous') === 'yes') {
                            echo '<em>' . __('Ẩn danh', 'charity-woocommerce') . '</em>';
                        } else {
                            echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                        }
                        ?>
                    </td>
                    <td><?php echo $order->get_billing_email(); ?></td>
                    <td><?php echo $order->get_billing_phone(); ?></td>
                    <td><?php echo CharityWooCommerce::format_currency($order->get_total()); ?></td>
                    <td><?php echo $method_text; ?></td>
                    <td>
                        <span class="donation-status <?php echo $status_class; ?>">
                            <?php echo $status_text; ?>
                        </span>
                    </td>
                    <td><?php echo $order->get_date_created()->format('d/m/Y H:i'); ?></td>
                    <td>
                        <a href="<?php echo admin_url('post.php?post=' . $order->get_id() . '&action=edit'); ?>"
                           target="_blank" class="button button-small" title="<?php _e('Xem WooCommerce', 'charity-woocommerce'); ?>">
                            <span class="dashicons dashicons-external"></span>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=charity-donations&action=edit&donation_id=' . $order->get_id()); ?>"
                           class="button button-small" title="<?php _e('Sửa', 'charity-woocommerce'); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </a>
                        <button type="button" class="button button-small delete-donation-btn"
                                data-donation-id="<?php echo $order->get_id(); ?>"
                                title="<?php _e('Xóa', 'charity-woocommerce'); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </td>
                </tr>
                <?php
            }
        } else {
            ?>
            <tr>
                <td colspan="10" style="text-align: center;">
                    <?php _e('Chưa có khoản ủng hộ nào.', 'charity-woocommerce'); ?>
                    <a href="<?php echo admin_url('admin.php?page=charity-donations&action=add'); ?>">
                        <?php _e('Thêm ủng hộ mới', 'charity-woocommerce'); ?>
                    </a>
                </td>
            </tr>
            <?php
        }
    }

    /**
     * Hiển thị thống kê ủng hộ
     */
    private static function display_donations_stats() {
        $args = array(
            'limit' => -1,
            'meta_query' => array(
                array(
                    'key' => '_charity_campaign_id',
                    'compare' => 'EXISTS'
                )
            )
        );

        $orders = wc_get_orders($args);

        $total_donations = count($orders);
        $total_amount = 0;
        $completed_donations = 0;
        $pending_donations = 0;
        $today_amount = 0;
        $month_amount = 0;

        $today = current_time('Y-m-d');
        $month_start = current_time('Y-m-01');

        foreach ($orders as $order) {
            $total_amount += $order->get_total();

            if ($order->get_status() === 'completed') {
                $completed_donations++;
            } elseif (in_array($order->get_status(), ['pending', 'processing'])) {
                $pending_donations++;
            }

            $order_date = $order->get_date_created()->format('Y-m-d');

            if ($order_date === $today) {
                $today_amount += $order->get_total();
            }

            if ($order_date >= $month_start) {
                $month_amount += $order->get_total();
            }
        }
        ?>
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php _e('Tổng số ủng hộ', 'charity-woocommerce'); ?></h3>
                <p class="stat-value"><?php echo number_format($total_donations); ?></p>
            </div>
            <div class="stat-card">
                <h3><?php _e('Tổng tiền ủng hộ', 'charity-woocommerce'); ?></h3>
                <p class="stat-value"><?php echo CharityWooCommerce::format_currency($total_amount); ?></p>
            </div>
            <div class="stat-card">
                <h3><?php _e('Ủng hộ hoàn thành', 'charity-woocommerce'); ?></h3>
                <p class="stat-value"><?php echo number_format($completed_donations); ?></p>
            </div>
            <div class="stat-card">
                <h3><?php _e('Đang chờ xử lý', 'charity-woocommerce'); ?></h3>
                <p class="stat-value"><?php echo number_format($pending_donations); ?></p>
            </div>
            <div class="stat-card">
                <h3><?php _e('Ủng hộ hôm nay', 'charity-woocommerce'); ?></h3>
                <p class="stat-value"><?php echo CharityWooCommerce::format_currency($today_amount); ?></p>
            </div>
            <div class="stat-card">
                <h3><?php _e('Ủng hộ tháng này', 'charity-woocommerce'); ?></h3>
                <p class="stat-value"><?php echo CharityWooCommerce::format_currency($month_amount); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Thêm ủng hộ mới
     */
    public function ajax_add_donation() {
        check_ajax_referer('charity_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Bạn không có quyền thực hiện hành động này.', 'charity-woocommerce'));
        }

        $campaign_id = intval($_POST['campaign_id']);
        $donor_name = sanitize_text_field($_POST['donor_name']);
        $donor_email = sanitize_email($_POST['donor_email']);
        $donor_phone = sanitize_text_field($_POST['donor_phone']);
        $donor_address = sanitize_textarea_field($_POST['donor_address']);
        $amount = floatval($_POST['amount']);
        $method = sanitize_text_field($_POST['method']);
        $status = sanitize_text_field($_POST['status']);
        $note = sanitize_textarea_field($_POST['note']);
        $is_anonymous = isset($_POST['is_anonymous']) && $_POST['is_anonymous'] == '1';

        // Lấy sản phẩm (chiến dịch)
        $product = wc_get_product($campaign_id);
        if (!$product || $product->get_meta('_is_charity_campaign') !== 'yes') {
            wp_send_json_error(__('Chiến dịch không tồn tại.', 'charity-woocommerce'));
        }

        // Tạo đơn hàng
        $order = wc_create_order();

        // Thêm sản phẩm vào đơn hàng với giá custom
        $order->add_product($product, 1, array(
            'subtotal' => $amount,
            'total' => $amount
        ));

        // Tách tên
        $name_parts = explode(' ', $donor_name, 2);
        $first_name = $name_parts[0];
        $last_name = isset($name_parts[1]) ? $name_parts[1] : '';

        // Thiết lập thông tin billing
        $order->set_billing_first_name($first_name);
        $order->set_billing_last_name($last_name);
        $order->set_billing_email($donor_email);
        $order->set_billing_phone($donor_phone);
        $order->set_billing_address_1($donor_address);

        // Thêm ghi chú nếu có
        if ($note) {
            $order->set_customer_note($note);
            $order->add_order_note('Ghi chú từ người ủng hộ: ' . $note);
        }

        // Set payment method
        if ($method === 'bank_transfer') {
            $order->set_payment_method('bacs');
            $order->set_payment_method_title('Chuyển khoản ngân hàng');
        }

        // Thêm meta để đánh dấu
        $order->add_meta_data('_charity_campaign_id', $campaign_id, true);

        if ($method === 'manual') {
            $order->add_meta_data('_is_manual_donation', 'yes', true);
        }

        if ($is_anonymous) {
            $order->add_meta_data('_is_anonymous', 'yes', true);
        }

        // Cập nhật tổng tiền
        $order->calculate_totals();

        // Đặt trạng thái
        $order->set_status($status);

        // Lưu đơn hàng
        $order_id = $order->save();

        // Cập nhật số tiền đã quyên góp cho chiến dịch nếu status là completed
        if ($status === 'completed') {
            $raised = floatval($product->get_meta('_charity_raised'));
            $product->update_meta_data('_charity_raised', $raised + $amount);
            $product->save();
        }

        if ($order_id) {
            wp_send_json_success(array(
                'message' => __('Đã thêm ủng hộ thành công!', 'charity-woocommerce'),
                'order_id' => $order_id,
                'redirect_url' => admin_url('admin.php?page=charity-donations')
            ));
        } else {
            wp_send_json_error(__('Có lỗi xảy ra khi thêm ủng hộ.', 'charity-woocommerce'));
        }
    }

    /**
     * AJAX: Cập nhật ủng hộ
     */
    public function ajax_update_donation() {
        check_ajax_referer('charity_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Bạn không có quyền thực hiện hành động này.', 'charity-woocommerce'));
        }

        $donation_id = intval($_POST['donation_id']);
        $order = wc_get_order($donation_id);

        if (!$order || !$order->get_meta('_charity_campaign_id')) {
            wp_send_json_error(__('Đơn ủng hộ không tồn tại.', 'charity-woocommerce'));
        }

        $donor_name = sanitize_text_field($_POST['donor_name']);
        $donor_email = sanitize_email($_POST['donor_email']);
        $donor_phone = sanitize_text_field($_POST['donor_phone']);
        $donor_address = sanitize_textarea_field($_POST['donor_address']);
        $amount = floatval($_POST['amount']);
        $method = sanitize_text_field($_POST['method']);
        $new_status = sanitize_text_field($_POST['status']);
        $note = sanitize_textarea_field($_POST['note']);
        $is_anonymous = isset($_POST['is_anonymous']) && $_POST['is_anonymous'] == '1';

        $old_status = $order->get_status();
        $old_amount = $order->get_total();
        $campaign_id = $order->get_meta('_charity_campaign_id');

        // Tách tên
        $name_parts = explode(' ', $donor_name, 2);
        $first_name = $name_parts[0];
        $last_name = isset($name_parts[1]) ? $name_parts[1] : '';

        // Cập nhật thông tin
        $order->set_billing_first_name($first_name);
        $order->set_billing_last_name($last_name);
        $order->set_billing_email($donor_email);
        $order->set_billing_phone($donor_phone);
        $order->set_billing_address_1($donor_address);

        // Cập nhật ghi chú
        if ($note) {
            $order->set_customer_note($note);
        }

        // Cập nhật meta
        if ($is_anonymous) {
            $order->update_meta_data('_is_anonymous', 'yes');
        } else {
            $order->delete_meta_data('_is_anonymous');
        }

        // Cập nhật payment method
        if ($method === 'manual') {
            $order->update_meta_data('_is_manual_donation', 'yes');
        } elseif ($method === 'bank_transfer') {
            $order->set_payment_method('bacs');
            $order->delete_meta_data('_is_manual_donation');
        } else {
            $order->delete_meta_data('_is_manual_donation');
        }

        // Cập nhật số tiền nếu thay đổi
        if ($amount != $old_amount) {
            // Cập nhật line items
            foreach ($order->get_items() as $item) {
                $item->set_subtotal($amount);
                $item->set_total($amount);
                $item->save();
            }
            $order->calculate_totals();
        }

        // Cập nhật trạng thái
        if ($new_status !== $old_status) {
            $order->set_status($new_status);
        }

        // Cập nhật số tiền đã quyên góp cho chiến dịch
        $product = wc_get_product($campaign_id);
        if ($product) {
            $raised = floatval($product->get_meta('_charity_raised'));

            // Xử lý thay đổi trạng thái
            if ($old_status !== $new_status) {
                // Nếu chuyển từ completed sang status khác, trừ tiền cũ
                if ($old_status === 'completed' && $new_status !== 'completed') {
                    $product->update_meta_data('_charity_raised', max(0, $raised - $old_amount));
                }
                // Nếu chuyển từ status khác sang completed, cộng tiền mới
                elseif ($old_status !== 'completed' && $new_status === 'completed') {
                    $product->update_meta_data('_charity_raised', $raised + $amount);
                }
            }
            // Xử lý thay đổi số tiền khi cả 2 đều là completed
            elseif ($old_status === 'completed' && $new_status === 'completed' && $amount != $old_amount) {
                $product->update_meta_data('_charity_raised', $raised - $old_amount + $amount);
            }

            $product->save();
        }

        $order->save();

        wp_send_json_success(array(
            'message' => __('Đã cập nhật ủng hộ thành công!', 'charity-woocommerce'),
            'redirect_url' => admin_url('admin.php?page=charity-donations')
        ));
    }

    /**
     * AJAX: Xóa ủng hộ
     */
    public function ajax_delete_donation() {
        check_ajax_referer('charity_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Bạn không có quyền thực hiện hành động này.', 'charity-woocommerce'));
        }

        $donation_id = intval($_POST['donation_id']);
        $order = wc_get_order($donation_id);

        if (!$order || !$order->get_meta('_charity_campaign_id')) {
            wp_send_json_error(__('Đơn ủng hộ không tồn tại.', 'charity-woocommerce'));
        }

        // Nếu đơn hàng đã completed, cập nhật lại số tiền quyên góp
        if ($order->get_status() === 'completed') {
            $campaign_id = $order->get_meta('_charity_campaign_id');
            $product = wc_get_product($campaign_id);

            if ($product) {
                $raised = floatval($product->get_meta('_charity_raised'));
                $order_amount = $order->get_total();
                $product->update_meta_data('_charity_raised', max(0, $raised - $order_amount));
                $product->save();
            }
        }

        // Xóa đơn hàng
        $order->delete(true);

        wp_send_json_success(array(
            'message' => __('Đã xóa ủng hộ thành công!', 'charity-woocommerce')
        ));
    }

    /**
     * AJAX: Load danh sách ủng hộ với filters
     */
    public function ajax_load_donations() {
        check_ajax_referer('charity_ajax_nonce', 'nonce');

        $filters = array(
            'campaign_id' => isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : '',
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '',
            'date_from' => isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '',
            'date_to' => isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : ''
        );

        ob_start();
        self::display_donations_table($filters);
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    /**
     * AJAX: Export donations to CSV
     */
    public function ajax_export_donations() {
        check_ajax_referer('charity_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Bạn không có quyền thực hiện hành động này.', 'charity-woocommerce'));
        }

        // Tạo CSV content
        $csv_data = array();
        $csv_data[] = array(
            'ID',
            'Chiến dịch',
            'Người ủng hộ',
            'Email',
            'Điện thoại',
            'Số tiền',
            'Phương thức',
            'Trạng thái',
            'Ngày ủng hộ',
            'Ghi chú'
        );

        $orders = wc_get_orders(array(
            'limit' => -1,
            'meta_query' => array(
                array(
                    'key' => '_charity_campaign_id',
                    'compare' => 'EXISTS'
                )
            )
        ));

        foreach ($orders as $order) {
            $campaign_id = $order->get_meta('_charity_campaign_id');
            $product = wc_get_product($campaign_id);

            if (!$product) continue;

            $csv_data[] = array(
                $order->get_id(),
                $product->get_name(),
                $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                $order->get_billing_email(),
                $order->get_billing_phone(),
                $order->get_total(),
                $order->get_payment_method_title(),
                wc_get_order_status_name($order->get_status()),
                $order->get_date_created()->format('d/m/Y H:i'),
                $order->get_customer_note()
            );
        }

        // Convert to CSV string
        $csv_string = '';
        foreach ($csv_data as $row) {
            $csv_string .= '"' . implode('","', $row) . '"' . "\n";
        }

        // Add BOM for Excel UTF-8
        $csv_string = "\xEF\xBB\xBF" . $csv_string;

        wp_send_json_success(array(
            'csv' => base64_encode($csv_string),
            'filename' => 'donations_' . date('Y-m-d_H-i-s') . '.csv'
        ));
    }
}
