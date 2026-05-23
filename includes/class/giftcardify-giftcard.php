<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

require_once 'giftcardify-giftcard-log.php';

class GiftCardify_GiftCard {
  public $giftcardify_giftcard_log;

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
    $gift_card_amount = floatval($gift_card_amount);

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
        'gift_card_status'    => 'draft',
        'shipping_at'         => $shipping_at_,
        'created_at'          => $created_at,
        'sent_at'             => null,
        'expired_at'          => $expired_at,
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
        '%f',
        '%f',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
      )
    );

    if ($result) {
      return $gift_card_code;
    }

    return false;
  }

  public function update_status_from_draft_to_created($order_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

    return $wpdb->query(
      $wpdb->prepare(
        "UPDATE $table_name
        SET gift_card_status = 'created'
        WHERE order_id = %d
        AND gift_card_status = 'draft'",
        $order_id
      )
    );
  }

  public function buy_product_with_giftcard(
    $receiver_email,
    $gift_card_code,
    $product_order_id,
    $amount
  ) {
    return $this->redeem_gift_card($gift_card_code, $product_order_id, $amount);
  }

  /**
   * Atomically deduct balance when sufficient funds are available.
   */
  public function redeem_gift_card($gift_card_code, $product_order_id, $amount) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';
    $amount = round(floatval($amount), 2);

    if ($amount <= 0 || empty($gift_card_code)) {
      return false;
    }

    $gift_card = $this->get_giftcard($gift_card_code);

    if (null === $gift_card) {
      return false;
    }

    $updated = $wpdb->query(
      $wpdb->prepare(
        "UPDATE $table_name
        SET gift_card_balance = gift_card_balance - %f,
            gift_card_status = IF((gift_card_balance - %f) <= 0, 'used', gift_card_status)
        WHERE gift_card_code = %s
        AND gift_card_balance >= %f
        AND gift_card_status NOT IN ('draft', 'expired')",
        $amount,
        $amount,
        $gift_card_code,
        $amount
      )
    );

    if (false === $updated || 0 === $wpdb->rows_affected) {
      return false;
    }

    $this->giftcardify_giftcard_log->create_giftcard_log($gift_card->id, $product_order_id, $amount);

    return true;
  }

  public function expire_giftcards() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

    $wpdb->query(
      "UPDATE $table_name
       SET gift_card_status = 'expired', expired_at = NOW()
       WHERE gift_card_status != 'expired'
       AND DATE_ADD(shipping_at, INTERVAL 1 YEAR) <= NOW()
       AND gift_card_status NOT IN ('draft', 'expired')"
    );
  }

  public function get_giftcard($gift_card_code) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

    return $wpdb->get_row(
      $wpdb->prepare(
        "SELECT *
        FROM $table_name
        WHERE gift_card_code = %s
        AND gift_card_status NOT IN ('draft', 'expired')
        AND gift_card_balance > 0",
        $gift_card_code
      )
    );
  }

  public function send_gift_card_received_email() {
    if (!wp_next_scheduled('giftcardify_custom_cron_hook')) {
      wp_schedule_event(time(), 'daily', 'giftcardify_custom_cron_hook');
    }
  }

  public function send_gift_message_emails() {
    $gift_cards = $this->get_gift_messages_to_send();

    foreach ($gift_cards as $gift_card) {
      $g_subject = 'You Received a Gift Card';
      $g_placeholders = array(
        'receiver_name'  => $gift_card->receiver_firstname . ' ' . $gift_card->receiver_lastname,
        'sender_name'    => $gift_card->sender_name,
        'gift_card_code' => $gift_card->gift_card_code,
        'gift_message'   => $gift_card->gift_message,
        'available_date' => $gift_card->expired_at,
        'assets_path'    => plugin_dir_url(__FILE__) . '../../',
      );

      $this->send_gift_card_received_wp_email($gift_card->receiver_email, $g_subject, $g_placeholders);
      $this->mark_gift_card_sent($gift_card->id);
    }

    $this->expire_giftcards();
  }

  private function get_gift_messages_to_send() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';
    $current_date = current_time('mysql');

    return $wpdb->get_results(
      $wpdb->prepare(
        "SELECT *
        FROM $table_name
        WHERE gift_card_status = 'created'
        AND sent_at IS NULL
        AND DATE(shipping_at) <= DATE(%s)",
        $current_date
      )
    );
  }

  private function send_gift_card_received_wp_email($to, $subject, $placeholders) {
    if (!is_email($to)) {
      return;
    }

    $template_path = plugin_dir_path(__FILE__) . '../../templates/emails/gift-card-received-email.php';

    if (!function_exists('get_gift_card_received_email_template')) {
      return;
    }

    $message = get_gift_card_received_email_template($template_path, $placeholders);

    if ('' === $message) {
      return;
    }

    $headers = array('Content-Type: text/html; charset=UTF-8');
    $headers[] = 'From: Listen To Your Soul <admin@ltysoul.com>';

    wp_mail($to, $subject, $message, $headers);
  }

  private function mark_gift_card_sent($gift_card_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

    $wpdb->update(
      $table_name,
      array(
        'gift_card_status' => 'sent',
        'sent_at'          => current_time('mysql'),
      ),
      array('id' => $gift_card_id),
      array('%s', '%s'),
      array('%d')
    );
  }

  private function update_gift_card_status($gift_card_id, $status) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

    $wpdb->update(
      $table_name,
      array('gift_card_status' => $status),
      array('id' => $gift_card_id),
      array('%s'),
      array('%d')
    );
  }

  private function generate_gift_card_code() {
    $gift_card_code = '';

    do {
      $random_number = mt_rand(1, 9999999);
      $padded_number = str_pad($random_number, 7, '0', STR_PAD_LEFT);
      $formatted_number = substr($padded_number, 0, 4) . '-' . substr($padded_number, 4);
      $gift_card_code = 'LTYS-' . $formatted_number;
    } while (!$this->is_unique_code($gift_card_code));

    return $gift_card_code;
  }

  private function is_unique_code($gift_card_code) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'giftcardify_gift_cards';

    $existing = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT id FROM $table_name WHERE gift_card_code = %s LIMIT 1",
        $gift_card_code
      )
    );

    return empty($existing);
  }
}
