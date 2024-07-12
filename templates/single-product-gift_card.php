<?php
/**
 * Gift card product page template
 */

defined('ABSPATH') || exit;

get_header('shop');

while (have_posts()) :
  the_post();
  global $product;
  $nonce = wp_create_nonce('gift_card_nonce');
  ?>

  <div id="product-<?php the_ID(); ?>" <?php wc_product_class(); ?>>

    <div class="single-product-gift-card-image">
      <div class="image-container">
        <div class="gift-card-title">
          <h1>GIFT CARD</h1>
        </div>
        <div class="gift-card-image">
          <img decoding="async" width="433" height="76" src="https://ltysoul.com/wp-content/uploads/2024/04/Group-3745.png" class="attachment-full size-full wp-image-5939" alt="" srcset="https://ltysoul.com/wp-content/uploads/2024/04/Group-3745.png 433w, https://ltysoul.com/wp-content/uploads/2024/04/Group-3745-300x53.png 300w" sizes="(max-width: 433px) 100vw, 433px">
        </div>
      </div>
    </div>

    <div class="single-product-gift-card-form">
      <div class="single-product-gift-card-discount">
        <span>10% Discount</span>
      </div>

      <form class="cart" method="post" id="gift_card_form" enctype="multipart/form-data">
        <input type="hidden" name="post_id" value="<?php echo $product->get_id(); ?>">
        <input type="hidden" name="product_type" value="<?php echo $product->get_type(); ?>">
        <input type="hidden" name="security" id="gift_card_nonce" value="<?php echo esc_attr($nonce); ?>">

        <?php
        // Display radio buttons for predefined values if available
        $values = get_post_meta($product->get_id(), '_gift_card_values', true);
        if ($values) {
          $values_array = explode(',', $values);
          sort($values_array);
          ?>
          <div class="gift-card-price">
            <div class="gift-card-price-presets">
              <?php foreach ($values_array as $value) : ?>
                <label>
                  <input type="radio" name="gift_card_value" value="<?php echo esc_attr($value); ?>" /> <?php echo esc_html($value); ?>
                </label>
              <?php endforeach; ?>
            </div>
            <div class="gift-card-price-custom">
              <label>
                <input type="radio" name="gift_card_value" value="custom_amount" /> <?php _e('Open Amount', 'giftcardify_for_woocommerce'); ?>
              </label>
              <div id="custom_amount_input" style="display: none;">
                <label for="custom_gift_card_price" hidden><?php _e('Custom Price', 'giftcardify_for_woocommerce'); ?></label>
                <input type="number" id="custom_gift_card_price" name="custom_gift_card_price" step="1" min="0" value="0" title="<?php echo esc_attr(__('Custom Price', 'giftcardify_for_woocommerce')); ?>">
              </div>
            </div>
          </div>
          <?php
        }
        ?>

        <div class="gift-card-content">
          <label for="receiver_firstname" hidden><?php _e('First name of the receiver*', 'giftcardify_for_woocommerce'); ?></label>
          <input type="text" id="receiver_firstname" name="receiver_firstname" value="" placeholder="First name of the receiver*" required>
        </div>
        <div class="gift-card-content">
          <label for="receiver_lastname" hidden><?php _e('Last name of the receiver*', 'giftcardify_for_woocommerce'); ?></label>
          <input type="text" id="receiver_lastname" name="receiver_lastname" value="" placeholder="Last name of the receiver*" required>
        </div>
        <div class="gift-card-content">
          <label for="receiver_email" hidden><?php _e('E-mail of the receiver*', 'giftcardify_for_woocommerce'); ?></label>
          <input type="email" id="receiver_email" name="receiver_email" value="" placeholder="E-mail of the receiver*" required>
        </div>
        <div class="gift-card-content">
          <label for="receiver_email_confirm" hidden><?php _e('Confirm E-mail of the receiver*', 'giftcardify_for_woocommerce'); ?></label>
          <input type="email" id="receiver_email_confirm" name="receiver_email_confirm" value="" placeholder="Confirm E-mail of the receiver*" required>
        </div>
        <div class="gift-card-content">
          <label for="sender_name" hidden><?php _e('Your name', 'giftcardify_for_woocommerce'); ?></label>
          <input type="text" id="sender_name" name="sender_name" value="" placeholder="Your name">
        </div>
        <div class="gift-card-content">
          <label for="gift_message" hidden><?php _e('Your message', 'giftcardify_for_woocommerce'); ?></label>
          <textarea id="gift_message" name="gift_message" rows="4" placeholder="Your message"></textarea>
        </div>
        <div class="gift-card-content">
          <label for="shipping_date" hidden><?php _e('Shipping date', 'giftcardify_for_woocommerce'); ?></label>
          <input type="date" id="shipping_date" name="shipping_date" value="" placeholder="Shipping date" required>
        </div>

        <button type="submit" class="single_add_to_cart_button button alt">Add to CHECKOUT</button>

        <?php do_action('woocommerce_after_add_to_cart_button'); ?>
      </form>
        
      <div class="single-product-gift-card-form__footer">
        <p class="text-xl">LISTERN TO YOUR SOUL</p>
        <p class="text-lg">TO HEAR THE SILENCE</p>

        <p class="gift-card-condition">
          * Conditions:<br/>
          Gift Card can only be used at: <a href="https://www.ltysoul.com">www.ltysoul.com</a> effective for 1 year.
        </p>
      </div>
    </div>

  </div>

  <script type="text/javascript">
  jQuery(document).ready(function($) {
    $('input[name="gift_card_value"]').change(function() {
      if ($(this).val() === 'custom_amount') {
        $('#custom_amount_input').show();
      } else {
        $('#custom_amount_input').hide();
      }
    });

    $('#gift_card_form').on('submit', function(e) {
      e.preventDefault();

      var formData = $(this).serializeArray();
      var jsonData = {};

      $.each(formData, function() {
        jsonData[this.name] = this.value;
      });
      
      jsonData['action'] = 'add_gift_card_to_cart';
      jsonData['security'] = $('#gift_card_nonce').val();
      
      $.ajax({
        url: '/wp-admin/admin-ajax.php',
        type: 'POST',
        data: jsonData,
        success: function(response) {
          if(response.success) {
            window.location.href = '/cart';
          } else {
            console.log('Failed to add gift card to cart');
          }
        },
        error: function(xhr, status, error) {
          console.error('Error adding gift card to cart');
        }
      });
    });
  });
  </script>

  <?php
endwhile;

get_footer('shop');
?>
