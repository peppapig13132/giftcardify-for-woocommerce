<?php
/*
Plugin Name: GiftCardify for WooCommerce
Plugin URI:
Description: This plugin provides a custom Gift card solution for the e-shops using WordPress & WooCommerce.
Version: 1.0.1
Author:
Author URI:
*/

if (!defined('WPINC')) {
  die;
}

/**
 * Include require files
 */
require_once(plugin_dir_path(__FILE__) . 'includes/class/giftcardify-giftcard.php');

define('GIFTCARDIFY_VERSION', '1.0.1');

add_action('plugins_loaded', 'giftcardify_check_woocommerce', 5);

function giftcardify_check_woocommerce() {
  if (class_exists('WooCommerce')) {
    return;
  }

  add_action('admin_notices', 'giftcardify_missing_woocommerce_notice');
}

function giftcardify_missing_woocommerce_notice() {
  echo '<div class="notice notice-error"><p>' . esc_html__('GiftCardify requires WooCommerce to be installed and active.', 'giftcardify_for_woocommerce') . '</p></div>';
}

/**
 * Allowed preset amounts for a gift card product (from product meta).
 *
 * @return float[]
 */
function giftcardify_get_allowed_preset_amounts($product_id) {
  $values = get_post_meta($product_id, '_gift_card_values', true);

  if (!$values) {
    return array();
  }

  $amounts = array();

  foreach (explode(',', $values) as $value) {
    $value = trim($value);

    if ($value !== '' && is_numeric($value)) {
      $amounts[] = round(floatval($value), 2);
    }
  }

  return array_values(array_unique($amounts));
}

/**
 * Validate gift card purchase amount server-side.
 *
 * @return float|WP_Error
 */
function giftcardify_validate_gift_card_amount($product_id, $gift_card_value, $custom_amount) {
  $product = wc_get_product($product_id);

  if (!$product || $product->get_type() !== 'gift_card') {
    return new WP_Error('invalid_product', __('Invalid gift card product.', 'giftcardify_for_woocommerce'));
  }

  $allowed_presets = giftcardify_get_allowed_preset_amounts($product_id);

  if ($gift_card_value === 'custom_amount') {
    $amount = round(floatval($custom_amount), 2);
    $min = apply_filters('giftcardify_min_custom_amount', 1);
    $max = apply_filters('giftcardify_max_custom_amount', 10000);

    if ($amount < $min || $amount > $max) {
      return new WP_Error(
        'invalid_custom_amount',
        sprintf(
          /* translators: 1: minimum amount 2: maximum amount */
          __('Custom gift card amount must be between %1$s and %2$s.', 'giftcardify_for_woocommerce'),
          wc_price($min),
          wc_price($max)
        )
      );
    }

    return $amount;
  }

  $selected = round(floatval($gift_card_value), 2);

  foreach ($allowed_presets as $preset) {
    if (abs($preset - $selected) < 0.0001) {
      return $selected;
    }
  }

  return new WP_Error('invalid_amount', __('Please select a valid gift card amount.', 'giftcardify_for_woocommerce'));
}

function giftcardify_activate_order_gift_cards($order_id) {
  $gift_card = new GiftCardify_GiftCard();
  $gift_card->update_status_from_draft_to_created($order_id);
}

function giftcardify_is_redemption_rate_limited() {
  $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
  $key = 'giftcardify_redeem_fail_' . md5($ip);
  $failures = (int) get_transient($key);

  return $failures >= 10;
}

function giftcardify_record_redemption_failure() {
  $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
  $key = 'giftcardify_redeem_fail_' . md5($ip);
  $failures = (int) get_transient($key);

  set_transient($key, $failures + 1, 15 * MINUTE_IN_SECONDS);
}

function giftcardify_clear_redemption_failures() {
  $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
  delete_transient('giftcardify_redeem_fail_' . md5($ip));
}

/**
 * Plugin activation hooks
 */
register_activation_hook(__FILE__, 'giftcardify_activation');

