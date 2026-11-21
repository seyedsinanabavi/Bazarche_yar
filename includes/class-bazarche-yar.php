<?php

defined('ABSPATH') || exit;

require_once BAZARCHE_YAR_PATH . 'includes/class-bazarche-yar-admin.php';
require_once BAZARCHE_YAR_PATH . 'includes/class-bazarche-yar-api.php';

final class Bazarche_Yar {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        new Bazarche_Yar_Admin();
        new Bazarche_Yar_API();
    }
}