(function ($) {
  Drupal.behaviors.customFixesAdminFormJs = {
    attach: function (context, settings) {
      // Your custom JavaScript code for the admin form.
      console.log('Custom JavaScript attached to admin form.');
	  
	  
	  $('#edit-variations').removeAttr( "open" );
	  
	  // Attach an event listener to the source field.
      $('[name="field_supplier_sku[0][value]"]').each(function() {
        // Get the value from the source field.
        var sourceValue = $(this).val();
        // Set the value to the target field.
        $('[name="variations[entity][sku][0][value]"]').val(sourceValue);
      });
	  
	  
	  // Attach an event listener to the source field.
      $('[name="title[0][value]"]').each(function() {
        // Get the value from the source field.
        var sourceValue = $(this).val();

        // Set the value to the target field.
        $('[name="variations[entity][title][0][value]"]').val(sourceValue);
      });
	  
	  // Attach an event listener to the source field.
      $('[name="commerce_price[0][number]"]').each(function() {
        // Get the value from the source field.
        var sourceValue = $(this).val();

        // Set the value to the target field.
        $('[name="variations[entity][price][0][number]"]').val(sourceValue);
      });
	  
	  
	  
	  // Attach an event listener to the source field.
      $('[name="field_supplier_sku[0][value]"]').on("change paste keyup", function() {
        // Get the value from the source field.
        var sourceValue = $(this).val();

        // Set the value to the target field.
        $('[name="variations[entity][sku][0][value]"]').val(sourceValue);
      });
	  
	  
	  // Attach an event listener to the source field.
      $('[name="title[0][value]"]').on("change paste keyup", function() {
        // Get the value from the source field.
        var sourceValue = $(this).val();

        // Set the value to the target field.
        $('[name="variations[entity][title][0][value]"]').val(sourceValue);
      });
	  
	  // Attach an event listener to the source field.
      $('[name="commerce_price[0][number]"]').on("change paste keyup", function() {
        // Get the value from the source field.
        var sourceValue = $(this).val();

        // Set the value to the target field.
        $('[name="variations[entity][price][0][number]"]').val(sourceValue);
      });
    }
  };
})(jQuery);