function giftcardify_activation() {
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  require_once plugin_dir_path(__FILE__) . 'database/db-setup.php';

  dbDelta($sql_gift_cards);
  dbDelta($sql_gift_card_logs);
}

/**
 * Plugin deactivation hooks
 */
register_deactivation_hook(__FILE__, 'giftcardify_deactivation');

function giftcardify_deactivation() {
  wp_clear_scheduled_hook('giftcardify_custom_cron_hook');
}


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
      $custom_template = plugin_dir_path(__FILE__) . 'templates/single-product/gift-card.php';
      
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
 * Import single product gift card template style
 */
add_action('wp_enqueue_scripts', 'enqueue_single_product_gift_card_styles');

function enqueue_single_product_gift_card_styles() {
  if (is_singular('product')) {
    $timestamp = Date('U');
    wp_enqueue_style('single-product-gift-card-style', plugin_dir_url(__FILE__) . 'assets/css/single-product-gift_card.css?' . $timestamp);
  }
}


/** 
 * Add gift card to cart - Ajax
 */
add_action('wp_ajax_add_gift_card_to_cart', 'add_gift_card_to_cart');
add_action('wp_ajax_nopriv_add_gift_card_to_cart', 'add_gift_card_to_cart');

function add_gift_card_to_cart() {
  check_ajax_referer('gift_card_nonce', 'security');

  $response = array('success' => false, 'message' => '');

  if (!function_exists('WC') || !WC()->cart) {
    $response['message'] = __('Store is unavailable. Please try again.', 'giftcardify_for_woocommerce');
    wp_send_json($response);
  }

  if (!WC()->session) {
    WC()->session = new WC_Session_Handler();
    WC()->session->init();
  }

  $product_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
  $gift_card_value = isset($_POST['gift_card_value']) ? sanitize_text_field(wp_unslash($_POST['gift_card_value'])) : '';
  $custom_amount = isset($_POST['custom_gift_card_price']) ? floatval($_POST['custom_gift_card_price']) : 0;
  $receiver_firstname = isset($_POST['receiver_firstname']) ? sanitize_text_field(wp_unslash($_POST['receiver_firstname'])) : '';
  $receiver_lastname = isset($_POST['receiver_lastname']) ? sanitize_text_field(wp_unslash($_POST['receiver_lastname'])) : '';
  $receiver_email = isset($_POST['receiver_email']) ? sanitize_email(wp_unslash($_POST['receiver_email'])) : '';
  $receiver_email_confirm = isset($_POST['receiver_email_confirm']) ? sanitize_email(wp_unslash($_POST['receiver_email_confirm'])) : '';
  $sender_name = isset($_POST['sender_name']) ? sanitize_text_field(wp_unslash($_POST['sender_name'])) : '';
  $gift_message = isset($_POST['gift_message']) ? sanitize_textarea_field(wp_unslash($_POST['gift_message'])) : '';
  $shipping_date = isset($_POST['shipping_date']) ? sanitize_text_field(wp_unslash($_POST['shipping_date'])) : '';

  if ($product_id <= 0) {
    $response['message'] = __('Invalid product ID.', 'giftcardify_for_woocommerce');
    wp_send_json($response);
  }

  $product = wc_get_product($product_id);

  if (!$product || $product->get_type() !== 'gift_card') {
    $response['message'] = __('Invalid product type.', 'giftcardify_for_woocommerce');
    wp_send_json($response);
  }

  if (empty($gift_card_value)) {
    $response['message'] = __('Gift card value is required.', 'giftcardify_for_woocommerce');
    wp_send_json($response);
  }

  $validated_amount = giftcardify_validate_gift_card_amount($product_id, $gift_card_value, $custom_amount);

  if (is_wp_error($validated_amount)) {
    $response['message'] = $validated_amount->get_error_message();
    wp_send_json($response);
  }

  $gift_card_value_final = $validated_amount;

  if (empty($receiver_firstname)) {
    $response['message'] = __('Receiver first name is required.', 'giftcardify_for_woocommerce');
    wp_send_json($response);
  }

  if (empty($receiver_lastname)) {
    $response['message'] = __('Receiver last name is required.', 'giftcardify_for_woocommerce');
    wp_send_json($response);
  }

  if (empty($receiver_email) || !is_email($receiver_email)) {
    $response['message'] = __('A valid receiver email is required.', 'giftcardify_for_woocommerce');
    wp_send_json($response);
  }

  if (empty($receiver_email_confirm) || !is_email($receiver_email_confirm)) {
    $response['message'] = __('Please confirm the receiver email.', 'giftcardify_for_woocommerce');
    wp_send_json($response);
  }

  if (strtolower($receiver_email) !== strtolower($receiver_email_confirm)) {
    $response['message'] = __('Receiver email addresses do not match.', 'giftcardify_for_woocommerce');
    wp_send_json($response);
  }

  if (empty($shipping_date) || false === strtotime($shipping_date)) {
    $response['message'] = __('A valid shipping date is required.', 'giftcardify_for_woocommerce');
    wp_send_json($response);
  }

  // Prepare custom cart item data
  $cart_item_data = array(
    'gift_card_value'     => $gift_card_value_final,
    'receiver_firstname'  => $receiver_firstname,
    'receiver_lastname'   => $receiver_lastname,
    'receiver_email'      => $receiver_email,
    'sender_name'         => $sender_name,
    'gift_card_code'      => '',
    'gift_message'        => $gift_message,
    'shipping_date'       => $shipping_date,
  );

  if (!$product->is_purchasable()) {
    $response['message'] = __('This product cannot be purchased.', 'giftcardify_for_woocommerce');
    wp_send_json($response);
  }

  // Add product to cart
  $cart_item_key = WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);

  if ($cart_item_key) {
    $response['success'] = true;
    $response['message'] = __('Gift card added to cart successfully.', 'giftcardify_for_woocommerce');
  } else {
    $response['message'] = __('Failed to add gift card to cart.', 'giftcardify_for_woocommerce');
  }

  wp_send_json($response);
  wp_die();
}


