<?php
/**
 * Class xử lý hiển thị frontend cho chiến dịch từ thiện
 */

if (!defined('ABSPATH')) {
    exit;
}

class CharityFrontend {

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
        // Hiển thị thông tin chiến dịch trên trang single product
        add_action('woocommerce_single_product_summary', array($this, 'display_campaign_info'), 25);

        // Hiển thị thông tin chiến dịch trong shop loop
        add_action('woocommerce_after_shop_loop_item_title', array($this, 'display_campaign_info_in_loop'), 15);

        // Ẩn giá cho sản phẩm từ thiện
        add_filter('woocommerce_get_price_html', array($this, 'modify_price_display'), 10, 2);

        // Thay đổi text nút Add to Cart
        add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'change_add_to_cart_text'), 10, 2);
        add_filter('woocommerce_product_add_to_cart_text', array($this, 'change_add_to_cart_text'), 10, 2);

        // Thay thế form add to cart cho sản phẩm từ thiện
        add_action('woocommerce_single_product_summary', array($this, 'replace_add_to_cart_button'), 30);
        add_filter('woocommerce_product_single_add_to_cart_url', array($this, 'change_add_to_cart_url'), 10, 2);

        // Ẩn form add to cart mặc định cho sản phẩm từ thiện
        add_action('woocommerce_before_single_product', array($this, 'remove_default_add_to_cart'));

        // Đăng ký shortcode
        add_shortcode('danh_sach_ung_ho', array($this, 'donors_list_shortcode'));
    }

    /**
     * Hiển thị thông tin chiến dịch từ thiện
     */
    public function display_campaign_info() {
        global $product;

        // Kiểm tra xem có phải chiến dịch từ thiện không
        if (!$product || $product->get_meta('_is_charity_campaign') !== 'yes') {
            return;
        }

        $goal = floatval($product->get_meta('_charity_goal'));
        $raised = floatval($product->get_meta('_charity_raised'));
        $progress = $goal > 0 ? min(round(($raised / $goal) * 100, 2), 100) : 0;

        // Hiển thị mô tả ngắn nếu có
        $short_description = $product->get_short_description();

        ?>
        <div class="charity-campaign-info" style="margin: 20px 0; padding: 15px; background: #f7f7f7; border-left: 4px solid #007cba;">
            <?php if (!empty($short_description)) : ?>
                <div style="margin-bottom: 15px;">
                    <?php echo wpautop($short_description); ?>
                </div>
            <?php endif; ?>

            <p style="margin: 5px 0;">
                <strong>Mục tiêu:</strong> <?php echo number_format($goal, 0, ',', '.'); ?>đ
            </p>
            <p style="margin: 5px 0;">
                <strong>Số tiền đã quyên góp:</strong> <?php echo number_format($raised, 0, ',', '.'); ?>đ
            </p>
            <p style="margin: 5px 0;">
                <strong>Tỉ lệ:</strong> <?php echo $progress; ?>%
            </p>
        </div>
        <?php
    }

    /**
     * Hiển thị thông tin chiến dịch từ thiện trong shop loop
     */
    public function display_campaign_info_in_loop() {
        global $product;

        // Kiểm tra xem có phải chiến dịch từ thiện không
        if (!$product || $product->get_meta('_is_charity_campaign') !== 'yes') {
            return;
        }

        $goal = floatval($product->get_meta('_charity_goal'));
        $raised = floatval($product->get_meta('_charity_raised'));
        $progress = $goal > 0 ? min(round(($raised / $goal) * 100, 2), 100) : 0;

        ?>
        <div class="charity-campaign-info-loop" style="margin: 10px 0;">
            <!-- Progress bar -->
            <div class="charity-progress-bar" style="background: #e9ecef; border-radius: 4px; overflow: hidden; height: 8px; margin-bottom: 8px;">
                <div class="charity-progress" style="background: #da0055; height: 100%; width: <?php echo $progress; ?>%; transition: width 0.3s ease;"></div>
            </div>

            <!-- Số tiền và phần trăm song song -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                <span style="font-weight: bold; color: #da0055; font-size: 14px;">
                    <?php echo number_format($raised, 0, ',', '.'); ?>đ
                </span>
                <span style="font-weight: bold; color: #007cba; font-size: 14px;">
                    <?php echo $progress; ?>%
                </span>
            </div>

            <!-- Mục tiêu -->
            <div style="font-size: 12px; color: #666;">
                với mục tiêu: <?php echo number_format($goal, 0, ',', '.'); ?>đ
            </div>
        </div>
        <?php
    }

    /**
     * Remove default add to cart form cho sản phẩm từ thiện
     */
    public function remove_default_add_to_cart() {
        global $product;

        if ($product && $product->get_meta('_is_charity_campaign') === 'yes') {
            // Remove default add to cart form
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        }
    }

    /**
     * Thay thế nút add to cart bằng link đến trang ủng hộ
     */
    public function replace_add_to_cart_button() {
        global $product;

        if (!$product || $product->get_meta('_is_charity_campaign') !== 'yes') {
            return;
        }

        // Tạo URL với parameter campaign_id
        $donate_url = home_url('/ung-ho-ngay') . '?campaign_id=' . $product->get_id();

        ?>
        <div class="charity-donate-button-wrapper" style="margin: 20px 0;">
            <a href="<?php echo esc_url($donate_url); ?>"
               class="button alt charity-donate-button"
               style="background: #007cba; color: white; padding: 6px 30px; font-size: 16px; border-radius: 5px; display: inline-block; text-decoration: none;">
                Ủng hộ ngay
            </a>
        </div>
        <?php
    }

    /**
     * Thay đổi URL của nút add to cart trong shop/archive pages
     */
    public function change_add_to_cart_url($url, $product) {
        if ($product->get_meta('_is_charity_campaign') === 'yes') {
            return home_url('/ung-ho-ngay') . '?campaign_id=' . $product->get_id();
        }
        return $url;
    }

    /**
     * Thay đổi text hiển thị giá
     */
    public function modify_price_display($price, $product) {
        if ($product->get_meta('_is_charity_campaign') === 'yes') {
            return '<span class="charity-price"></span>';
        }
        return $price;
    }

    /**
     * Thay đổi text nút Add to Cart
     */
    public function change_add_to_cart_text($text, $product) {
        if ($product->get_meta('_is_charity_campaign') === 'yes') {
            return 'Ủng hộ ngay';
        }
        return $text;
    }

    /**
     * Shortcode hiển thị danh sách người ủng hộ
     * Sử dụng: [danh_sach_ung_ho limit="10" show_anonymous="yes"]
     */
    public function donors_list_shortcode($atts) {
        // Lấy current product ID
        global $product;

        // Nếu không có product ID từ global, cố gắng lấy từ query
        if (!$product) {
            $product_id = get_the_ID();
            $product = wc_get_product($product_id);
        }

        // Kiểm tra xem có phải là chiến dịch từ thiện không
        if (!$product || $product->get_meta('_is_charity_campaign') !== 'yes') {
            return '<p>Đây không phải là chiến dịch từ thiện.</p>';
        }

        // Parse attributes
        $atts = shortcode_atts(array(
            'limit' => 10,
            'show_anonymous' => 'yes',
            'show_date' => 'yes',
            'show_amount' => 'yes',
            'order' => 'DESC'
        ), $atts);

        $campaign_id = $product->get_id();

        // Lấy danh sách orders
        $orders = wc_get_orders(array(
            'limit' => intval($atts['limit']),
            'status' => 'completed',
            'meta_key' => '_charity_campaign_id',
            'meta_value' => $campaign_id,
            'orderby' => 'date',
            'order' => $atts['order']
        ));

        if (empty($orders)) {
            return '<p>Chưa có người ủng hộ nào.</p>';
        }

        // Bắt đầu output
        ob_start();
        ?>
        <div class="charity-donors-list" style="margin: 20px 0;">
            <h3 style="margin-bottom: 15px;">Danh sách người ủng hộ</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">STT</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Người ủng hộ</th>
                        <?php if ($atts['show_amount'] === 'yes') : ?>
                            <th style="padding: 10px; text-align: right; border-bottom: 2px solid #ddd;">Số tiền</th>
                        <?php endif; ?>
                        <?php if ($atts['show_date'] === 'yes') : ?>
                            <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Ngày ủng hộ</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stt = 1;
                    foreach ($orders as $order) :
                        $is_anonymous = $order->get_meta('_is_anonymous') === 'yes';

                        // Skip anonymous donors if not showing
                        if ($is_anonymous && $atts['show_anonymous'] !== 'yes') {
                            continue;
                        }

                        if ($is_anonymous) {
                            $donor_name = 'Nhà hảo tâm ẩn danh';
                        } else {
                            $first_name = $order->get_billing_first_name();
                            $last_name = $order->get_billing_last_name();
                            // Hiển thị tên đầy đủ nhưng che bớt họ
                            $donor_name = $first_name . ' ' . mb_substr($last_name, 0, 1) . '.';
                        }

                        $amount = $order->get_total();
                        $date = $order->get_date_created();
                    ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px;"><?php echo $stt++; ?></td>
                            <td style="padding: 10px;">
                                <?php echo esc_html($donor_name); ?>
                                <?php if ($is_anonymous) : ?>
                                    <span style="color: #999; font-size: 12px;">(Ẩn danh)</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($atts['show_amount'] === 'yes') : ?>
                                <td style="padding: 10px; text-align: right; font-weight: bold; color: #28a745;">
                                    <?php echo number_format($amount, 0, ',', '.'); ?>đ
                                </td>
                            <?php endif; ?>
                            <?php if ($atts['show_date'] === 'yes') : ?>
                                <td style="padding: 10px; color: #666;">
                                    <?php echo $date->format('d/m/Y H:i'); ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f5f5f5; font-weight: bold;">
                        <td colspan="2" style="padding: 10px;">
                            Tổng cộng: <?php echo count($orders); ?> lượt ủng hộ
                        </td>
                        <?php if ($atts['show_amount'] === 'yes') : ?>
                            <td style="padding: 10px; text-align: right; color: #007cba;">
                                <?php
                                $total = array_sum(array_map(function($order) {
                                    return $order->get_total();
                                }, $orders));
                                echo number_format($total, 0, ',', '.');
                                ?>đ
                            </td>
                        <?php endif; ?>
                        <?php if ($atts['show_date'] === 'yes') : ?>
                            <td style="padding: 10px;"></td>
                        <?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}
