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
// Em SemanticDataDictionaryValidationTrait.php

public function validateFormCallback(array &$form, FormStateInterface $form_state) {
  try {
    $response = new AjaxResponse();

    // 1. A ÚNICA TAREFA DO PHP: Obter a lista de prefixos válidos.
    $tables = new \Drupal\rep\Entity\Tables();
    $namespaces_array = $tables->getNamespaces();
    $valid_prefixes = [];
    if (is_array($namespaces_array)) {
      $valid_prefixes = array_keys($namespaces_array);
    }

    // 2. ENVIAR A LISTA PARA A PÁGINA:
    // Nós "escondemos" a lista de prefixos num atributo 'data-valid-prefixes'
    // no contentor principal da tabela. O JavaScript vai ler isto.
    $response->addCommand(new InvokeCommand(
      '#dict-wrapper', 
      'attr', 
      ['data-valid-prefixes', json_encode($valid_prefixes)]
    ));

    return $response;

  } catch (\Throwable $e) {
    \Drupal::logger('sem_validation')->error($e->getMessage());
    $response = new AjaxResponse();
    $response->addCommand(new MessageCommand($this->t('Ocorreu um erro ao obter os dados de validação.'), NULL, ['type' => 'error']));
    return $response;
  }
}
}