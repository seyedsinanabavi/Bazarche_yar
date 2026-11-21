<?php
/**
 * Plugin Name: بازارچه‌یار برای ووکامرس
 * Description: همگام‌سازی یک‌طرفه محصولات، متغیرها، قیمت و موجودی ووکامرس با بازارچه
 * Version: 1.1.0
 * Author: SeyedSinaNabavu
 * Text Domain: bazarche-yar
 * Requires Plugins: woocommerce
 */
defined('ABSPATH') || exit;
define('BAZARCHE_YAR_VERSION','1.1.0');
define('BAZARCHE_YAR_FILE',__FILE__);
define('BAZARCHE_YAR_PATH',plugin_dir_path(__FILE__));
define('BAZARCHE_YAR_URL',plugin_dir_url(__FILE__));
require_once BAZARCHE_YAR_PATH.'includes/class-bazarche-yar.php';
add_action('plugins_loaded',static function(){Bazarche_Yar::instance();});
