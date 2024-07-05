<?php
/*
Plugin Name: GiftCardify for WooCommerce
Plugin URI:
Description: This plugin provides a custom Gift card solution for the e-shops using WordPress & WooCommerce.
Version: 1.0
Author:
Author URI:
*/

// If this file is called directly, abort.
if (!defined('WPINC')) {
  die;
}


// Include require files
require_once(plugin_dir_path(__FILE__) . 'includes/classes/giftcardify-giftcard.php');


// Plugin activation and deactivation hooks
register_activation_hook(__FILE__, 'giftcardify_activation');
register_deactivation_hook(__FILE__, 'giftcardify_deactivation');

function giftcardify_activation() {
  // Activation tasks

  // Create tables
  require_once(plugin_dir_path(__FILE__) . 'database/db-setup.php');

  require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
  
  dbDelta($sql_giftcards);
  dbDelta($sql_giftcard_logs);
}

function giftcardify_deactivation() {
  // Deactivation tasks
  
  // Drop tables - use this script only in development mode
  global $wpdb;

  $table_name_giftcards = $wpdb->prefix . 'giftcards';
  $table_name_giftcard_logs = $wpdb->prefix . 'giftcard_logs';

  $sql_delete_giftcards = "DROP TABLE IF EXISTS $table_name_giftcards";
  $sql_delete_giftcard_logs = "DROP TABLE IF EXISTS $table_name_giftcard_logs";

  $wpdb->query($sql_delete_giftcards);
  $wpdb->query($sql_delete_giftcard_logs);
}


// Hook to enqueue the required scripts
function giftcardify_enqueue_uuid_script() {
  wp_enqueue_script(
    'uuid-script', 
    'https://cdnjs.cloudflare.com/ajax/libs/uuid/8.3.2/uuid.min.js', 
    array(), 
    null, 
    true
  );
}
add_action('wp_enqueue_scripts', 'giftcardify_enqueue_uuid_script');

function giftcard_pdp_script() {
  if (is_product() && get_queried_object()->post_name == 'gift-card') {
    $timestamp = Date('U');
    wp_register_script('giftcard-pdp-script', plugins_url('/assets/js/giftcard-pdp-script.js?' . $timestamp, __FILE__), array(), null, true);
    wp_enqueue_script('giftcard-pdp-script');
  }
}
add_action('wp_enqueue_scripts', 'giftcard_pdp_script');


add_action('wp_ajax_buy_gift_card', 'buy_gift_card');
add_action('wp_ajax_nopriv_buy_gift_card', 'buy_gift_card');

function buy_gift_card() {
  if(isset($_POST['post_id'], $_POST['input_amount'], $_POST['referer_title'], $_POST['form_fields'])) {
    $product_id           = intval($_POST['post_id']);
    $amount               = floatval($_POST['input_amount']);
    $title                = $_POST['referer_title'];
    $data                 = $_POST['form_fields'];

    $uuid                 = $_POST['uuid'];
    $receiver_firstname   = $data['giftcard_form_receiver_first_name'];
    $receiver_lastname    = $data['giftcard_form_receiver_last_name'];
    $receiver_email       = $data['giftcard_form_receiver_email'];
    $sender_name          = $data['giftcard_form_sender_name'];
    $sender_email         = '';
    $gift_message         = $data['giftcard_form_gift_message'];
    $amount               = intval($_POST['input_amount']);
    $shipping_at          = $data['giftcard_form_shipping_date'];
    
    $giftcard = new GiftCardify_GiftCard();

    $result = $giftcard->create_giftcard(
      $uuid,
      $receiver_firstname,
      $receiver_lastname,
      $receiver_email,
      $sender_name,
      $sender_email,
      $gift_message,
      $amount,
      $shipping_at
    );
    
    if($result) {
      wp_send_json_success($result);
    } else {
      wp_send_json_error('Buy gift card failed.');
    }

  } else {
    wp_send_json_error('Buy gift card failed.');
  }

  wp_die();
}