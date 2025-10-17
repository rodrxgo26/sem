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
    
    fieldsToValidate.css({'border': '', 'background-color': ''});
    $('.drupal-message').remove(); // Remove mensagens de validações anteriores

    fieldsToValidate.each(function () {
      if (!checkNamespace($(this).val())) {
        invalidFields.push($(this));
      }
    });

    const messages = new Drupal.Message();
    if (invalidFields.length > 0) {
      invalidFields.forEach(function($field) {
        $field.css({'border': '2px solid red', 'background-color': '#f8d7da'});
      });
      // --- MENSAGEM DE ERRO ALTERADA ---
      messages.add('Validation complete: Errors were found.', { type: 'error' });
    } else {
      // --- MENSAGEM DE SUCESSO ALTERADA ---
      messages.add('Validation complete: No errors were found.', { type: 'status' });
    }
  });

})(jQuery, Drupal);