<?php
defined('ABSPATH') || exit;
final class Bazarche_Yar_Cron {
 const HOOK='bazarche_yar_sync_queued_product';
 public function __construct(){add_action(self::HOOK,array($this,'process'));add_action('woocommerce_update_product',array($this,'queue'),30);add_action('woocommerce_product_set_stock',array($this,'queue_object'),30);add_action('woocommerce_variation_set_stock',array($this,'queue_object'),30);}
 public function queue_object($p){if(is_object($p)&&method_exists($p,'get_id'))$this->queue($p->get_id());}
 public function queue($id){$s=Bazarche_Yar_Admin::get_settings();if(empty($s['auto_sync'])||!class_exists('WooCommerce'))return;$p=wc_get_product($id);if(!$p)return;if($p->is_type('variation'))$id=$p->get_parent_id()?:$id;if(function_exists('as_enqueue_async_action'))as_enqueue_async_action(self::HOOK,array(absint($id)),'bazarche-yar');elseif(!wp_next_scheduled(self::HOOK,array(absint($id))))wp_schedule_single_event(time()+20,self::HOOK,array(absint($id)));}
 public function process($id){if(!class_exists('WooCommerce'))return;$r=(new Bazarche_Yar_Sync(Bazarche_Yar_Admin::get_settings()))->sync_product(absint($id));if(is_wp_error($r)&&function_exists('wc_get_logger'))wc_get_logger()->error($r->get_error_message(),array('source'=>'bazarche-yar'));}
}