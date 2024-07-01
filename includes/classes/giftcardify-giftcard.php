<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

class GiftCardify_GiftCard {
  public function __construct() {}

  public function create_giftcard(
    $receiver_firstname,
    $receiver_lastname,
    $receiver_email,
    $sender_name,
    $sender_email,
    $gift_message,
    $amount,
    $shipping_at
  ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcards';
    $code = $this->generate_gift_card_code();
    $shipping_at_ = date('Y-m-d H:i:s', strtotime($shipping_at));
    $created_at = date('Y-m-d H:i:s');
    $expired_at = date('Y-m-d H:i:s', strtotime('+1 year', strtotime($shipping_at_)));

    $wpdb->insert(
      $table_name,
      array(
        'receiver_firstname'  => $receiver_firstname,
        'receiver_lastname'   => $receiver_lastname,
        'receiver_email'      => $receiver_email,
        'sender_name'         => $sender_name,
        'sender_email'        => $sender_email,
        'gift_message'        => $gift_message,
        'code'                => $code,
        'amount'              => $amount,
        'balance'             => $amount,
        'shipping_at'         => $shipping_at_,
        'created_at'          => $created_at,
        'updated_at'          => $created_at,
        'expired_at'          => $expired_at
      ),
      array(
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%d',
        '%d',
        '%s',
        '%s',
        '%s',
        '%s'
      )
    );
  }

  public function use_giftcard() {}

  public function expire_giftcard() {}

  public function get_giftcards() {}

  private function generate_gift_card_code() {
    return 'YOUR_GIFT_CARD_CODE';
  }
}