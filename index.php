<?php
/*
Plugin Name: GiftCardify for WooCommerce
Plugin URI:
Description: This plugin provides a custom Gift card solution for the e-shops using WordPress & WooCommerce.
Version: 1.0
Author:
Author URI:
*/

if (!defined('WPINC')) {
  die;
}

/**
 * Include require files
 */


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


/** 
 * Register Gift Card Product Type
 */
add_action('init', 'register_gift_card_product_type');

function register_gift_card_product_type() {
  class WC_Product_Gift_Card extends WC_Product {
    public function __construct($product) {
      $this->product_type = 'gift_card';
      parent::__construct($product);
    }
  }
}


/** 
 * Add Gift Card to Product Types
 */
add_filter('product_type_selector', 'add_gift_card_product_type');

function add_gift_card_product_type($types) {
  $types['gift_card'] = __('Gift Card', 'giftcardify_for_woocommerce');
  return $types;
}


/** 
 * Extend Product Data Tabs
 */
add_filter('woocommerce_product_data_tabs', 'add_gift_card_product_tab');

function add_gift_card_product_tab($tabs) {
  $tabs['gift_card'] = array(
    'label' => __('Gift Card', 'giftcardify_for_woocommerce'),
    'target' => 'gift_card_options',
    'class' => array('show_if_gift_card'),
    'priority' => 21,
  );
  return $tabs;
}

add_action('woocommerce_product_data_panels', 'add_gift_card_product_tab_content');

function add_gift_card_product_tab_content() {
  echo '<div id="gift_card_options" class="panel woocommerce_options_panel">';
  echo '<div class="options_group">';
  woocommerce_wp_text_input(array(
    'id' => '_gift_card_values',
    'label' => __('Gift Card Values', 'giftcardify_for_woocommerce'),
    'description' => __('Comma-separated list of values (e.g. 100, 250, 500)', 'giftcardify_for_woocommerce'),
    'desc_tip' => 'true',
    'type' => 'text',
  ));
  echo '</div>';
  echo '</div>';
}

// Save the custom fields
add_action('woocommerce_process_product_meta_gift_card', 'save_gift_card_product_settings');

function save_gift_card_product_settings($post_id) {
  $gift_card_values = isset($_POST['_gift_card_values']) ? sanitize_text_field($_POST['_gift_card_values']) : '';
  update_post_meta($post_id, '_gift_card_values', $gift_card_values);
}


/** 
 * Load Custom Templates
 */
add_filter('template_include', 'load_custom_gift_card_template', 99);

function load_custom_gift_card_template($template) {
  if (is_singular('product')) {
    global $post;
    $product = wc_get_product($post->ID);

    if ($product && $product->get_type() == 'gift_card') {
      $custom_template = plugin_dir_path(__FILE__) . 'templates/single-product-gift_card.php';
      
      if (file_exists($custom_template)) {
        return $custom_template;
      } else {
        error_log('Custom gift card template does not exist: ' . $custom_template);
      }
    }
  }
  return $template;
}


/** 
 * Add Custom Pricing Logic
 */

add_filter('woocommerce_get_price_html', 'custom_gift_card_price_html', 10, 2);

function custom_gift_card_price_html($price, $product) {
  if ($product->get_type() == 'gift_card') {
    $values = get_post_meta($product->get_id(), '_gift_card_values', true);
    if ($values) {
      $values_array = explode(',', $values);
      sort($values_array);
      $price = __('Available Values: ', 'giftcardify_for_woocommerce') . implode(', ', $values_array);
    }
  }
  return $price;
}
 
add_filter('woocommerce_add_cart_item_data', 'add_custom_price_to_cart_item', 10, 2);

function add_custom_price_to_cart_item($cart_item_data, $product_id) {
  if (isset($_POST['custom_gift_card_price'])) {
    $cart_item_data['custom_gift_card_price'] = sanitize_text_field($_POST['custom_gift_card_price']);
  }
  return $cart_item_data;
}
