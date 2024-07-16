<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

require_once 'giftcardify-gift-card-log.php';

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
    $gift_card_amount,
    $shipping_at
  ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';
    $gift_card_code = $this->generate_gift_card_code();
    $shipping_at_ = date('Y-m-d H:i:s', strtotime($shipping_at));
    $created_at = date('Y-m-d H:i:s');
    $expired_at = date('Y-m-d H:i:s', strtotime('+1 year', strtotime($shipping_at_)));

    $result = $wpdb->insert(
      $table_name,
      array(
        'receiver_firstname'  => $receiver_firstname,
        'receiver_lastname'   => $receiver_lastname,
        'receiver_email'      => $receiver_email,
        'sender_name'         => $sender_name,
        'sender_email'        => $sender_email,
        'gift_message'        => $gift_message,
        'gift_card_code'      => $gift_card_code,
        'gift_card_amount'    => $gift_card_amount,
        'gift_card_balance'   => $gift_card_amount,
        'gift_card_status'    => 'created',
        'shipping_at'         => $shipping_at_,
        'created_at'          => $created_at,
        'sent_at'             => null,
        'expired_at'          => null
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

    if($result) {
      return $gift_card_code;
    } else {
      return false;
    }
  }

  public function buy_product_with_giftcard(
    $receiver_email,
    $gift_card_code,
    $product_order_id,
    $amount
  ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

    $query = $wpdb->prepare(
      "SELECT * FROM $table WHERE receiver_email = %s AND gift_card_code = %s",
      $receiver_email, $gift_card_code
    );
    $gift_card = $wpdb->get_row( $query );

    if ( null === $gift_card ) {
      
      echo "No gift card found with ID $gift_card_id";
      return false;
    } else {

      // get balance and validate if it's enough
      if($gift_card->balance < $amount) {

        echo "Gift card balance isn't enough.";
        return false;
      } else {

        // create gift card usage log
        $giftcard_log_id = $this->giftcardify_giftcard_log->create_giftcard_log($gift_card_id, $product_order_id, $amount);

        // update gift card balance
        if($giftcard_log_id > 0) {
          global $wpdb;
          $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

          $data = array(
            'balance'   => $gift_card->balance - $amount,
            'status'    => 'used'
          );

          $where = array(
            'id'  => $gift_card_id
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

  public function expire_giftcards() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

    // Update the gift cards where shipping_at + 1 year is less than or equal to the current date
    $query = $wpdb->prepare(
      "UPDATE $table_name 
       SET gift_card_status = 'expired', expired_at = NOW()
       WHERE DATE_ADD(shipping_at, INTERVAL 1 YEAR) <= NOW() 
       AND gift_card_status != 'expired'"
    );

    $wpdb->query($query);
  }

  private function get_giftcard($receiver_email, $gift_card_code) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

    $query = $wpdb->prepare(
      "SELECT *
      FROM $table
      WHERE rereceiver_email = %s
      AND gift_card_code = %s",
      $receiver_email,
      $gift_card_code
    );

    $result = $wpdb->get_results($query);
    return $result;
  }

  public function get_giftcards() {

  }

  private function generate_gift_card_code() {
    $gift_card_code = '';

    do {
      // If you're planning 100,000 gift cards to sell, set it 9,999,999 = 100,000 x 10e3 -1
      $random_number = mt_rand(1, 9999999);
      $padded_number = str_pad($random_number, 7, '0', STR_PAD_LEFT);
      $formatted_number = substr($padded_number, 0, 4) . '-' . substr($padded_number, 4);
      $temp_code = 'LTYS-' . $formatted_number;

      $gift_card_code = $temp_code;
    } while(!$this->is_unique_code($gift_card_code))

    return $gift_card_code;
  }

  private function is_unique_code($gift_card_code) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

    $query = $wpdb->prepare(
      "SELECT *
      FROM $table
      WHERE gift_card_code = %s",
      $temp_code
    );

    $result = $wpdb->get_results($query);

    return empty($result);
  }
}