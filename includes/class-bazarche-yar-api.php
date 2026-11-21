<?php

defined('ABSPATH') || exit;

final class Bazarche_Yar_API {

    const META_PRODUCT_ID   = '_bazarche_yar_product_id';
    const META_INVENTORY_ID = '_bazarche_yar_inventory_id';
    const META_LAST_SYNC    = '_bazarche_yar_last_sync';
    const META_LAST_ERROR   = '_bazarche_yar_last_error';

    public function __construct() {
        add_action('admin_post_bazarche_yar_sync', [$this, 'sync_product']);
    }

    public function sync_product() {
        $product_id = absint($_POST['product_id'] ?? 0);

        if (!$product_id || !current_user_can('edit_post', $product_id)) {
            wp_die('دسترسی غیرمجاز');
        }

        check_admin_referer('bazarche_yar_sync_' . $product_id);

        if (!class_exists('WooCommerce')) {
            $this->fail($product_id, 'ووکامرس فعال نیست.');
        }

        $product = wc_get_product($product_id);

        if (!$product) {
            $this->fail($product_id, 'محصول پیدا نشد.');
        }

        $settings        = Bazarche_Yar_Admin::get_settings();
        $remote_product  = get_post_meta($product_id, self::META_PRODUCT_ID, true);
        $inventory_id    = get_post_meta($product_id, self::META_INVENTORY_ID, true);

        if (empty($settings['base_url']) || empty($settings['api_key'])) {
            $this->fail($product_id, 'آدرس API یا کلید API تنظیم نشده است.');
        }

        if (empty($settings['warehouse_id']) || empty($remote_product)) {
            $this->fail($product_id, 'شناسهٔ انبار یا شناسهٔ محصول در بازارچه وارد نشده است.');
        }

        $is_update = !empty($inventory_id);

        if ($is_update) {
            $endpoint = str_replace(
                '{id}',
                rawurlencode($inventory_id),
                $settings['update_endpoint']
            );
            $method = 'PUT';
        } else {
            $endpoint = $settings['create_endpoint'];
            $method   = 'POST';
        }

        $url = untrailingslashit($settings['base_url']) . '/' . ltrim($endpoint, '/');

        $quantity = $product->get_stock_quantity();
        $image_id = $product->get_image_id();

        // این فیلدها را در صورت تفاوت با مستندات API خودتان تنظیم کنید.
        $payload = [
            'user_id'      => $settings['user_id'],
            'warehouse_id' => $settings['warehouse_id'],
            'product_id'   => $remote_product,
            'price'        => (float) $product->get_price(),
            'quantity'     => ($quantity === null) ? 0 : (int) $quantity,
            'weight'       => (float) $product->get_weight(),
            'status'       => $product->get_stock_status(),
            'image'        => $image_id ? wp_get_attachment_url($image_id) : '',
        ];

        $header_name  = $settings['api_key_header'];
        $header_value = trim($settings['api_key_prefix'] . ' ' . $settings['api_key']);

        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if (!empty($header_name)) {
            $headers[$header_name] = $header_value;
        }

        $response = wp_remote_request($url, [
            'method'  => $method,
            'timeout' => 25,
            'headers' => $headers,
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            $this->fail($product_id, $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);
        $data   = json_decode($body, true);

        if ($status < 200 || $status >= 300) {
            $message = 'پاسخ API: HTTP ' . $status;

            if (is_array($data) && !empty($data['message'])) {
                $message .= ' — ' . sanitize_text_field($data['message']);
            }

            $this->fail($product_id, $message);
        }

        $new_inventory_id = $this->find_inventory_id($data);

        if ($new_inventory_id) {
            update_post_meta(
                $product_id,
                self::META_INVENTORY_ID,
                sanitize_text_field($new_inventory_id)
            );
        }

        update_post_meta($product_id, self::META_LAST_SYNC, current_time('mysql'));
        delete_post_meta($product_id, self::META_LAST_ERROR);

        Bazarche_Yar_Admin::redirect_with_notice('موجودی با موفقیت همگام‌سازی شد.');
    }

    private function find_inventory_id($data) {
        if (!is_array($data)) {
            return '';
        }

        $possible_ids = [
            $data['inventory_id'] ?? '',
            $data['id'] ?? '',
            $data['data']['inventory_id'] ?? '',
            $data['data']['id'] ?? '',
        ];

        foreach ($possible_ids as $id) {
            if (is_scalar($id) && (string) $id !== '') {
                return (string) $id;
            }
        }

        return '';
    }

    private function fail($product_id, $message) {
        update_post_meta(
            $product_id,
            self::META_LAST_ERROR,
            sanitize_text_field($message)
        );

        Bazarche_Yar_Admin::redirect_with_notice($message, 'error');
    }
}