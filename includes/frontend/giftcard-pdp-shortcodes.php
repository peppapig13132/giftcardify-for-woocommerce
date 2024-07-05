<?php
/*
On gift card pdp(product page), the gift card data will be stored in web browser localstorage by clicking "ADD TO CHECKOUT" button.

Model: Giftcard
{
  id: number,
  uuid: string,
  receiver_firstname: string,
  receiver_lastname: string,
  receiver_email: string,
  sender_name: string,
  sender_email: string,
  gift_message: string,
  code: string,
  amount: number,
  balance: number,
  status: enum(draft, pending, ready_to_use, canceled),
  note: string,
  shipping_at: string,
  created_at: string,
  updated_at: string,
  expired_at: string,
}
*/

require_once plugin_dir_path(__FILE__) . '../../constants.php';

// Hook to enqueue the script on all pages - Import uuid module via cdn.
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

// Shortcode function
function giftcardify_product_form_shortcode() {
  // Enqueue the uuid script
  wp_enqueue_script('uuid-script');

  $output = '
  <script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function() {
      var addToCheckoutButton = document.getElementById("' . GIFTCARD_FORM_ADD_TO_CHECKOUT_BTN . '");

      // Generate UUID and display it
      if (window.uuid) {
        var generatedUuid = uuid.v4();
      } else {
        console.log("🐛 missing uuid module");
      }

      if (!addToCheckoutButton) {
        console.error("🚫 Add to Checkout button not found");
        return; // Stop the script if button is not found
      }

      addToCheckoutButton.addEventListener("click", function() {
        var amount = document.getElementById("' . GIFTCARD_FORM_AMOUNT . '").value;
        var receiver_firstname = document.getElementById("' . GIFTCARD_FORM_RECEIVER_FIRST_NAME . '").value;
        var receiver_lastname = document.getElementById("' . GIFTCARD_FORM_RECEIVER_LAST_NAME . '").value;
        var receiver_email = document.getElementById("' . GIFTCARD_FORM_RECEIVER_EMAIL . '").value;
        var sender_name = document.getElementById("' . GIFTCARD_FORM_SENDER_NAME . '").value;
        var gift_message = document.getElementById("' . GIFTCARD_FORM_GIFT_MESSAGE . '").value;
        var shipping_date = document.getElementById("' . GIFTCARD_FORM_SHIPPING_DATE . '").value;
        var timestamp = Date.now();

        if (!amount || !receiver_firstname || !receiver_lastname || !receiver_email || !sender_name || !gift_message || !shipping_date) {
          console.error("🚫 Required fields are missing");
          return; // Stop the script if required fields are missing
        }
        
        var giftcardData = {
          uuid: generatedUuid,
          amount: amount,
          receiver_firstname: receiver_firstname,
          receiver_lastname: receiver_lastname,
          receiver_email: receiver_email,
          sender_name: sender_name,
          gift_message: gift_message,
          shipping_date: shipping_date,
          timestamp: timestamp
        };

        try {
          // Retrieve existing data from localStorage
          var existingData = localStorage.getItem("giftcardify_giftcards");

          if (existingData) {
              // If data exists, parse it to an array
              existingData = JSON.parse(existingData);

              // Check if the existingData is an array, if not, make it an array
              if (!Array.isArray(existingData)) {
                  existingData = [existingData];
              }

              // Add the new giftcardData to the existing data
              existingData.push(giftcardData);
          } else {
              // If no data exists, create a new array with the giftcardData
              existingData = [giftcardData];
          }

          localStorage.setItem("giftcardify_giftcards", JSON.stringify(existingData));

          console.log("🔔 giftcard data stored in localstorage!");
        } catch(e) {
          console.error("🛑 Error storing giftcard data in localstorage", e);
        }
      });
    });
  </script>';

  return $output;
}
add_shortcode('giftcardify_product_form', 'giftcardify_product_form_shortcode');