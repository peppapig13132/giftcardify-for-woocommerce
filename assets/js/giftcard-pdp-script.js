jQuery(document).ready(function($) {
  $('#gift_card_form').on('submit', function(e) {
		e.preventDefault(); // Prevent form submission

		var formData = $(this).serializeArray();
		var jsonData = {};
		$.each(formData, function() {
			jsonData[this.name] = this.value;
		});

    if (window.uuid) {
      var generatedUuid = uuid.v4();
      jsonData['uuid'] = generatedUuid;
    } else {
      console.log("🐛 missing uuid module");
    }

    localStorage.setItem('_giftcardify_giftcard', JSON.stringify(jsonData));

    jsonData['action'] = 'buy_gift_card';

    $.ajax({
      url: '/wp-admin/admin-ajax.php',
      type: 'POST',
      data: jsonData,
      success: function(response) {
        if(response.success) {
          window.location.href = '/cart';
        } else {
          console.log('Failed to add product to cart');
        }
      },
      error: function(xhr, status, error) {
        console.error('Error adding product to cart');
      }
    });
  });
});