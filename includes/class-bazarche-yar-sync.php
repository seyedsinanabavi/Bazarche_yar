<?php
defined('ABSPATH') || exit;
final class Bazarche_Yar_Sync {
 const PRODUCT_UUID='_bazarche_yar_product_uuid', INVENTORY_UUID='_bazarche_yar_inventory_uuid', LAST_SYNC='_bazarche_yar_last_sync', LAST_ERROR='_bazarche_yar_last_error';
 private $s,$api;
 public function __construct($settings){$this->s=$settings;$this->api=new Bazarche_Yar_API($settings);}
 public function sync_product($id){
  $p=wc_get_product($id);if(!$p)return new WP_Error('missing_product','محصول ووکامرس پیدا نشد.');
  foreach(array('base_url','api_key','user_id','storage_uuid','default_category_id') as $k)if(empty($this->s[$k]))return new WP_Error('settings_missing','تنظیمات اتصال، شناسه کاربر، UUID انبار و دسته‌بندی پیش‌فرض را کامل کنید.');
  $uuid=get_post_meta($id,self::PRODUCT_UUID,true);
  if(!$uuid){
   $r=$this->api->create_product($this->product_payload($p));
   if(is_wp_error($r))return $this->fail($id,$r);
   $uuid=$this->find($r,array('uuid','product_uuid'));
   if(!$uuid)return $this->fail($id,new WP_Error('no_product_uuid','API محصول را پذیرفت اما UUID محصول در پاسخ پیدا نشد؛ برای جلوگیری از ساخت تکراری، عملیات متوقف شد.'));
   update_post_meta($id,self::PRODUCT_UUID,sanitize_text_field($uuid));
  }
  $ids=$p->is_type('variable')?$p->get_children():array($id);
  foreach($ids as $child_id){
   $item=wc_get_product($child_id);if(!$item)continue;
   $inv=get_post_meta($child_id,self::INVENTORY_UUID,true);
   $payload=$this->inventory_payload($item);
   $payload=apply_filters('bazarche_yar_inventory_payload',$payload,$item,$p,$uuid);
   $r=$inv?$this->api->update_inventory($inv,$payload):$this->api->create_inventory($uuid,$payload);
   if(is_wp_error($r))return $this->fail($child_id,$r);
   if(!$inv){$new=$this->find($r,array('inventory_uuid','uuid'));if(!$new)return $this->fail($child_id,new WP_Error('no_inventory_uuid','API موجودی را پذیرفت اما inventory_uuid برنگرداند؛ برای جلوگیری از موجودی تکراری عملیات متوقف شد.'));update_post_meta($child_id,self::INVENTORY_UUID,sanitize_text_field($new));}
   update_post_meta($child_id,self::LAST_SYNC,current_time('mysql'));delete_post_meta($child_id,self::LAST_ERROR);
  }
  update_post_meta($id,self::LAST_SYNC,current_time('mysql'));delete_post_meta($id,self::LAST_ERROR);return true;
 }
 private function product_payload($p){
  $imgs=array();$image_ids=array_unique(array_filter(array_merge(array($p->get_image_id()),$p->get_gallery_image_ids())));
  foreach($image_ids as $iid){$u=wp_get_attachment_url($iid);if($u)$imgs[]=esc_url_raw($u);}
  $data=array('user_id'=>absint($this->s['user_id']),'uid'=>(string)$p->get_id(),'category_id'=>absint($this->s['default_category_id']),'title'=>$p->get_name(),'slug'=>$p->get_slug(),'content'=>wp_strip_all_tags($p->get_description()),'images'=>$imgs,'status'=>'publish','in_stock'=>$p->is_in_stock());
  if(!empty($this->s['brand']))$data['brand']=sanitize_text_field($this->s['brand']);
  return apply_filters('bazarche_yar_product_payload',$data,$p);
 }
 private function inventory_payload($p){
  $q=$p->get_stock_quantity();$data=array('user_id'=>absint($this->s['user_id']),'storage_uuid'=>sanitize_text_field($this->s['storage_uuid']),'variables'=>array(),'price'=>(int)round((float)$p->get_price()),'count'=>null===$q?0:max(0,(int)$q),'min_sale'=>1,'max_sale'=>null===$q?1:max(1,(int)$q),'original'=>false,'used'=>false,'weight'=>(int)round((float)$p->get_weight()),'image'=>$p->get_image_id()?esc_url_raw(wp_get_attachment_url($p->get_image_id())):'' );
  if($p->is_type('variation')){foreach($p->get_variation_attributes() as $k=>$v)$data['variables'][]=array('name'=>wc_attribute_label(str_replace('attribute_','',$k)),'value'=>(string)$v);}
  return $data;
 }
 private function find($data,$keys){foreach($keys as $k){if(isset($data[$k])&&is_scalar($data[$k])&&$data[$k]!=='')return (string)$data[$k];foreach(array('data','response') as $nest)if(isset($data[$nest][$k])&&is_scalar($data[$nest][$k])&&$data[$nest][$k]!=='')return (string)$data[$nest][$k];}return '';}
 private function fail($id,$error){update_post_meta($id,self::LAST_ERROR,sanitize_text_field($error->get_error_message()));if(function_exists('wc_get_logger'))wc_get_logger()->error($error->get_error_message(),array('source'=>'bazarche-yar','product_id'=>$id));return $error;}
}