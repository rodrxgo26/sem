(function ($, Drupal) {
  'use strict';

  // Renamed function for clarity. It now processes all validation results.
  $.fn.processValidationResults = function(results) {

    // --- PART 1: UPDATE TAB BADGES (Same as before) ---

    $('.validation-badge').remove();
    const tabMap = {
      'basic': 'button_basic',
      'dictionary': 'button_dictionary',
      'codebook': 'button_codebook'
    };
    const errorCounts = results.error_counts;

    for (const tabKey in errorCounts) {
      if (tabMap.hasOwnProperty(tabKey)) {
        const buttonName = tabMap[tabKey];
        const count = errorCounts[tabKey];
        const $button = $('input[name="' + buttonName + '"]');
        if ($button.length) {
          const $badge = $('<span class="validation-badge"></span>');
          $badge.text(count);
          $badge.addClass(count > 0 ? 'errors' : 'no-errors');
          $button.after($badge);
        }
      }
    }

    // --- PART 2: HIGHLIGHT SPECIFIC INVALID FIELDS (Functionality restored) ---

    // First, clear all previous field-level error styles
    $('input[name^="variable_"], input[name^="object_"]').css({'border': '', 'background-color': ''});
    $('.tooltip-wrapper').remove();

    const detailedErrors = results.detailed_errors;
    if (detailedErrors) {
      for (const fieldName in detailedErrors) {
        const $field = $('input[name="' + fieldName + '"]');

        // Check if the field is visible on the current tab
        if ($field.length > 0) {
          const errors = detailedErrors[fieldName];
          const errorCount = errors.length;
          const tooltipMessage = errors.join('\n');

          // Apply the error style to the field.
          $field.css({'border': '2px solid red', 'background-color': '#f8d7da'});

          // Create and append the tooltip icon
          const $tooltipWrapper = $('<span class="tooltip-wrapper"></span>');
          const $errorIcon = $('<span class="namespace-error-icon">' + errorCount + '</span>');
          const $tooltipText = $('<span class="custom-tooltip-text">' + tooltipMessage + '</span>');
          $tooltipWrapper.append($errorIcon).append($tooltipText);
          $field.after($tooltipWrapper);
        }
      }
    }
  };

})(jQuery, Drupal);