/**
 * Add custom data - Gift card form data to order
 */
add_action('woocommerce_checkout_create_order_line_item', 'save_custom_data_to_order_item', 10, 4);

function save_custom_data_to_order_item($item, $cart_item_key, $values, $order) {
  if (!isset($values['gift_card_value'])) {
    return;
  }

  $giftcard = new GiftCardify_GiftCard();
  $gift_card_code = $giftcard->create_giftcard(
    $order->get_id(),
    $values['receiver_firstname'],
    $values['receiver_lastname'],
    $values['receiver_email'],
    $values['sender_name'],
    $order->get_billing_email(),
    $values['gift_message'],
    $values['gift_card_value'],
    $values['shipping_date']
  );

  $item->add_meta_data('receiver_firstname', $values['receiver_firstname']);
  $item->add_meta_data('receiver_lastname', $values['receiver_lastname']);
  $item->add_meta_data('receiver_email', $values['receiver_email']);
  $item->add_meta_data('sender_name', $values['sender_name']);
  $item->add_meta_data('gift_card_code', $gift_card_code ? $gift_card_code : '');
  $item->add_meta_data('gift_message', $values['gift_message']);
  $item->add_meta_data('shipping_date', $values['shipping_date']);
  $item->add_meta_data('gift_card_value', $values['gift_card_value']);
}


/**
 * Hide gift card form data on order table
 */
add_filter('woocommerce_order_item_get_formatted_meta_data', 'hide_custom_meta_data_from_order', 10, 2);

function hide_custom_meta_data_from_order($formatted_meta, $order_item) {
  // Define the meta keys you want to hide
  $meta_keys_to_hide = array(
    'receiver_firstname',
    'receiver_lastname',
    'receiver_email',
    'sender_name',
    'gift_card_code',
    'gift_message',
    'shipping_date',
    'gift_card_value'
  );

  foreach ($formatted_meta as $key => $meta) {
    if (in_array($meta->key, $meta_keys_to_hide)) {
      unset($formatted_meta[$key]);
    }
  }

  return $formatted_meta;
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


/**
 * Adjust cart clist, subtotal, cart total with gift_card_value
 */
add_action('woocommerce_cart_loaded_from_session', 'custom_gift_card_before_calculate_totals', 10, 1);

function custom_gift_card_before_calculate_totals($cart) {
  foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
    if (isset($cart_item['gift_card_value'])) {
      $cart_item['data']->set_price($cart_item['gift_card_value']);
      $cart_item['data']->set_regular_price($cart_item['gift_card_value']);
    }
  }
}


