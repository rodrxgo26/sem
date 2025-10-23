(function ($, Drupal) {
  'use strict';

  $(document).ajaxComplete(function (event, xhr, settings) {
    
    const wrapper = $('#dict-wrapper');
    const prefixesJson = wrapper.attr('data-valid-prefixes');

    if (!prefixesJson) {
      return;
    }
    
    const validPrefixes = JSON.parse(prefixesJson);
    let invalidItems = [];

    // CHANGED: The function now accepts the jQuery field object ($field) as a second argument.
    const getValidationErrors = function(value, $field) {
  let errors = [];
  const fieldName = $field.attr('name');

  // --- Rule 1: Check for required fields ---
  const isRequired = 
    fieldName.startsWith('variable_column_') ||
    fieldName.startsWith('variable_attribute_') ||
    fieldName.startsWith('variable_is_attribute_of_') ||
    fieldName.startsWith('object_column_') ||
    fieldName.startsWith('object_entity_');

  if (isRequired && (value === null || value.trim() === '')) {
    errors.push('This field cannot be empty.');
    return errors;
  }

  // --- Rule 2: Check for namespace format on specific fields ---

  // Define which fields should have the "prefix:value" format.
  // This excludes the 'column' fields.
  const requiresNamespace = 
    !fieldName.startsWith('variable_column_') &&
    !fieldName.startsWith('object_column_');

  // Only apply namespace validation if the field requires it AND is not empty.
  if (requiresNamespace && value !== null && value.trim() !== '') {
    if (value.includes(':')) {
      const prefix = value.split(':', 1)[0];
      if (!validPrefixes.includes(prefix)) {
        errors.push('The prefix "' + prefix + '" is not a valid namespace.');
      }
    } else {
      errors.push('The value must be in the format "prefix:value".');
    }
  }

  return errors;
};

    // CHANGED: The selector now includes the 'column' fields to ensure they are validated.
    const fieldsToValidate = $('input[name*="variable_column_"], input[name*="variable_attribute_"], input[name*="_is_attribute_of_"], input[name*="_unit_"], input[name*="object_column_"], input[name*="object_entity_"], input[name*="_role_"], input[name*="_relation_"], input[name*="_class_"]');
    
    // Clear previous error styles and icons
    fieldsToValidate.css({'border': '', 'background-color': ''});
    $('.tooltip-wrapper').remove();
    $('.drupal-message').remove();

    fieldsToValidate.each(function () {
      const $field = $(this);
      // CHANGED: Pass the field object itself to the validation function.
      const errors = getValidationErrors($field.val(), $field);
      
      if (errors.length > 0) {
        invalidItems.push({ field: $field, errors: errors });
      }
    });

    const messages = new Drupal.Message();
    if (invalidItems.length > 0) {

      invalidItems.forEach(function(item) {
        const $field = item.field;
        const errors = item.errors;
        const errorCount = errors.length;
        const tooltipMessage = errors.join('\n');

        $field.css({'border': '2px solid red', 'background-color': '#f8d7da'});

        const $tooltipWrapper = $('<span class="tooltip-wrapper"></span>');
        const $errorIcon = $('<span class="namespace-error-icon">' + errorCount + '</span>');
        const $tooltipText = $('<span class="custom-tooltip-text">' + tooltipMessage + '</span>');
        
        $tooltipWrapper.append($errorIcon).append($tooltipText);
        $field.after($tooltipWrapper);
      });
      
      messages.add('Validation complete: ' + invalidItems.length + ' fields have errors.', { type: 'error' });

    } else {
      messages.add('Validation complete: No errors were found.', { type: 'status' });
    }
  });

})(jQuery, Drupal);