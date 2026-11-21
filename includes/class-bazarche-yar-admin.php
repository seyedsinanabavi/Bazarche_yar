<?php

defined('ABSPATH') || exit;

final class Bazarche_Yar_Admin {

    const OPTION            = 'bazarche_yar_settings';
    const META_PRODUCT_ID   = '_bazarche_yar_product_id';
    const META_INVENTORY_ID = '_bazarche_yar_inventory_id';
    const META_LAST_SYNC    = '_bazarche_yar_last_sync';
    const META_LAST_ERROR   = '_bazarche_yar_last_error';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('admin_post_bazarche_yar_save_mapping', [$this, 'save_mapping']);
    }

    private function defaults() {
        return [
            'base_url'        => '',
            'create_endpoint' => '/inventory',
            'update_endpoint' => '/inventory/{id}',
            'warehouse_id'    => '',
            'user_id'         => '',
            'api_key'         => '',
            'api_key_header'  => 'X-API-Key',
            'api_key_prefix'  => '',
        ];
    }

    public static function get_settings() {
        $defaults = [
            'base_url'        => '',
            'create_endpoint' => '/inventory',
            'update_endpoint' => '/inventory/{id}',
            'warehouse_id'    => '',
            'user_id'         => '',
            'api_key'         => '',
            'api_key_header'  => 'X-API-Key',
            'api_key_prefix'  => '',
        ];

        return wp_parse_args(get_option(self::OPTION, []), $defaults);
    }

    public function register_menu() {
        add_menu_page(
            'بازارچه‌یار',
            'بازارچه‌یار',
            'manage_woocommerce',
            'bazarche-yar',
            [$this, 'render_products_page'],
            'dashicons-update',
            56
        );

        add_submenu_page(
            'bazarche-yar',
            'تنظیمات بازارچه‌یار',
            'تنظیمات',
            'manage_woocommerce',
            'bazarche-yar-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting(
            'bazarche_yar_settings_group',
            self::OPTION,
            [$this, 'sanitize_settings']
        );
    }

    public function sanitize_settings($input) {
        $old   = self::get_settings();
        $input = is_array($input) ? $input : [];

        return [
            'base_url'        => esc_url_raw(untrailingslashit(trim($input['base_url'] ?? ''))),
            'create_endpoint' => sanitize_text_field($input['create_endpoint'] ?? '/inventory'),
            'update_endpoint' => sanitize_text_field($input['update_endpoint'] ?? '/inventory/{id}'),
            'warehouse_id'    => sanitize_text_field($input['warehouse_id'] ?? ''),
            'user_id'         => sanitize_text_field($input['user_id'] ?? ''),
            'api_key'         => !empty($input['api_key'])
                ? sanitize_text_field($input['api_key'])
                : $old['api_key'],
            'api_key_header'  => sanitize_text_field($input['api_key_header'] ?? 'X-API-Key'),
            'api_key_prefix'  => sanitize_text_field($input['api_key_prefix'] ?? ''),
        ];
    }

    public function enqueue_assets($hook) {
        $allowed_pages = [
            'toplevel_page_bazarche-yar',
            'bazarche-yar_page_bazarche-yar-settings',
        ];

        if (!in_array($hook, $allowed_pages, true)) {
            return;
        }

        wp_enqueue_style(
            'bazarche-yar-admin',
            BAZARCHE_YAR_URL . 'assets/css/admin.css',
            [],
            BAZARCHE_YAR_VERSION
        );
    }

    public function render_products_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        echo '<div class="wrap by-wrap">';
        echo '<header class="by-hero">';
        echo '<h1>بازارچه‌یار</h1>';
        echo '<p>همگام‌سازی موجودی محصولات ووکامرس با بازارچه</p>';
        echo '</header>';

        $this->render_notice();

        if (!class_exists('WooCommerce')) {
            echo '<div class="notice notice-error"><p>برای استفاده از افزونه، ووکامرس باید فعال باشد.</p></div>';
            echo '</div>';
            return;
        }

        $products = wc_get_products([
            'limit'   => 50,
            'status'  => ['publish', 'private'],
            'type'    => ['simple', 'variation'],
            'orderby' => 'date',
            'order'   => 'DESC',
        ]);

        echo '<section class="by-card">';
        echo '<h2>محصولات</h2>';
        echo '<p class="by-muted">شناسهٔ محصول در بازارچه را وارد و ذخیره کنید؛ سپس موجودی را همگام‌سازی کنید.</p>';

        if (empty($products)) {
            echo '<p>محصولی برای نمایش پیدا نشد.</p>';
            echo '</section></div>';
            return;
        }

        echo '<table class="widefat striped by-table">';
        echo '<thead><tr>';
        echo '<th>محصول</th>';
        echo '<th>موجودی ووکامرس</th>';
        echo '<th>شناسهٔ محصول در بازارچه</th>';
        echo '<th>وضعیت</th>';
        echo '<th>عملیات</th>';
        echo '</tr></thead><tbody>';

        foreach ($products as $product) {
            $product_id = $product->get_id();
            $remote_id  = get_post_meta($product_id, self::META_PRODUCT_ID, true);
            $inventory  = get_post_meta($product_id, self::META_INVENTORY_ID, true);
            $last_sync  = get_post_meta($product_id, self::META_LAST_SYNC, true);
            $last_error = get_post_meta($product_id, self::META_LAST_ERROR, true);
            $quantity   = $product->get_stock_quantity();

            $quantity_text = ($quantity === null)
                ? 'مدیریت‌نشده'
                : (string) $quantity;

            echo '<tr>';

            echo '<td><div class="by-product">';
            echo wp_kses_post($product->get_image([42, 42]));
            echo '<div><strong>' . esc_html($product->get_name()) . '</strong>';
            echo '<br><span class="by-muted">شناسه ووکامرس: ' . esc_html($product_id) . '</span></div>';
            echo '</div></td>';

            echo '<td>' . esc_html($quantity_text) . '</td>';

            echo '<td>';
            echo '<form class="by-inline-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="bazarche_yar_save_mapping">';
            echo '<input type="hidden" name="product_id" value="' . esc_attr($product_id) . '">';
            wp_nonce_field('bazarche_yar_mapping_' . $product_id);
            echo '<input type="text" name="remote_product_id" value="' . esc_attr($remote_id) . '" placeholder="شناسه در بازارچه">';
            echo '<button class="button">ذخیره</button>';
            echo '</form>';
            echo '</td>';

            echo '<td>';

            if ($last_error) {
                echo '<span class="by-badge by-badge-error">خطا</span>';
                echo '<br><span class="by-muted">' . esc_html($last_error) . '</span>';
            } elseif ($last_sync) {
                echo '<span class="by-badge">همگام‌شده</span>';
                echo '<br><span class="by-muted">' . esc_html($last_sync) . '</span>';
            } else {
                echo '<span class="by-muted">هنوز همگام نشده</span>';
            }

            if ($inventory) {
                echo '<br><span class="by-muted">شناسهٔ موجودی: ' . esc_html($inventory) . '</span>';
            }

            echo '</td>';

            echo '<td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="bazarche_yar_sync">';
            echo '<input type="hidden" name="product_id" value="' . esc_attr($product_id) . '">';
            wp_nonce_field('bazarche_yar_sync_' . $product_id);
            echo '<button class="button button-primary">همگام‌سازی</button>';
            echo '</form>';
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</section></div>';
    }

    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = self::get_settings();

        echo '<div class="wrap by-wrap">';
        echo '<header class="by-hero">';
        echo '<h1>تنظیمات اتصال</h1>';
        echo '<p>اطلاعات API بازارچه را وارد کنید.</p>';
        echo '</header>';

        echo '<section class="by-card">';
        echo '<form method="post" action="options.php">';
        settings_fields('bazarche_yar_settings_group');

        echo '<table class="form-table"><tbody>';

        $this->render_text_field('base_url', 'آدرس پایه API', $settings['base_url'], 'مثلاً https://api.example.com');
        $this->render_text_field('create_endpoint', 'مسیر ساخت موجودی', $settings['create_endpoint'], 'مثلاً /inventory');
        $this->render_text_field('update_endpoint', 'مسیر ویرایش موجودی', $settings['update_endpoint'], 'برای شناسه از {id} استفاده کنید');
        $this->render_text_field('warehouse_id', 'شناسهٔ انبار', $settings['warehouse_id']);
        $this->render_text_field('user_id', 'شناسهٔ کاربر API', $settings['user_id']);
        $this->render_text_field('api_key_header', 'نام هدر کلید API', $settings['api_key_header'], 'مثلاً X-API-Key یا Authorization');
        $this->render_text_field('api_key_prefix', 'پیشوند کلید API', $settings['api_key_prefix'], 'مثلاً Bearer؛ در صورت نیاز');

        $option = esc_attr(self::OPTION);

        echo '<tr><th><label for="by-api-key">کلید API</label></th><td>';
        echo '<input class="regular-text by-field" id="by-api-key" type="password" autocomplete="new-password" name="' . $option . '[api_key]" value="" placeholder="برای حفظ کلید فعلی خالی بگذارید">';
        echo '<p class="description">کلید فعلی نمایش داده نمی‌شود. با خالی گذاشتن این فیلد، کلید قبلی حفظ می‌شود.</p>';
        echo '</td></tr>';

        echo '</tbody></table>';

        submit_button('ذخیره تنظیمات');
        echo '</form>';
        echo '</section>';

        echo '<section class="by-card">';
        echo '<h2>نکته</h2>';
        echo '<p>مسیرها نسبت به آدرس پایه ساخته می‌شوند. برای نمونه، آدرس پایهٔ <code>https://api.example.com</code> و مسیر <code>/inventory</code> می‌شود <code>https://api.example.com/inventory</code>.</p>';
        echo '<p class="by-muted">نام فیلدها و روش احراز هویت باید با مستندات دقیق API بازارچه هماهنگ باشد.</p>';
        echo '</section></div>';
    }

    private function render_text_field($key, $label, $value, $placeholder = '') {
        $option = esc_attr(self::OPTION);

        echo '<tr><th><label for="by-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input class="regular-text by-field" id="by-' . esc_attr($key) . '" type="text" name="' . $option . '[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '">';
        echo '</td></tr>';
    }

    public function save_mapping() {
        $product_id = absint($_POST['product_id'] ?? 0);

        if (!$product_id || !current_user_can('edit_post', $product_id)) {
            wp_die('دسترسی غیرمجاز');
        }

        check_admin_referer('bazarche_yar_mapping_' . $product_id);

        $remote_id = sanitize_text_field(
            wp_unslash($_POST['remote_product_id'] ?? '')
        );

        update_post_meta($product_id, self::META_PRODUCT_ID, $remote_id);

        $this->redirect_with_notice('شناسهٔ محصول ذخیره شد.');
    }

    public static function redirect_with_notice($message, $type = 'success') {
        $url = add_query_arg([
            'page'      => 'bazarche-yar',
            'by_notice' => $message,
            'by_type'   => $type,
        ], admin_url('admin.php'));

        wp_safe_redirect($url);
        exit;
    }

    private function render_notice() {
        if (empty($_GET['by_notice'])) {
            return;
        }

        $type  = sanitize_key($_GET['by_type'] ?? 'success');
        $class = ($type === 'error') ? 'notice notice-error' : 'notice notice-success';

        echo '<div class="' . esc_attr($class) . ' is-dismissible"><p>';
        echo esc_html(sanitize_text_field(wp_unslash($_GET['by_notice'])));
        echo '</p></div>';
    }
}