add_action('woocommerce_checkout_order_processed', 'giftcardify_order_created', 10, 3);

function giftcardify_order_created($order_id, $posted_data, $order) {
  // About $order: https://www.businessbloomer.com/woocommerce-easily-get-order-info-total-items-etc-from-order-object/

  // Send custom order email
  $to = $order->get_billing_email();
  $subject = 'Your Order is Complete';
  $placeholders = array(
    'name'            => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
    'order_id'        => $order_id,
    'product_list'    => $order->get_items(),
    'subtotal'        => wc_price($order->get_subtotal()),
    'discount'        => wc_price($order->get_discount_total()),
    'total'           => wc_price($order->get_total()),
    'billing_email'   => $order->get_billing_email(),
    'assets_path'     => plugin_dir_url(__FILE__)
  );
  
  send_custom_order_created_wp_email($to, $subject, $placeholders);

  // // Send gift card received email if the order includes gift card
  // $has_gift_card = false;

  // foreach ($order->get_items() as $item_id => $item) {
  //   $product = $item->get_product();

  //   if ($product && $product->get_type() === 'gift_card') {
  //     $has_gift_card = true;
      
  //     if($has_gift_card) {
  //       $receiver_firstname = $item->get_meta('receiver_firstname');
  //       $receiver_lastname = $item->get_meta('receiver_lastname');
  //       $receiver_email = $item->get_meta('receiver_email');
  //       $sender_name = $item->get_meta('sender_name');
  //       $gift_card_code = $item->get_meta('gift_card_code');
  //       $gift_message = $item->get_meta('gift_message');
  //       $shipping_date = $item->get_meta('shipping_date');
  //       $gift_card_value = $item->get_meta('gift_card_value');

  //       // available_data = shipping_date + 1 year
  //       $date = new DateTime($shipping_date);
  //       $date->modify('+1 year');
  //       $available_date = $date->format('Y-m-d');
        
  //       $g_to = $order->get_billing_email();
  //       $g_subject = 'You Received a Gift Card';
  //       $g_placeholders = array(
  //         'receiver_name'    => $receiver_firstname . ' ' . $receiver_lastname,
  //         'sender_name'      => $sender_name,
  //         'gift_card_code'   => $gift_card_code,
  //         'gift_message'     => $gift_message,
  //         'available_date'   => $available_date, 
  //         'assets_path'      => plugin_dir_url(__FILE__)
  //       );

  //       send_gift_card_received_wp_email($g_to, $g_subject, $g_placeholders);
  //     }
  //   }
  // }
}


