<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

require_once 'giftcardify-giftcard-log.php';

class GiftCardify_GiftCard {
  public function __construct() {
    $this->giftcardify_giftcard_log = new GiftCardify_GiftCard_Log();
    
    add_action('send_gift_card_received_email', array($this, 'send_gift_card_received_email'));
  }

  public function create_giftcard(
    $order_id,
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
        'order_id'            => $order_id,
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
        'expired_at'          => $expired_at
      ),
      array(
        '%d',
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

  public function update_status_from_draft_to_created($order_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

    $query = $wpdb->prepare(
      "UPDATE $table_name
      SET gift_card_status = 'created'
      WHERE order_id = %d
      AND gift_card_status = 'draft'",
      $order_id
    );
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
      "SELECT * FROM $table_name
      WHERE gift_card_code = %s
      AND gift_card_status NOT IN ('draft', 'expired')",
      $gift_card_code
    );
    $gift_card = $wpdb->get_row( $query );

    if ( null === $gift_card ) {
      
      error_log("No gift card found with ID $gift_card->id");
      return false;
    } else {

      // get balance and validate if it's enough
      if($gift_card->gift_card_balance < $amount) {

        error_log("Gift card balance isn't enough.");
        return false;
      } else {

        // create gift card usage log
        $giftcard_log_id = $this->giftcardify_giftcard_log->create_giftcard_log($gift_card->id, $product_order_id, $amount);

        // update gift card balance
        if($giftcard_log_id > 0) {
          global $wpdb;
          $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

          $data = array(
            'gift_card_balance'   => $gift_card->gift_card_balance - $amount,
            'gift_card_status'    => 'used'
          );

          $where = array(
            'id'  => $gift_card->id
          );

          $wpdb->update(
            $table_name,
            $data,
            $where,
            array('%d', '%s'),
            array('%d')
          );

          // Update gift card status
          $this->update_gift_card_status($gift_card_id, 'used');

          // Update sent_at if needed
          // ...
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
       WHERE gift_card_status != 'expired'
       AND DATE_ADD(shipping_at, INTERVAL 1 YEAR) <= NOW()
       AND gift_card_status NOT IN ('draft', 'expired')"
    );

    $wpdb->query($query);
  }

  public function get_giftcard($gift_card_code) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

    $query = $wpdb->prepare(
      "SELECT *
      FROM $table_name
      WHERE gift_card_code = %s
      AND gift_card_status NOT IN ('draft', 'expired')",
      $gift_card_code
    );

    $result = $wpdb->get_row($query);
    return $result;
  }

  public function send_gift_card_received_email() {
    // Schedule the event if it's not already scheduled
    if (!wp_next_scheduled('giftcardify_custom_cron_hook')) {
      wp_schedule_event(time(), 'hourly', 'giftcardify_custom_cron_hook');
    }
  }

  public function send_gift_message_emails() {
    $gift_messages = $this->get_gift_messages_to_send();

    foreach ($gift_cards as $gift_card) {
      $gift_card_id = $gift_card->id;

      $g_to = $order->get_billing_email();
      $g_subject = 'You Received a Gift Card';
      $g_placeholders = array(
        'receiver_name'    => $gift_card->receiver_firstname . ' ' . $gift_card->receiver_lastname,
        'sender_name'      => $gift_card->sender_name,
        'gift_card_code'   => $gift_card->gift_card_code,
        'gift_message'     => $gift_card->gift_message,
        'available_date'   => $gift_card->expired_at, 
        'assets_path'      => plugin_dir_url(__FILE__) . '../../'
      );

      send_gift_card_received_wp_email($g_to, $g_subject, $g_placeholders);

      // Update gift card status
      $this->update_gift_card_status($gift_card_id, 'sent');
    }

    $gift_card->expire_giftcards();
  }

  private function get_gift_messages_to_send() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

    $current_date = current_time('mysql', 0); // 0 for UTC time

    $query = $wpdb->prepare(
      "SELECT *
      FROM $table_name
      WHERE gift_card_status = 'created'
      AND sent_at IS NULL
      AND DATE(shipping_at) = DATE(%s)",
      $current_date
    );

    $result = $wpdb->get_results($query);

    return $result;
  }
    
  private function send_gift_card_received_wp_email($to, $subject, $placeholders) {
    $template_path = plugin_dir_path(__FILE__) . '../../templates/emails/gift-card-received-email.php';

    $message = get_order_created_email_template($template_path, $placeholders);
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $headers[] = 'From: Listen To Your Soul <admin@ltysoul.com>';

    wp_mail($to, $subject, $message, $headers);
  }

  private function update_gift_card_status($gift_card_id, $status) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

    $query = $wpdb->prepare(
      "UPDATE $table_name
      SET gift_card_status = %s
      WHERE gift_card_id = %d",
      $status,
      $gift_card_id
    );

    $wpdb->query($query);
  }

  private function generate_gift_card_code() {
    $gift_card_code = '';

    do {
      // If you're planning 100,000 gift cards to sell, set random maximum as 9,999,999(= 100,000 x 10e3 - 1)
      $random_number = mt_rand(1, 9999999);
      $padded_number = str_pad($random_number, 7, '0', STR_PAD_LEFT);
      $formatted_number = substr($padded_number, 0, 4) . '-' . substr($padded_number, 4);
      $temp_code = 'LTYS-' . $formatted_number;

      $gift_card_code = $temp_code;
    } while(!$this->is_unique_code($gift_card_code));

    return $gift_card_code;
  }

  private function is_unique_code($gift_card_code) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

    $query = $wpdb->prepare(
      "SELECT *
      FROM $table_name
      WHERE gift_card_code = %s",
      $gift_card_code
    );

    $result = $wpdb->get_results($query);

    return empty($result);
  }
}