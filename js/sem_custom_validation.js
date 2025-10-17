(function ($, Drupal) {
  'use strict';

  $(document).ajaxComplete(function (event, xhr, settings) {
    
    const wrapper = $('#dict-wrapper');
    const prefixesJson = wrapper.attr('data-valid-prefixes');

    if (!prefixesJson) {
      return;
    }
    
    const validPrefixes = JSON.parse(prefixesJson);
    let invalidFields = [];

    const checkNamespace = function(value) {
      if (value === null || value === '') return true;
      if (value.includes(':')) {
        const prefix = value.split(':', 1)[0];
        return validPrefixes.includes(prefix);
      }
      return false;
    };

    const fieldsToValidate = $('input[name*="_attribute_"], input[name*="_is_attribute_of_"], input[name*="_unit_"], input[name*="_entity_"], input[name*="_role_"], input[name*="_relation_"], input[name*="_class_"]');
    
    // Clear previous error styles and icons
    fieldsToValidate.css({'border': '', 'background-color': ''});
    $('.tooltip-wrapper').remove(); // CHANGED: Removes the old tooltip container
    $('.drupal-message').remove();

    fieldsToValidate.each(function () {
      if (!checkNamespace($(this).val())) {
        invalidFields.push($(this));
      }
    });

    const messages = new Drupal.Message();
    if (invalidFields.length > 0) {
      // The message that will appear inside our custom tooltip.
      const tooltipMessage = 'Error: This value does not follow the NameSpace rule (e.g., "prefix:value").';

      invalidFields.forEach(function($field) {
        // Apply the error style to the field
        $field.css({'border': '2px solid red', 'background-color': '#f8d7da'});

        // ==========================================================
        // CHANGED LOGIC TO CREATE THE CUSTOM TOOLTIP
        // ==========================================================
        
        // 1. Create the main container
        const $tooltipWrapper = $('<span class="tooltip-wrapper"></span>');
        
        // 2. Create the 'i' icon (no longer needs the 'title' attribute)
        const $errorIcon = $('<span class="namespace-error-icon">i</span>');
        
        // 3. Create the element that will hold the tooltip text
        const $tooltipText = $('<span class="custom-tooltip-text">' + tooltipMessage + '</span>');
        
        // 4. Assemble the parts: the icon and text go inside the container
        $tooltipWrapper.append($errorIcon).append($tooltipText);
        
        // 5. Insert the complete container after the input field
        $field.after($tooltipWrapper);
      });
      
      messages.add('Validation complete: Errors were found.', { type: 'error' });

    } else {
      messages.add('Validation complete: No errors were found.', { type: 'status' });
    }
  });

})(jQuery, Drupal);