function get_order_created_email_template($template_path, $placeholders) {
  if(!file_exists($template_path)) {
    return '';
  }

  $template_content = file_get_contents($template_path);
  
  if(isset($placeholders['product_list']) && is_array($placeholders['product_list'])) {
    $product_list_html = '<table style="width: 100%;">
      <tbody>';
      foreach($placeholders['product_list'] as $item_id => $item) {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $product = $item->get_product();

        $full_image_url  = get_the_post_thumbnail_url($product_id, 'full_size');

        // if ($variation_id) {
        //   $full_image_url = wp_get_attachment_image_url(get_post_thumbnail_id($variation_id), 'full_size');
        // }

        $product_list_html .=
        '<tr>
          <td style="min-width: 100px; max-width: 200px; width: 30%; padding-bottom: 20px;">
            <a href="' . get_permalink($product_id) . '">
              <img src="' . $full_image_url  . '" style="width: 100%;" alt="' . $item->get_name() . '">
            </a>
          </td>
          <td style="padding-left: 10px; padding-bottom: 20px;">
            <a href="' . get_permalink($product_id) . '" style="text-decoration: none; color: unset;">
              <div>
                <table style="width: 100%;">
                  <tr>
                    <td>' . $item->get_name() . '</td>
                    <td style="vertical-align: top; width: 50px; text-align: right;">' . wc_price($item->get_subtotal()) . '</td>
                  </tr>
                </table>
              </div>
              <div style="margin-top: 5px;">' . $product->get_attribute('pa_color') . '</div>
              <div style="margin-top: 5px;">' . $product->get_attribute('pa_size') . '</div>
              <div style="margin-top: 5px;">' . $item->get_quantity() . '</div>
            </a>
          </td>
        </tr>';
      }
      $product_list_html .=
      '</tbody>
    </table>';

    $template_content = str_replace('{{PRODUCT_LIST}}', $product_list_html, $template_content);
    unset($placeholders['product_list']);
  }

  foreach ($placeholders as $key => $value) {
    $template_content = str_replace('{{' . strtoupper($key) . '}}', $value, $template_content);
  }

  return $template_content;
}


function send_custom_order_created_wp_email($to, $subject, $placeholders) {
  $template_path = plugin_dir_path(__FILE__) . 'templates/emails/custom-order-created-email.php';

  $message = get_order_created_email_template($template_path, $placeholders);
  
  $headers = array('Content-Type: text/html; charset=UTF-8');
  $headers[] = 'From: Listen To Your Soul <admin@ltysoul.com>';

  wp_mail($to, $subject, $message, $headers);
}


function get_gift_card_received_email_template($template_path, $placeholders) {
  if(!file_exists($template_path)) {
    return '';
  }

  $template_content = file_get_contents($template_path);

  foreach ($placeholders as $key => $value) {
    $template_content = str_replace('{{' . strtoupper($key) . '}}', $value, $template_content);
  }

  return $template_content;
}


/**
 * Activate purchased gift cards after payment (draft -> created).
 */
add_action('woocommerce_payment_complete', 'giftcardify_activate_order_gift_cards_on_payment', 10, 1);
add_action('woocommerce_order_status_completed', 'giftcardify_activate_order_gift_cards_on_payment', 10, 1);

function giftcardify_activate_order_gift_cards_on_payment($order_id) {
  giftcardify_activate_order_gift_cards($order_id);
}


/**
 * Register gift card gateway
 */
add_filter('woocommerce_payment_gateways', 'giftcardify_register_gateway');

function giftcardify_register_gateway($gateways) {
  $gateways[] = 'WC_GiftCardify_Gateway';
  return $gateways;
}


add_action('plugins_loaded', 'giftcardify_init_gateway_class', 20);

