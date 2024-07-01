<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

class GiftCardify_GiftCard_Log {
  public function __construct() {}

  public function create_giftcard_log(
    $giftcard_id,
    $product_order_id
  ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcard_logs';
    $created_at = date('Y-m-d H:i:s');
    
    $wpdb->insert(
      $table_name,
      array(
        'giftcard_id'       => $giftcard_id,
        'product_order_id'  => $product_order_id,
        'status'            => 'draft',
        'created_at'        => $created_at,
        'updated_at'        => $created_at
      ),
      array(
        '%d',
        '%d',
        '%s',
        '%s',
        '%s'
      )
    );
  }

  public function udpate_status(
    $giftcard_log_id,
    $new_status
  ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcard_logs';
    $data = array(
      'status'     => $new_status,
      'updated_at' => date('Y-m-d H:i:s')
    );
    $where = array(
      'id' => $giftcard_log_id
    )

    $wpdb->update(
      $table_name,
      $data,
      $where,
      array('%s', '%s'),
      array('%d')
    )
  }

  public function get_giftcard_logs() {}
}