<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

class GiftCardify_GiftCard_Log {
  public function __construct() {}

  public function create_giftcard_log(
    $gift_card_id,
    $product_order_id,
    $amount
  ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_card_logs';
    
    $result = $wpdb->insert(
      $table_name,
      array(
        'gift_card_id'       => $gift_card_id,
        'product_order_id'  => $product_order_id,
        'amount'            => $amount,
        'created_at'        => date('Y-m-d H:i:s')
      ),
      array(
        '%d',
        '%d',
        '%d',
        '%s'
      )
    );

    $insert_id = 0;

    if (false === $result) {
      $insert_id = -1;
    } else {
      $insert_id = $wpdb->insert_id;
    }

    return $insert_id;
  }

  public function get_giftcard_logs() {

  }
}