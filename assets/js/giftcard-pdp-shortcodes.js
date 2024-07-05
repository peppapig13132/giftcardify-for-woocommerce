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
    } else {
      console.log("🐛 missing uuid module");
    }

    localStorage.setItem("_giftcardify_giftcard", JSON.stringify(jsonData));
  });
});