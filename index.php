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


/**
 * Include require files
 */
require_once(plugin_dir_path(__FILE__) . 'templates/giftcard-template.php');


/**
 * Plugin activation hooks
 */
register_activation_hook(__FILE__, 'giftcardify_activation');

function giftcardify_activation() {}


/**
 * Plugin deactivation hooks
 */
register_deactivation_hook(__FILE__, 'giftcardify_deactivation');

function giftcardify_deactivation() {}

