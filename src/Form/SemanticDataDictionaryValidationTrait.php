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
    try {
      $response = new AjaxResponse();

      // 1. PHP'S ONLY TASK: Get the list of valid prefixes.
      $tables = new \Drupal\rep\Entity\Tables();
      $namespaces_array = $tables->getNamespaces();
      $valid_prefixes = [];
      if (is_array($namespaces_array)) {
        $valid_prefixes = array_keys($namespaces_array);
      }

      // 2. SEND THE LIST TO THE PAGE:
      // We "hide" the list of prefixes in a 'data-valid-prefixes' attribute
      // on the main table container. JavaScript will read this.
      $response->addCommand(new InvokeCommand(
        '#dict-wrapper', 
        'attr', 
        ['data-valid-prefixes', json_encode($valid_prefixes)]
      ));

      return $response;

    } catch (\Throwable $e) {
      \Drupal::logger('sem_validation')->error($e->getMessage());
      $response = new AjaxResponse();
      $response->addCommand(new MessageCommand($this->t('An error occurred while fetching validation data.'), NULL, ['type' => 'error']));
      return $response;
    }
  }
}