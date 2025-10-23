<?php

namespace Drupal\sem\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;

/**
 * Handles validation logic for the EditSemanticDataDictionaryForm.
 */
trait SemanticDataDictionaryValidationTrait {

  /**
   * AJAX callback for the validation button.
   */
  // In SemanticDataDictionaryValidationTrait.php
 public function validateFormCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // --- 1. GET ALL DATA FROM STATE ---
    $currentState = $form_state->getValue('state');
    if ($currentState === 'basic') { $this->updateBasic($form_state); }
    if ($currentState === 'dictionary') { $this->updateVariables($form_state); $this->updateObjects($form_state); }
    if ($currentState === 'codebook') { $this->updateCodes($form_state); }

    $basic = \Drupal::state()->get('my_form_basic');
    $variables = \Drupal::state()->get('my_form_variables') ?? [];
    $objects = \Drupal::state()->get('my_form_objects') ?? [];

    // --- 2. GET VALID NAMESPACES (RULE RESTORED) ---
    $tables = new \Drupal\rep\Entity\Tables();
    $namespaces_array = $tables->getNamespaces();
    $valid_prefixes = [];
    if (is_array($namespaces_array)) {
        $valid_prefixes = array_keys($namespaces_array);
    }

    // --- 3. INITIALIZE COUNTERS AND DETAILED ERRORS ARRAY ---
    $error_counts = ['basic' => 0, 'dictionary' => 0, 'codebook' => 0];
    $detailed_errors = [];

    // --- 4. PERFORM VALIDATION ---

    // Basic Tab Validation
    if (empty(trim($basic['name']))) {
        $error_counts['basic']++;
    }

    // Data Dictionary Tab Validation with CORRECTED logic
    // a) Variables
    foreach ($variables as $index => $variable) {
        if (empty(trim($variable['column']))) { $detailed_errors['variable_column_' . $index][] = $this->t('This field cannot be empty.'); }
        if (empty(trim($variable['attribute']))) { $detailed_errors['variable_attribute_' . $index][] = $this->t('This field cannot be empty.'); }
        if (empty(trim($variable['is_attribute_of']))) { $detailed_errors['variable_is_attribute_of_' . $index][] = $this->t('This field cannot be empty.'); }

        $fields_to_check_namespace = ['attribute', 'is_attribute_of', 'unit', 'time', 'in_relation_to', 'was_derived_from'];
        foreach ($fields_to_check_namespace as $field) {
            $value = trim($variable[$field]);
            if (!empty($value)) {
                if (str_contains($value, ':')) {
                    $parts = explode(':', $value, 2);
                    $prefix = $parts[0];
                    if (!in_array($prefix, $valid_prefixes)) {
                        $detailed_errors['variable_' . $field . '_' . $index][] = $this->t('The prefix "@prefix" is not a valid namespace.', ['@prefix' => $prefix]);
                    }
                } else {
                    $detailed_errors['variable_' . $field . '_' . $index][] = $this->t('The value must be in the format "prefix:value".');
                }
            }
        }
    }

    // b) Objects
    foreach ($objects as $index => $object) {
        if (empty(trim($object['column']))) { $detailed_errors['object_column_' . $index][] = $this->t('This field cannot be empty.'); }
        if (empty(trim($object['entity']))) { $detailed_errors['object_entity_' . $index][] = $this->t('This field cannot be empty.'); }

        $fields_to_check_namespace = ['entity', 'role', 'relation', 'in_relation_to', 'was_derived_from'];
        foreach ($fields_to_check_namespace as $field) {
            $value = trim($object[$field]);
            if (!empty($value)) {
                if (str_contains($value, ':')) {
                    $parts = explode(':', $value, 2);
                    $prefix = $parts[0];
                    if (!in_array($prefix, $valid_prefixes)) {
                        $detailed_errors['object_' . $field . '_' . $index][] = $this->t('The prefix "@prefix" is not a valid namespace.', ['@prefix' => $prefix]);
                    }
                } else {
                    $detailed_errors['object_' . $field . '_' . $index][] = $this->t('The value must be in the format "prefix:value".');
                }
            }
        }
    }

    $error_counts['dictionary'] = count($detailed_errors);

    // --- 5. PREPARE RESPONSE FOR JAVASCRIPT ---
    $total_errors = $error_counts['basic'] + $error_counts['dictionary'] + $error_counts['codebook'];

    $validation_results = ['error_counts' => $error_counts, 'detailed_errors' => $detailed_errors];

    $response->addCommand(new InvokeCommand(NULL, 'processValidationResults', [$validation_results]));

    if ($total_errors > 0) {
        $message = $this->t('Validation complete: @count errors found.', ['@count' => $total_errors]);
        $response->addCommand(new MessageCommand($message, NULL, ['type' => 'error']));
    } else {
        $message = $this->t('Validation complete: No errors were found.');
        $response->addCommand(new MessageCommand($message, NULL, ['type' => 'status']));
    }

    return $response;
}
}