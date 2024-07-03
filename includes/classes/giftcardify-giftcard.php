<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

require_once 'giftcardify-giftcard-log.php';

class GiftCardify_GiftCard {
  public function __construct() {
    $this->giftcardify_giftcard_log = new GiftCardify_GiftCard_Log();
  }

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
        'status'              => 'draft',
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
        '%s',
        '%s'
      )
    );
  }

  public function use_giftcard(
    $giftcard_id,
    $product_order_id,
    $amount
  ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcards';
    $query = $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $giftcard_id );
    $giftcard = $wpdb->get_row( $query );

    if ( null === $row ) {
      echo "No gift card found with ID $giftcard_id";
    } else {
      // get balance and validate if it's enough
      if($giftcard->balance < $amount) {
        echo "Gift card balance isn't enough."
      } else {
        // create gift card usage log
        $giftcard_log_id = $this->giftcardify_giftcard_log->create_giftcard_log($giftcard_id, $product_order_id, $amount);

        // update gift card balance
        if($giftcard_log_id > 0) {
          global $wpdb;
          $table_name = $wpdb->prefix . 'giftcards';
          $data = array(
            'balance'     => $new_balance,
            'updated_at'  => date('Y-m-d H:i:s')
          );
          $where = array(
            'id'  => $giftcard_id
          );

          $wpdb->update(
            $table_name,
            $data,
            $where,
            array('%d', '%s'),
            array('%d')
          );
        }
      }
    }
  }

  public function expire_giftcard() {}

  public function get_giftcards() {}

  private function generate_gift_card_code() {
    return 'YOUR_GIFT_CARD_CODE';
  }
}