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
 * Display preset gift card prices to single page gift card template
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


/** 
 * Add gift card to cart - Ajax
 */
add_action('wp_ajax_add_gift_card_to_cart', 'add_gift_card_to_cart');
add_action('wp_ajax_nopriv_add_gift_card_to_cart', 'add_gift_card_to_cart');

function add_gift_card_to_cart() {
  // Verify nonce for security
  check_ajax_referer('gift_card_nonce', 'security');

  // Initialize response array
  $response = array('success' => false, 'message' => '');

  // Ensure WooCommerce session is started
  if (!WC()->session) {
    WC()->session = new WC_Session_Handler();
    WC()->session->init();
  }

  // Retrieve and sanitize POST data
  $product_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
  $product_type = isset($_POST['product_type']) ? sanitize_text_field($_POST['product_type']) : '';
  $gift_card_value = isset($_POST['gift_card_value']) ? sanitize_text_field($_POST['gift_card_value']) : '';
  $custom_amount = isset($_POST['custom_gift_card_price']) ? floatval($_POST['custom_gift_card_price']) : 100;
  $gift_card_value_final = $gift_card_value === 'custom_amount' ? $custom_amount : $gift_card_value;
  $receiver_firstname = isset($_POST['receiver_firstname']) ? sanitize_text_field($_POST['receiver_firstname']) : '';
  $receiver_lastname = isset($_POST['receiver_lastname']) ? sanitize_text_field($_POST['receiver_lastname']) : '';
  $receiver_email = isset($_POST['receiver_email']) ? sanitize_email($_POST['receiver_email']) : '';
  $sender_name = isset($_POST['sender_name']) ? sanitize_text_field($_POST['sender_name']) : '';
  $gift_message = isset($_POST['gift_message']) ? sanitize_textarea_field($_POST['gift_message']) : '';
  $shipping_date = isset($_POST['shipping_date']) ? sanitize_text_field($_POST['shipping_date']) : '';

  // Validate product ID
  if ($product_id <= 0) {
    $response['message'] = __('Invalid product ID.', 'textdomain');
    wp_send_json($response);
  }

  // Validate product type (if necessary)
  if ($product_type !== 'gift_card') {
    $response['message'] = __('Invalid product type.', 'textdomain');
    wp_send_json($response);
  }

  // Validate gift card value
  if (empty($gift_card_value)) {
    $response['message'] = __('Gift card value is required.', 'textdomain');
    wp_send_json($response);
  }

  // Validate receiver first name
  if (empty($receiver_firstname)) {
    $response['message'] = __('Receiver first name is required.', 'textdomain');
    wp_send_json($response);
  }

  // Validate receiver last name
  if (empty($receiver_lastname)) {
    $response['message'] = __('Receiver last name is required.', 'textdomain');
    wp_send_json($response);
  }

  // Validate receiver email
  if (empty($receiver_email)) {
    $response['message'] = __('Receiver email is required.', 'textdomain');
    wp_send_json($response);
  } elseif (!is_email($receiver_email)) {
    $response['message'] = __('Invalid receiver email format.', 'textdomain');
    wp_send_json($response);
  }

  // Optionally, validate sender name, gift message, and shipping date as needed

  // Prepare custom cart item data
  $cart_item_data = array(
    'gift_card_value' => $gift_card_value_final,
    'receiver_firstname' => $receiver_firstname,
    'receiver_lastname' => $receiver_lastname,
    'receiver_email' => $receiver_email,
    'sender_name' => $sender_name,
    'gift_message' => $gift_message,
    'shipping_date' => $shipping_date,
  );

  // Check if product is purchasable
  $product = wc_get_product($product_id);

  // $product->set_regular_price('100'); // You can set a default or minimum value

  // Mark as virtual and downloadable
  $product->set_virtual(true);

  // Set stock status
  $product->set_manage_stock(false);
  $product->set_stock_status('instock');

  // Set SKU
  $product->set_sku('GFT100');

  // Save the product
  $product->save();

  if (!$product->is_purchasable()) {
    error_log('Product is not purchasable: ' . $product_id);
    $response['message'] = __('This product cannot be purchased.', 'textdomain');
    wp_send_json($response);
  }

  // Add product to cart
  $cart_item_key = WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);

  if ($cart_item_key) {
    $response['success'] = true;
    $response['message'] = __('Gift card added to cart successfully.', 'textdomain');
  } else {
    $response['success'] = false;
    $response['message'] = __('Failed to add gift card to cart.', 'textdomain');
    // Log error details
    error_log('Failed to add to cart. Product ID: ' . $product_id);
    error_log('Cart item data: ' . print_r($cart_item_data, true));
    error_log('Cart contents: ' . print_r(WC()->cart->get_cart(), true));
  }

  wp_send_json($response);
  wp_die();
}


/** 
 * Replace the price with the gift card value
 */
add_filter('woocommerce_cart_item_price', 'custom_display_gift_card_price', 10, 3);

function custom_display_gift_card_price($price, $cart_item, $cart_item_key) {
  if (isset($cart_item['gift_card_value'])) {
    // Replace the price with the gift card value
    $gift_card_value = wc_clean($cart_item['gift_card_value']);
    $price = wc_price($gift_card_value); // Formats the value as a price
  }
  return $price;
}


// /** 
//  * Display Custom Data in Cart
//  */
// add_filter('woocommerce_get_item_data', 'display_gift_card_item_data', 10, 2);

// function display_gift_card_item_data($item_data, $cart_item) {
//   if (isset($cart_item['gift_card_value'])) {
//     $item_data[] = array(
//       'key'     => __('Gift Card Value', 'giftcardify_for_woocommerce'),
//       'value'   => wc_clean($cart_item['gift_card_value']),
//       'display' => wc_clean($cart_item['gift_card_value']),
//     );
//   }
//   return $item_data;
// }


// /**
//  * Adjust cart clist, subtotal, cart total with gift_card_value
//  */
add_action('woocommerce_cart_loaded_from_session', 'custom_gift_card_before_calculate_totals', 10, 1);
function custom_gift_card_before_calculate_totals($cart) {
  foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
    if (isset($cart_item['gift_card_value'])) {
      $cart_item['data']->set_price($cart_item['gift_card_value']);
      $cart_item['data']->set_regular_price($cart_item['gift_card_value']);
    }
  }
}