function giftcardify_init_gateway_class() {
  if (!class_exists('WooCommerce') || !class_exists('WC_Payment_Gateway')) {
    return;
  }

  class WC_GiftCardify_Gateway extends WC_Payment_Gateway {

    public function __construct() {
      $this->id = 'giftcardify_gateway';
      $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
      $this->has_fields = true; // in case you need a custom credit card form
      $this->method_title = 'Gift Card';
      $this->method_description = 'Pay with gift card balance';

      // Load the settings.
      $this->init_form_fields();
      $this->init_settings();

      // Define user setting variables.
      $this->title = $this->get_option('title');
      $this->description = $this->get_option('description');

      // Actions.
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
      add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

      // // Customer Emails.
      // add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
    }

    // Initialize Gateway Settings Form Fields.
    public function init_form_fields() {
      $this->form_fields = array(
        'enabled' => array(
          'title' => 'Enable/Disable',
          'type' => 'checkbox',
          'label' => 'Enable Gift Card Payment',
          'default' => 'yes'
        ),
        'title' => array(
          'title' => 'Title',
          'type' => 'text',
          'description' => 'This controls the title which the user sees during checkout.',
          'default' => 'Gift Card',
          'desc_tip' => true,
        ),
        'description' => array(
          'title' => 'Description',
          'type' => 'textarea',
          'description' => 'This controls the description which the user sees during checkout.',
          'default' => 'Pay with your gift card balance.',
        ),
      );
    }

    // Payment form on checkout page.
    public function payment_fields() {
      ?>
      <p><?php echo esc_html($this->description); ?></p>
      <div>
        <p class="form-row form-row-wide">
          <label for="giftcardify_gift_card_code"><?php _e('Gift Card Code', 'woocommerce'); ?> <span class="required">*</span></label>
          <input id="giftcardify_gift_card_code" class="input-text" type="text" name="giftcardify_gift_card_code" autocomplete="off">
        </p>
      </div>
      <?php
    }

    // Process the payment and return the result.
    public function process_payment($order_id) {
      $order = wc_get_order($order_id);

      if (!$order) {
        wc_add_notice(__('Order not found.', 'giftcardify_for_woocommerce'), 'error');
        return array('result' => 'fail', 'redirect' => '');
      }

      if (giftcardify_is_redemption_rate_limited()) {
        wc_add_notice(__('Too many failed attempts. Please wait and try again.', 'giftcardify_for_woocommerce'), 'error');
        return array('result' => 'fail', 'redirect' => '');
      }

      $gift_card_code = isset($_POST['giftcardify_gift_card_code'])
        ? sanitize_text_field(wp_unslash($_POST['giftcardify_gift_card_code']))
        : '';

      if ('' === $gift_card_code) {
        giftcardify_record_redemption_failure();
        wc_add_notice(__('Please enter a gift card code.', 'giftcardify_for_woocommerce'), 'error');
        return array('result' => 'fail', 'redirect' => '');
      }

      $giftcard = new GiftCardify_GiftCard();
      $gift_card = $giftcard->get_giftcard($gift_card_code);
      $order_total = round(floatval($order->get_total()), 2);

      if (!$gift_card || floatval($gift_card->gift_card_balance) < $order_total) {
        giftcardify_record_redemption_failure();
        wc_add_notice(__('Invalid or insufficient gift card balance.', 'giftcardify_for_woocommerce'), 'error');
        return array('result' => 'fail', 'redirect' => '');
      }

      if (!$giftcard->redeem_gift_card($gift_card_code, $order_id, $order_total)) {
        giftcardify_record_redemption_failure();
        wc_add_notice(__('Unable to apply gift card. Please try again.', 'giftcardify_for_woocommerce'), 'error');
        return array('result' => 'fail', 'redirect' => '');
      }

      giftcardify_clear_redemption_failures();

      $order->payment_complete();
      wc_reduce_stock_levels($order_id);

      $order->add_order_note(
        sprintf(
          __('Gift card applied. Code: %1$s, Amount: %2$s', 'giftcardify_for_woocommerce'),
          $gift_card_code,
          wc_price($order_total)
        )
      );

      return array(
        'result'   => 'success',
        'redirect' => $this->get_return_url($order),
      );
    }

    // Output for the order received page.
    public function thankyou_page() {
      if ($this->instructions) {
        echo wpautop(wptexturize($this->instructions));
      }
    }

    // // Add content to the WC emails.
    // public function email_instructions($order, $sent_to_admin, $plain_text = false) {
    //   if ($this->instructions && !$sent_to_admin && 'giftcardify_gateway' === $order->get_payment_method()) {
    //     echo wpautop(wptexturize($this->instructions));
    //   }
    // }
  }
}


/**
 * Register the cron job and hook to the GiftCardify_GiftCard class method
 */
add_action('init', 'register_giftcardify_custom_cron_job');

function register_giftcardify_custom_cron_job() {
  $gift_card = new GiftCardify_GiftCard();

  // Schedule the event if it's not already scheduled
  if (!wp_next_scheduled('giftcardify_custom_cron_hook')) {
      wp_schedule_event(time(), 'daily', 'giftcardify_custom_cron_hook');
  }
  
  add_action('giftcardify_custom_cron_hook', array($gift_card, 'send_gift_message_emails'));
}