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


// Plugin activation and deactivation hooks
register_activation_hook(__FILE__, 'giftcardify_activation');
register_deactivation_hook(__FILE__, 'giftcardify_deactivation');


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
}
