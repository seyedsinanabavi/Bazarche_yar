<?php
defined('ABSPATH') || exit;
final class Bazarche_Yar_API {
 private $s;
 public function __construct($settings){$this->s=$settings;}
 public function request($method,$path,$payload=null){
  $url=untrailingslashit($this->s['base_url']).'/'.ltrim($path,'/');
  $args=array('method'=>$method,'timeout'=>30,'headers'=>array('Accept'=>'application/json','X-API-KEY'=>trim($this->s['api_key'])));
  if(null!==$payload){$args['headers']['Content-Type']='application/json';$args['body']=wp_json_encode($payload);}
  $r=wp_remote_request($url,$args); if(is_wp_error($r))return $r;
  $code=(int)wp_remote_retrieve_response_code($r);$data=json_decode(wp_remote_retrieve_body($r),true);
  if($code<200||$code>=300)return new WP_Error('bazarche_http','خطای API بازارچه HTTP '.$code.(is_array($data)&&!empty($data['message'])?': '.sanitize_text_field($data['message']):''),array('status'=>$code,'response'=>$data));
  if(!is_array($data))return new WP_Error('bazarche_json','پاسخ API معتبر و JSON نیست.');
  if(isset($data['success'])&&false===$data['success'])return new WP_Error('bazarche_rejected',!empty($data['message'])?sanitize_text_field($data['message']):'API عملیات را نپذیرفت.');
  return $data;
 }
 public function create_product($p){return $this->request('POST','/api/v1/products/create/',$p);}
 public function storage_products($uuid,$page=1,$per=50){return $this->request('GET','/api/v1/storages/products/'.rawurlencode($uuid).'/?page='.absint($page).'&per_page='.absint($per));}
 public function product_inventories($uuid,$page=1,$per=50){return $this->request('GET','/api/v1/inventories/product/'.rawurlencode($uuid).'/?page='.absint($page).'&per_page='.absint($per));}
 public function create_inventory($uuid,$p){return $this->request('POST','/api/v1/inventories/product/'.rawurlencode($uuid).'/create/',$p);}
 public function update_inventory($uuid,$p){return $this->request('PUT','/api/v1/inventories/update/'.rawurlencode($uuid).'/',$p);}
}