// We've added 'once' to the function parameters
(function ($, Drupal, drupalSettings, once) {
  'use strict';

  /**
   * Define the main function that applies all validation UI.
   */
  function applyValidationUI(results) {

    // --- PART 1: UPDATE BADGES ON TABS ---
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

        // Use 'input' and '.after()' as in your structure
        const $button = $('input[name="' + buttonName + '"]');

        if ($button.length) {
          const $badge = $('<span class="validation-badge"></span>');
          $badge.text(count);
          $badge.addClass(count > 0 ? 'errors' : 'no-errors');
          $button.after($badge);
        }
      }
    }

    // --- PART 2: HIGHLIGHT INVALID FIELDS ---

    $('input[name^="variable_"], input[name^="object_"]').css({'border': '', 'background-color': ''});
    $('.tooltip-wrapper').remove();

    const detailedErrors = results.detailed_errors;
    if (detailedErrors) {
      for (const fieldName in detailedErrors) {
        const $field = $('input[name="' + fieldName + '"]');

        if ($field.length > 0) {
          const errors = detailedErrors[fieldName];
          const errorCount = errors.length;
          const tooltipMessage = errors.join('\n');

          $field.css({'border': '2px solid red', 'background-color': '#f8d7da'});

          const $tooltipWrapper = $('<span class="tooltip-wrapper"></span>');
          const $errorIcon = $('<span class="namespace-error-icon">' + errorCount + '</span>');
          const $tooltipText = $('<span class="custom-tooltip-text">' + tooltipMessage + '</span>');
          $tooltipWrapper.append($errorIcon).append($tooltipText);
          $field.after($tooltipWrapper);
        }
      }
    }
  }

  // 1. Function for the Validation button click (AJAX)
  $.fn.processValidationResults = function(results) {
    applyValidationUI(results);
  };

  // 2. Function to persist validation on tab changes
  Drupal.behaviors.semValidationPersistence = {
    attach: function (context, settings) {

      // ---- THIS IS THE CORRECTED PART ----
      // We use the new 'once()' syntax.
      // We select 'body' as an anchor element to run this code only once
      // per page load or AJAX update.
      const elements = once('sem-validation-init', 'body', context);

      // 'once' returns an array of elements. If it found 'body'...
      if (elements.length > 0) {
        // We check if PHP passed us any saved validation results
        if (settings.semValidation && settings.semValidation.results) {
          applyValidationUI(settings.semValidation.results);
        }
      }
    }
  };

// We pass the global 'once' into our function
})(jQuery, Drupal, drupalSettings, once);