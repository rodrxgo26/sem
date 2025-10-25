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

    // --- 1. SAVE THE CURRENT STATE FIRST ---
    // This is the crucial fix. We must save any changes
    // in the current tab BEFORE validating everything.
    $currentState = $form_state->getValue('state');
    if ($currentState === 'basic') { 
        $this->updateBasic($form_state); 
    }
    if ($currentState === 'dictionary') { 
        $this->updateVariables($form_state); 
        $this->updateObjects($form_state); 
    }
    if ($currentState === 'codebook') { 
        $this->updateCodes($form_state); 
    }

    // --- 2. GET THE *UPDATED* DATA FROM STATE ---
    $basic = \Drupal::state()->get('my_form_basic');
    $variables = \Drupal::state()->get('my_form_variables') ?? [];
    $objects = \Drupal::state()->get('my_form_objects') ?? [];

    // --- 3. GET VALID NAMESPACES ---
    $tables = new \Drupal\rep\Entity\Tables();
    $namespaces_array = $tables->getNamespaces();
    $valid_prefixes = [];
    if (is_array($namespaces_array)) {
        $valid_prefixes = array_keys($namespaces_array);
    }

    // --- 4. INITIALIZE COUNTERS ---
    $error_counts = ['basic' => 0, 'dictionary' => 0, 'codebook' => 0];
    $detailed_errors = [];
    // Array for the correct count (15 instead of 16)
    $dictionary_error_fields = []; 

    // --- 5. EXECUTE VALIDATION ---

    // "Basic" Validation
    if (empty(trim($basic['name']))) {
        $error_counts['basic']++;
        $detailed_errors['semantic_data_dictionary_name'][] = $this->t('This field cannot be empty.');
    }

    // "Data Dictionary" Validation
    // a) Variables
    foreach ($variables as $index => $variable) {
        if (empty(trim($variable['column']))) { 
            $detailed_errors['variable_column_' . $index][] = $this->t('This field cannot be empty.'); 
            $dictionary_error_fields['variable_column_' . $index] = TRUE;
        }
        if (empty(trim($variable['attribute']))) { 
            $detailed_errors['variable_attribute_' . $index][] = $this->t('This field cannot be empty.'); 
            $dictionary_error_fields['variable_attribute_' . $index] = TRUE;
        }
        if (empty(trim($variable['is_attribute_of']))) { 
            $detailed_errors['variable_is_attribute_of_' . $index][] = $this->t('This field cannot be empty.'); 
            $dictionary_error_fields['variable_is_attribute_of_' . $index] = TRUE;
        }

        $fields_to_check_namespace = ['attribute', 'is_attribute_of', 'unit', 'time', 'in_relation_to', 'was_derived_from'];
        foreach ($fields_to_check_namespace as $field) {
            $value = trim($variable[$field]);
            if (!empty($value)) {
                if (str_contains($value, ':')) {
                    $parts = explode(':', $value, 2);
                    $prefix = $parts[0];
                    if (!in_array($prefix, $valid_prefixes)) {
                        $detailed_errors['variable_' . $field . '_' . $index][] = $this->t('The prefix "@prefix" is not a valid namespace.', ['@prefix' => $prefix]);
                        $dictionary_error_fields['variable_' . $field . '_' . $index] = TRUE;
                    }
                } else {
                    $detailed_errors['variable_' . $field . '_' . $index][] = $this->t('The value must be in the format "prefix:value".');
                    $dictionary_error_fields['variable_' . $field . '_' . $index] = TRUE;
                }
            }
        }
    }
    // b) Objects
    foreach ($objects as $index => $object) {
        if (empty(trim($object['column']))) { 
            $detailed_errors['object_column_' . $index][] = $this->t('This field cannot be empty.'); 
            $dictionary_error_fields['object_column_' . $index] = TRUE;
        }
        if (empty(trim($object['entity']))) { 
            $detailed_errors['object_entity_' . $index][] = $this->t('This field cannot be empty.'); 
            $dictionary_error_fields['object_entity_' . $index] = TRUE;
        }

        $fields_to_check_namespace = ['entity', 'role', 'relation', 'in_relation_to', 'was_derived_from'];
        foreach ($fields_to_check_namespace as $field) {
            $value = trim($object[$field]);
            if (!empty($value)) {
                if (str_contains($value, ':')) {
                    $parts = explode(':', $value, 2);
                    $prefix = $parts[0];
                    if (!in_array($prefix, $valid_prefixes)) {
                        $detailed_errors['object_' . $field . '_' . $index][] = $this->t('The prefix "@prefix" is not a valid namespace.', ['@prefix' => $prefix]);
                        $dictionary_error_fields['object_' . $field . '_' . $index] = TRUE;
                    }
                } else {
                    $detailed_errors['object_' . $field . '_' . $index][] = $this->t('The value must be in the format "prefix:value".');
                    $dictionary_error_fields['object_' . $field . '_' . $index] = TRUE;
                }
            }
        }
    }

    // Correct count for the "Data Dictionary"
    $error_counts['dictionary'] = count($dictionary_error_fields); 

    // --- 6. PREPARE RESPONSE ---
    $total_errors = $error_counts['basic'] + $error_counts['dictionary'] + $error_counts['codebook'];

    $validation_results = ['error_counts' => $error_counts, 'detailed_errors' => $detailed_errors];

    // Save results to state for persistence
    \Drupal::state()->set('my_form_validation_results', $validation_results);

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