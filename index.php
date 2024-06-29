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
