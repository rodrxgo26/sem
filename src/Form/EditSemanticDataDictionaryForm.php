<?php

namespace Drupal\sem\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\HASCO;
use Drupal\rep\Vocabulary\REPGUI;

class EditSemanticDataDictionaryForm extends FormBase {

  protected $state;

  protected $semanticDataDictionary;

  public function getState() {
    return $this->state;
  }
  public function setState($state) {
    return $this->state = $state;
  }

  public function getSemanticDataDictionary() {
    return $this->semanticDataDictionary;
  }
  public function setSemanticDataDictionary($semanticDataDictionary) {
    return $this->semanticDataDictionary = $semanticDataDictionary;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_semantic_data_dictionary_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $state=NULL, $uri=NULL) {

    // INITIALIZE NS TABLE
    $tables = new Tables;
    $namespaces = $tables->getNamespaces();

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';
    $form['#attached']['library'][] = 'core/drupal.ajax';

    // Header com título à esquerda e select à direita
    $form['header'] = [
      '#type' => 'container',
      '#attributes' => [
        // (supondo Bootstrap 4/5)
        'class' => ['d-flex', 'justify-content-between', 'align-items-center', 'mb-4'],
      ],
    ];

    // Título
    $form['header']['title'] = [
      '#type'   => 'markup',
      '#markup' => '<h3 class="mt-3 mb-5">' . $this->t('Edit Semantic Data Dictionary') . '</h3>',
    ];

   // Display Mode + Validation button (side by side)
      $display_mode = $form_state->getValue('display_mode', 'prefix:uri');

      $form['header']['controls'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'align-items-center', 'gap-2',],
          'style' => 'max-width: 520px;',
        ],
      ];

      // Validation button
     $form['header']['controls']['validation_button'] = [
  '#type' => 'button',
  '#value' => $this->t('Validation'),
  '#ajax' => [
    'callback' => '::validateFormCallback',
    'event' => 'click',
    'progress' => [
      'type' => 'throbber',
      'message' => $this->t('Validating...'),
    ],
    'disable-refocus' => TRUE,
    'dialog' => ['close' => FALSE, 'update' => FALSE],
  ],
  '#limit_validation_errors' => [],
  '#executes_submit_callback' => FALSE,
];



      // Select Display Mode
      $form['header']['controls']['display_mode'] = [
        '#type'          => 'select',
        '#title'         => $this->t('Display Mode'),
        '#options'       => [
          'prefix:uri'   => $this->t('Prefix: URI'),
          'prefix:label' => $this->t('Prefix: Label'),
          'label'        => $this->t('Just Label'),
        ],
        '#default_value' => $display_mode,
        '#wrapper_attributes' => [
          'style' => 'max-width: 350px;',
        ],
        '#ajax' => [
          'callback' => '::displayModeAjaxCallback',
          'event'    => 'change',
          'wrapper'  => 'dict-wrapper',
          'progress' => ['type' => 'throbber'],
        ],
      ];

    if ($state === 'init') {
      // READ SEMANTIC_DATA_DICTIONARY
      $api = \Drupal::service('rep.api_connector');
      $uri_decode=base64_decode($uri);
      $semanticDataDictionary = $api->parseObjectResponse($api->getUri($uri_decode),'getUri');
      if ($semanticDataDictionary == NULL) {
        \Drupal::messenger()->addMessage(t("Failed to retrieve Semantic Data Dictionary."));
        self::backUrl();
        return;
      } else {
        $this->setSemanticDataDictionary($semanticDataDictionary);
        //dpm($this->getSemanticDataDictionary());
      }

      // RESET STATE TO BASIC
      $state = 'basic';

      // POPULATE DATA STRUCTURES
      $basic = $this->populateBasic();
      $variables = $this->populateVariables($namespaces);
      $objects = $this->populateObjects($namespaces);
      $codes = $this->populateCodes($namespaces);

    } else {

      $basic = \Drupal::state()->get('my_form_basic');
      $variables = \Drupal::state()->get('my_form_variables') ?? [];
      $objects = \Drupal::state()->get('my_form_objects') ?? [];
      $codes = \Drupal::state()->get('my_form_codes') ?? [];

    }

    // SAVE STATE
    $this->setState($state);

    // SET SEPARATOR
    $separator = '<div class="w-100"></div>';

    $form['current_state'] = [
      '#type' => 'hidden',
      '#value' => $state,
    ];

    // Container for pills and content.
    $form['pills_card'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nav', 'nav-pills', 'nav-justified', 'mb-3'],
        'id' => 'pills-card-container',
        'role' => 'tablist',
      ],
    ];

    // Define pills as links with AJAX callback.
    $states = [
      'basic' => 'Basic variables',
      'dictionary' => 'Data Dictionary',
      'codebook' => 'Codebook'
    ];

    foreach ($states as $key => $label) {
      $form['pills_card'][$key] = [
        '#type' => 'button',
        '#value' => $label,
        '#name' => 'button_' . $key,
        '#attributes' => [
          'class' => ['nav-link', $state === $key ? 'active' : ''],
          'data-state' => $key,
          'role' => 'presentation',
        ],
        '#ajax' => [
          'callback' => '::pills_card_callback',
          'event' => 'click',
          'wrapper' => 'pills-card-container',
          'progress' => ['type' => 'none'],
        ],
      ];
    }

    // Add a hidden field to capture the current state.
    $form['state'] = [
      '#type' => 'hidden',
      '#value' => $state,
    ];

    /* ========================== BASIC ========================= */

    if ($this->getState() == 'basic') {

      $name = '';
      if (isset($basic['name'])) {
        $name = $basic['name'];
      }
      $version = '';
      if (isset($basic['version'])) {
        $version = $basic['version'];
      }
      $description = '';
      if (isset($basic['description'])) {
        $description = $basic['description'];
      }

      $form['semantic_data_dictionary_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#default_value' => $name,
      ];
      $form['semantic_data_dictionary_version'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Version'),
        '#default_value' => $version,
      ];
      $form['semantic_data_dictionary_description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#default_value' => $description,
      ];

    }

    /* ======================= DICTIONARY ======================= */

    // AJAX target container for display‐mode refresh:
    $form['dict_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'dict-wrapper'],
    ];

    if ($this->getState() == 'dictionary') {

      /*
      *      VARIABLES
      */

      $form['dict_wrapper']['variables_title'] = [
        '#type' => 'markup',
        '#markup' => 'Variables',
      ];

      $form['dict_wrapper']['variables'] = array(
        '#type' => 'container',
        '#title' => $this->t('variables'),
        '#attributes' => array(
          'class' => array('p-3', 'bg-light', 'text-dark', 'row', 'border', 'border-secondary', 'rounded'),
          'id' => 'custom-table-wrapper',
        ),
      );

      $form['dict_wrapper']['variables']['header'] = array(
        '#type' => 'markup',
        '#markup' =>
          '<div class="p-2 col bg-secondary text-white border border-white">Column</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Attribute</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Is Attribute Of</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Unit</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Time</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">In Relation To</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Was Derived From</div>' .
          '<div class="p-2 col-md-1 bg-secondary text-white border border-white">Operations</div>' . $separator,
      );

      $form['dict_wrapper']['variables']['rows'] = $this->renderVariableRows($variables, $display_mode);

      $form['dict_wrapper']['variables']['space_3'] = [
        '#type' => 'markup',
        '#markup' => $separator,
      ];

      $form['dict_wrapper']['variables']['actions']['top'] = array(
        '#type' => 'markup',
        '#markup' => '<div class="p-3 col">',
      );

      $form['dict_wrapper']['variables']['actions']['add_row'] = [
        '#type' => 'submit',
        '#value' => $this->t('New Variable'),
        '#name' => 'new_variable',
        '#attributes' => array('class' => array('btn', 'btn-sm', 'add-element-button')),
      ];

      $form['dict_wrapper']['variables']['actions']['bottom'] = array(
        '#type' => 'markup',
        '#markup' => '</div>' . $separator,
      );

      /*
      *      OBJECTS
      */

      $form['dict_wrapper']['objects_title'] = [
        '#type' => 'markup',
        '#markup' => 'Objects',
      ];

      $form['dict_wrapper']['objects'] = array(
        '#type' => 'container',
        '#title' => $this->t('Objects'),
        '#attributes' => array(
          'class' => array('p-3', 'bg-light', 'text-dark', 'row', 'border', 'border-secondary', 'rounded'),
          'id' => 'custom-table-wrapper',
        ),
      );

      $form['dict_wrapper']['objects']['header'] = array(
        '#type' => 'markup',
        '#markup' =>
          '<div class="p-2 col bg-secondary text-white border border-white">Column</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Entity</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Role</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Relation</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">In Relation To</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Was Derived From</div>' .
          '<div class="p-2 col-md-1 bg-secondary text-white border border-white">Operations</div>' . $separator,
      );

      $form['dict_wrapper']['objects']['rows'] = $this->renderObjectRows($objects, $display_mode);

      $form['dict_wrapper']['objects']['space_3'] = [
        '#type' => 'markup',
        '#markup' => $separator,
      ];

      $form['dict_wrapper']['objects']['actions']['top'] = array(
        '#type' => 'markup',
        '#markup' => '<div class="p-3 col">',
      );

      $form['dict_wrapper']['objects']['actions']['add_row'] = [
        '#type' => 'submit',
        '#value' => $this->t('New Object'),
        '#name' => 'new_object',
        '#attributes' => array('class' => array('btn', 'btn-sm', 'add-element-button')),
      ];

      $form['dict_wrapper']['objects']['actions']['bottom'] = array(
        '#type' => 'markup',
        '#markup' => '</div>' . $separator,
      );

    }

    /* ======================= CODEBOOK ======================= */

    if ($this->getState() == 'codebook') {

      /*
      *      CODES
      */

      $form['dict_wrapper']['codes_title'] = [
        '#type' => 'markup',
        '#markup' => 'Codes',
      ];

      $form['dict_wrapper']['codes'] = array(
        '#type' => 'container',
        '#title' => $this->t('codes'),
        '#attributes' => array(
          'class' => array('p-3', 'bg-light', 'text-dark', 'row', 'border', 'border-secondary', 'rounded'),
          'id' => 'custom-table-wrapper',
        ),
      );

      $form['dict_wrapper']['codes']['header'] = array(
        '#type' => 'markup',
        '#markup' =>
          '<div class="p-2 col bg-secondary text-white border border-white">Column</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Code</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Label</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Class</div>' .
          '<div class="p-2 col-md-1 bg-secondary text-white border border-white">Operations</div>' . $separator,
      );

      $form['dict_wrapper']['codes']['rows'] = $this->renderCodeRows($codes, $display_mode);

      $form['dict_wrapper']['codes']['space_3'] = [
        '#type' => 'markup',
        '#markup' => $separator,
      ];

      $form['dict_wrapper']['codes']['actions']['top'] = array(
        '#type' => 'markup',
        '#markup' => '<div class="p-3 col">',
      );

      $form['dict_wrapper']['codes']['actions']['add_row'] = [
        '#type' => 'submit',
        '#value' => $this->t('New Code'),
        '#name' => 'new_code',
        '#attributes' => array('class' => array('btn', 'btn-sm', 'add-element-button')),
      ];

      $form['dict_wrapper']['codes']['actions']['bottom'] = array(
        '#type' => 'markup',
        '#markup' => '</div>' . $separator,
      );

    }

    /* ======================= COMMON BOTTOM ======================= */

    $form['space'] = [
      '#type' => 'markup',
      '#markup' => '<br><br>',
    ];

    $form['save_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#name' => 'save',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'save-button'],
      ],
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'cancel-button'],
      ],
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br>'),
    ];

    //$form['#attached']['library'][] = 'sem/sem_list';

    return $form;
  }

  public function pills_card_callback(array &$form, FormStateInterface $form_state) {

    // RETRIEVE CURRENT STATE AND SAVE IT ACCORDINGLY
    $currentState = $form_state->getValue('state');
    if ($currentState == 'basic') {
      $this->updateBasic($form_state);
    }
    if ($currentState == 'dictionary') {
      $this->updateVariables($form_state);
      $this->updateObjects($form_state);
    }
    if ($currentState == 'codebook') {
      $this->updateCodes($form_state);
    }

    // Need to retrieve $basic because it contains the semantic data dictionary's URI
    $basic = \Drupal::state()->get('my_form_basic');

    // RETRIEVE FUTURE STATE
    $triggering_element = $form_state->getTriggeringElement();
    $parts = explode('_', $triggering_element['#name']);
    $state = (isset($parts) && is_array($parts)) ? end($parts) : null;

    // BUILD NEW URL
    $root_url = \Drupal::request()->getBaseUrl();
    $newUrl = $root_url . REPGUI::EDIT_SEMANTIC_DATA_DICTIONARY . $state . '/' . base64_encode($basic['uri']);

    // REDIRECT TO NEW URL
    $response = new AjaxResponse();
    $response->addCommand(new RedirectCommand($newUrl));

    return $response;
  }

 public function validateFormCallback(array &$form, FormStateInterface $form_state) {
  try {
    $response = new AjaxResponse();

    $input = $form_state->getUserInput();
    $invalid_fields = [];

    $tables = new \Drupal\rep\Entity\Tables();
    $namespaces_array = $tables->getNamespaces();
    
    $prefixString = '';
    if (is_array($namespaces_array)) {
        $prefixString = implode(' ', array_keys($namespaces_array));
    }

    $checkNamespace = function($str) use ($prefixString) {
      if ($str === NULL || $str === '') {
        return TRUE;
      }
      if (strpos($str, ':') !== FALSE) {
        [$prefixname] = explode(':', $str, 2);
        if (strpos($prefixString, $prefixname) === FALSE) {
          return FALSE;
        }
      }
      return TRUE;
    };

    foreach ($input as $name => $value) {
      if (preg_match('/_(attribute|is_attribute_of|unit|entity|role|relation|class)$/', $name)) {
        if (!$checkNamespace($value)) {
          $invalid_fields[] = $name;
        }
      }
    }

    $response->addCommand(new InvokeCommand('input[name^="variable_"], input[name^="object_"], input[name^="code_"]', 'css', ['border', '']));
    $response->addCommand(new InvokeCommand('input[name^="variable_"], input[name^="object_"], input[name^="code_"]', 'css', ['background-color', '']));

    if (!empty($invalid_fields)) {
      foreach ($invalid_fields as $field) {
        $response->addCommand(new InvokeCommand("input[name='$field']", 'css', ['border', '2px solid red']));
        $response->addCommand(new InvokeCommand("input[name='$field']", 'css', ['background-color', '#f8d7da']));
      }
      $response->addCommand(new MessageCommand($this->t('Validation failed: Some fields do not respect namespace rules.'), NULL, ['type' => 'error']));
    } else {
      $response->addCommand(new MessageCommand($this->t('Validation successful: All fields respect the namespace rules.'), NULL, ['type' => 'status']));
    }

    return $response;

  } catch (\Throwable $e) {
    \Drupal::logger('sem_validation')->error($e->getMessage());
    $response = new AjaxResponse();
    $response->addCommand(new MessageCommand($this->t('A critical error occurred during validation: @msg', ['@msg' => $e->getMessage()]), NULL, ['type' => 'error']));
    return $response;
  }
}

  /******************************
   *
   *    BASIC'S FUNCTIONS
   *
   ******************************/

  /**
   * {@inheritdoc}
   */
  public function updateBasic(FormStateInterface $form_state) {
    $basic = \Drupal::state()->get('my_form_basic');
    $input = $form_state->getUserInput();

    if (isset($input) && is_array($input) &&
        isset($basic) && is_array($basic)) {
      $basic['name']        = $input['semantic_data_dictionary_name'] ?? '';
      $basic['version']     = $input['semantic_data_dictionary_version'] ?? '';
      $basic['description'] = $input['semantic_data_dictionary_description'] ?? '';
      \Drupal::state()->set('my_form_basic', $basic);
    }
    $response = new AjaxResponse();
    return $response;
  }

  public function populateBasic() {
    $basic = [
      'uri' => $this->getSemanticDataDictionary()->uri,
      'name' => $this->getSemanticDataDictionary()->label,
      'version' => $this->getSemanticDataDictionary()->hasVersion,
      'description' => $this->getSemanticDataDictionary()->comment,
    ];
    \Drupal::state()->set('my_form_basic', $basic);
    return $basic;
  }

  /******************************
   *
   *    variables' FUNCTIONS
   *
   ******************************/

  /**
 * Render variable rows with Bootstrap structure and display-mode toggling.
 *
 * @param array  $variables
 *   Each item: [
 *     'column',
 *     'attribute',
 *     'is_attribute_of',
 *     'unit',
 *     'time',
 *     'in_relation_to',
 *     'was_derived_from',
 *   ]
 * @param string $mode
 *   Display mode: 'prefix:label', 'just label', 'uri', 'prefix:uri'.
 *
 * @return array
 *   Renderable rows.
 */
  protected function renderVariableRows(array $variables, string $mode = 'prefix:uri'): array {
    $rows = [];
    $sep  = '<div class="w-100"></div>';

    foreach ($variables as $delta => $v) {

      $form_row = [
        'column' => [
          'top' => ['#type'=>'markup', '#markup'=>'<div class="pt-3 col border border-white">'],
          'main' => ['#type'=>'textfield', '#name'=>"variable_column_$delta", '#value'=>$v['column'],],
          'bottom' => ['#type'=>'markup', '#markup'=>'</div>'],
        ],
        'attribute' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'variable_attribute_' . $delta,
            '#id' => 'variable_attribute_' . $delta,
            '#value' => $this->formatDisplay($v['attribute'], $mode),
            '#attributes' => [
              'class' => ['open-tree-modal'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 800]),
              'data-url' => Url::fromRoute('rep.tree_form', [
                'mode' => 'modal',
                'elementtype' => 'attribute',
                'silent' => true,
                'prefix' => true,
              ], [
                'query' => [
                  'field_id'     => 'variable_attribute_' . $delta,
                  'search_value' => UTILS::plainUri($this->formatDisplay($v['attribute'], 'plain:label')),
                ],
              ])->toString(),
              'data-field-id'    => 'variable_attribute_' . $delta,
              'data-search-value'=> $v['attribute'],
              'data-elementtype' => 'attribute',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        // 'attribute' => [
        //   'top' => ['#type'=>'markup', '#markup'=>'<div class="pt-3 col border border-white">'],
        //   'main' => [
        //     '#type'=>'textfield',
        //     '#name'=>"variable_attribute_$delta",
        //     '#value'=>$this->formatDisplay($v['attribute'], $mode),
        //     '#attributes'=>[
        //       'data-original-value'=>$v['attribute'],
        //       'data-label'=>$v['column'],
        //       'class'=>['open-tree-modal'],
        //       'data-dialog-type'=>'modal',
        //       'data-dialog-options'=>json_encode(['width'=>800]),
        //       'data-url'=>Url::fromRoute('rep.tree_form',['mode'=>'modal','elementtype'=>'attribute'],['query'=>['field_id'=>"variable_attribute_$delta"]])->toString(),
        //       'data-field-id'=>"variable_attribute_$delta",
        //       'data-search-value'=>$v['attribute'],
        //       'data-elementtype'=>'attribute',
        //     ],
        //   ],
        //   'bottom'=>['#type'=>'markup','#markup'=>'</div>'],
        // ],
        'is_attribute_of' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'variable_is_attribute_of_' . $delta,
            '#id' => 'variable_is_attribute_of_' . $delta,
            '#value' => $this->formatDisplay($v['is_attribute_of'], $mode),
            '#attributes' => [
              'class' => ['open-tree-modal'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 800]),
              'data-url' => Url::fromRoute('rep.tree_form', [
                'mode' => 'modal',
                'elementtype' => 'entity',
                'silent' => true,
                'prefix' => true,
              ], [
                'query' => [
                  'field_id'     => 'variable_is_attribute_of_' . $delta,
                  'search_value' => UTILS::plainUri($this->formatDisplay($v['is_attribute_of'], 'plain:label')),
                ],
              ])->toString(),
              'data-field-id'    => 'variable_is_attribute_of_' . $delta,
              'data-search-value'=> $v['is_attribute_of'],
              'data-elementtype' => 'entity',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        // 'is_attribute_of'=>[
        //   'top'=>['#type'=>'markup','#markup'=>'<div class="pt-3 col border border-white">'],
        //   'main'=>[
        //     '#type'=>'textfield',
        //     '#name'=>"variable_is_attribute_of_$delta",
        //     '#value'=>$this->formatDisplay($v['is_attribute_of'], $mode),
        //     '#attributes'=>['data-original-value'=>$v['is_attribute_of']],
        //   ],
        //   'bottom'=>['#type'=>'markup','#markup'=>'</div>'],
        // ],
        'unit' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'variable_unit_' . $delta,
            '#id' => 'variable_unit_' . $delta,
            '#value' => $this->formatDisplay($v['unit'], $mode),
            '#attributes' => [
              'class' => ['open-tree-modal'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 800]),
              'data-url' => Url::fromRoute('rep.tree_form', [
                'mode' => 'modal',
                'elementtype' => 'unit',
                'silent' => true,
                'prefix' => true,
              ], [
                'query' => [
                  'field_id'     => 'variable_unit_' . $delta,
                  'search_value' => UTILS::plainUri($this->formatDisplay($v['unit'], 'plain:label')),
                ],
              ])->toString(),
              'data-field-id'    => 'variable_unit_' . $delta,
              'data-search-value'=> $v['unit'],
              'data-elementtype' => 'unit',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        // 'unit'=>[
        //   'top'=>['#type'=>'markup','#markup'=>'<div class="pt-3 col border border-white">'],
        //   'main'=>[
        //     '#type'=>'textfield',
        //     '#name'=>"variable_unit_$delta",
        //     '#value'=>$this->formatDisplay($v['unit'], $mode),
        //     '#attributes'=>[
        //       'data-original-value'=>$v['unit'],
        //       'class'=>['open-tree-modal'],
        //       'data-dialog-type'=>'modal',
        //       'data-dialog-options'=>json_encode(['width'=>800]),
        //       'data-url'=>Url::fromRoute('rep.tree_form',['mode'=>'modal','elementtype'=>'unit'],['query'=>['field_id'=>"variable_unit_$delta"]])->toString(),
        //       'data-field-id'=>"variable_unit_$delta",
        //       'data-search-value'=>$v['unit'],
        //       'data-elementtype'=>'unit',
        //     ],
        //   ],
        //   'bottom'=>['#type'=>'markup','#markup'=>'</div>'],
        // ],
        'time'=>[
          'top'=>['#type'=>'markup','#markup'=>'<div class="pt-3 col border border-white">'],
          'main'=>[
            '#type'=>'textfield',
            '#name'=>"variable_time_$delta",
            '#value'=>$this->formatDisplay($v['time'], $v['column'], $mode),
            '#attributes'=>['data-original-value'=>$v['time']],
          ],
          'bottom'=>['#type'=>'markup','#markup'=>'</div>'],
        ],
        'in_relation_to' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'variable_in_relation_to_' . $delta,
            '#id' => 'variable_in_relation_to_' . $delta,
            '#value' => $this->formatDisplay($v['in_relation_to'], $mode),
            '#attributes' => [
              'class' => ['open-tree-modal'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 800]),
              'data-url' => Url::fromRoute('rep.tree_form', [
                'mode' => 'modal',
                'elementtype' => 'entity',
                'silent' => true,
                'prefix' => true,
              ], [
                'query' => [
                  'field_id'     => 'variable_in_relation_to_' . $delta,
                  'search_value' => UTILS::plainUri($this->formatDisplay($v['in_relation_to'], 'plain:label')),
                ],
              ])->toString(),
              'data-field-id'    => 'variable_in_relation_to_' . $delta,
              'data-search-value'=> $v['in_relation_to'],
              'data-elementtype' => 'entity',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        // 'in_relation_to'=>[
        //   'top'=>['#type'=>'markup','#markup'=>'<div class="pt-3 col border border-white">'],
        //   'main'=>[
        //     '#type'=>'textfield',
        //     '#name'=>"variable_in_relation_to_$delta",
        //     '#value'=>$this->formatDisplay($v['in_relation_to'], $mode),
        //     '#attributes'=>['data-original-value'=>$v['in_relation_to']],
        //   ],
        //   'bottom'=>['#type'=>'markup','#markup'=>'</div>'],
        // ],
        'was_derived_from' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'variable_was_derived_from_' . $delta,
            '#id' => 'variable_was_derived_from_' . $delta,
            '#value' => $this->formatDisplay($v['was_derived_from'], $mode),
            '#attributes' => [
              'class' => ['open-tree-modal'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 800]),
              'data-url' => Url::fromRoute('rep.tree_form', [
                'mode' => 'modal',
                'elementtype' => 'entity',
                'silent' => true,
                'prefix' => true,
              ], [
                'query' => [
                  'field_id'     => 'variable_was_derived_from_' . $delta,
                  'search_value' => UTILS::plainUri($this->formatDisplay($v['was_derived_from'], 'plain:label')),
                ],
              ])->toString(),
              'data-field-id'    => 'variable_was_derived_from_' . $delta,
              'data-search-value'=> $v['was_derived_from'],
              'data-elementtype' => 'entity',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        // 'was_derived_from'=>[
        //   'top'=>['#type'=>'markup','#markup'=>'<div class="pt-3 col border border-white">'],
        //   'main'=>[
        //     '#type'=>'textfield',
        //     '#name'=>"variable_was_derived_from_$delta",
        //     '#value'=>$this->formatDisplay($v['was_derived_from'], $mode),
        //     '#attributes'=>['data-original-value'=>$v['was_derived_from']],
        //   ],
        //   'bottom'=>['#type'=>'markup','#markup'=>'</div>'],
        // ],
        'operations'=>[
          'top'=>['#type'=>'markup','#markup'=>'<div class="pt-3 col-md-1 border border-white">'],
          'main'=>[
            '#type'=>'submit',
            '#name'=>"variable_remove_$delta",
            '#value'=>$this->t('Remove'),
            '#attributes'=>['class'=>['remove-row','btn','btn-sm','delete-element-button'],'id'=>"variable-$delta"],
          ],
          'bottom'=>['#type'=>'markup','#markup'=>"</div>$sep"],
        ],
      ];

      $rows[] = [$delta => $form_row];
    }

    return $rows;
  }

  protected function updateVariables(FormStateInterface $form_state) {
    $variables = \Drupal::state()->get('my_form_variables');
    $input = $form_state->getUserInput();
    if (isset($input) && is_array($input) &&
        isset($variables) && is_array($variables)) {

      foreach ($variables as $variable_id => $variable) {
        if (isset($variable_id) && isset($variable)) {
          $variables[$variable_id]['column']            = $input['variable_column_' . $variable_id] ?? '';
          $variables[$variable_id]['attribute']         = $input['variable_attribute_' . $variable_id] ?? '';
          $variables[$variable_id]['is_attribute_of']   = $input['variable_is_attribute_of_' . $variable_id] ?? '';
          $variables[$variable_id]['unit']              = $input['variable_unit_' . $variable_id] ?? '';
          $variables[$variable_id]['time']              = $input['variable_time_' . $variable_id] ?? '';
          $variables[$variable_id]['in_relation_to']    = $input['variable_in_relation_to_' . $variable_id] ?? '';
          $variables[$variable_id]['was_derived_from']  = $input['variable_was_derived_from_' . $variable_id] ?? '';
        }
      }
      \Drupal::state()->set('my_form_variables', $variables);
    }
    return;
  }

  protected function populateVariables($namespaces) {
    $variables = [];
    $attributes = $this->getSemanticDataDictionary()->attributes;
    if (count($attributes) > 0) {
      foreach ($attributes as $attribute_id => $attribute) {
        if (isset($attribute_id) && isset($attribute)) {
          $listPosition = $attribute->listPosition;
          $variables[$listPosition]['column']            = $attribute->label;
          $variables[$listPosition]['attribute']         = Utils::namespaceUriWithNS($attribute->attribute,$namespaces);
          $variables[$listPosition]['is_attribute_of']   = $attribute->objectUri;
          $variables[$listPosition]['unit']              = Utils::namespaceUriWithNS($attribute->unit,$namespaces);
          $variables[$listPosition]['time']              = Utils::namespaceUriWithNS($attribute->eventUri,$namespaces);
          $variables[$listPosition]['in_relation_to']    = $attribute->inRelationTo;
          $variables[$listPosition]['was_derived_from']  = $attribute->wasDerivedFrom;
        }
      }
      ksort($variables);
    }
    \Drupal::state()->set('my_form_variables', $variables);

    return $variables;
  }

  protected function saveVariables($semanticDataDictionaryUri, array $variables) {
    if (!isset($semanticDataDictionaryUri)) {
      \Drupal::messenger()->addError(t("No semantic data dictionary's URI have been provided to save variables."));
      return;
    }
    if (!isset($variables) || !is_array($variables)) {
      \Drupal::messenger()->addWarning(t("Semantic data dictionary has no variable to be saved."));
      return;
    }

    foreach ($variables as $variable_id => $variable) {
      if (isset($variable_id) && isset($variable)) {
        try {
          $useremail = \Drupal::currentUser()->getEmail();

          $column = ' ';
          if ($variables[$variable_id]['column'] != NULL && $variables[$variable_id]['column'] != '') {
            $column = $variables[$variable_id]['column'];
          }

          $attributeUri = ' ';
          if ($variables[$variable_id]['attribute'] != NULL && $variables[$variable_id]['attribute'] != '') {
            $attributeUri = $variables[$variable_id]['attribute'];
          }

          $isAttributeOf = ' ';
          if ($variables[$variable_id]['is_attribute_of'] != NULL && $variables[$variable_id]['is_attribute_of'] != '') {
            $isAttributeOf = $variables[$variable_id]['is_attribute_of'];
          }

          $unitUri = ' ';
          if ($variables[$variable_id]['unit'] != NULL && $variables[$variable_id]['unit'] != '') {
            $unitUri = $variables[$variable_id]['unit'];
          }

          $timeUri = ' ';
          if ($variables[$variable_id]['time'] != NULL && $variables[$variable_id]['time'] != '') {
            $timeUri = $variables[$variable_id]['time'];
          }

          $inRelationToUri = ' ';
          if ($variables[$variable_id]['in_relation_to'] != NULL && $variables[$variable_id]['in_relation_to'] != '') {
            $inRelationToUri = $variables[$variable_id]['in_relation_to'];
          }

          $wasDerivedFromUri = ' ';
          if ($variables[$variable_id]['was_derived_from'] != NULL && $variables[$variable_id]['was_derived_from'] != '') {
            $wasDerivedFromUri = $variables[$variable_id]['was_derived_from'];
          }

          $variableUri = str_replace(
            Constant::PREFIX_SEMANTIC_DATA_DICTIONARY,
            Constant::PREFIX_SDD_ATTRIBUTE,
            $semanticDataDictionaryUri) . '/' . $variable_id;
          $variableJSON = '{"uri":"'. $variableUri .'",'.
              '"typeUri":"'.HASCO::SDD_ATTRIBUTE.'",'.
              '"hascoTypeUri":"'.HASCO::SDD_ATTRIBUTE.'",'.
              '"partOfSchema":"'.$semanticDataDictionaryUri.'",'.
              '"listPosition":"'.$variable_id.'",'.
              '"label":"'.$column.'",'.
              '"attribute":"' . $attributeUri . '",' .
              '"objectUri":"' . $isAttributeOf . '",' .
              '"unit":"' . $unitUri . '",' .
              '"eventUri":"' . $timeUri . '",' .
              '"inRelationTo":"' . $inRelationToUri . '",' .
              '"wasDerivedFrom":"' . $wasDerivedFromUri . '",' .
              '"comment":"Column ' . $column . ' of ' . $semanticDataDictionaryUri . '",'.
              '"hasSIRManagerEmail":"'.$useremail.'"}';
          $api = \Drupal::service('rep.api_connector');
          $api->elementAdd('sddattribute',$variableJSON);

          //dpm($variableJSON);

        } catch(\Exception $e){
          \Drupal::messenger()->addError(t("An error occurred while saving the semantic data dictionary: ".$e->getMessage()));
        }
      }
    }
    return;
  }

  public function addVariableRow() {
    $variables = \Drupal::state()->get('my_form_variables') ?? [];

    // Add a new row to the table.
    $variables[] = [
      'column' => '',
      'attribute' => '',
      'is_attribute_of' => '',
      'unit' => '',
      'time' => '',
      'in_relation_to' => '',
      'was_derived_from' => '',
    ];
    \Drupal::state()->set('my_form_variables', $variables);

    // Rebuild the table rows.
    $form['variables']['rows'] = $this->renderVariableRows($variables);
    return;
  }

  public function removeVariableRow($button_name) {
    $variables = \Drupal::state()->get('my_form_variables') ?? [];

    // from button name's value, determine which row to remove.
    $parts = explode('_', $button_name);
    $variable_to_remove = (isset($parts) && is_array($parts)) ? (int) (end($parts)) : null;

    if (isset($variable_to_remove) && $variable_to_remove > -1) {
      unset($variables[$variable_to_remove]);
      $variables = array_values($variables);
      \Drupal::state()->set('my_form_variables', $variables);
    }
    return;
  }

  /******************************
   *
   *    OBJECTS' FUNCTIONS
   *
   ******************************/

  /**
 * Render object rows with Bootstrap structure and display-mode toggling.
 *
 * @param array  $objects
 *   Each item: [
 *     'column',
 *     'entity',
 *     'role',
 *     'relation',
 *     'in_relation_to',
 *     'was_derived_from',
 *   ]
 * @param string $mode
 *   Display mode: 'prefix:label', 'just label', 'uri', 'prefix:uri'.
 *
 * @return array
 *   Renderable rows.
 */
  protected function renderObjectRows(array $objects, string $mode = 'prefix:uri'): array {
    $rows = [];
    $sep  = '<div class="w-100"></div>';

    foreach ($objects as $delta => $o) {
      $display_entity = $this->formatDisplay($o['entity'], $mode);

      $form_row = [
        'column' => [
          'top'    => ['#type'=>'markup', '#markup'=>'<div class="pt-3 col border border-white">'],
          'main'   => ['#type'=>'textfield', '#name'=>"object_column_$delta", '#value'=>$o['column']],
          'bottom' => ['#type'=>'markup', '#markup'=>'</div>'],
        ],
        'entity' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'object_entity_' . $delta,
            '#id' => 'object_entity_' . $delta,
            '#value' => $this->formatDisplay($o['entity'], $mode),
            '#attributes' => [
              'class' => ['open-tree-modal'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 800]),
              'data-url' => Url::fromRoute('rep.tree_form', [
                'mode' => 'modal',
                'elementtype' => 'entity',
                'silent' => true,
                'prefix' => true,
              ], [
                'query' => [
                  'field_id'     => 'object_entity_' . $delta,
                  'search_value' => UTILS::plainUri($this->formatDisplay($o['entity'], 'plain:label')),
                ],
              ])->toString(),
              'data-field-id'    => 'object_entity_' . $delta,
              'data-search-value'=> $o['entity'],
              'data-elementtype' => 'entity',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        // 'entity' => [
        //   'top'    => ['#type'=>'markup', '#markup'=>'<div class="pt-3 col border border-white">'],
        //   'main'   => [
        //     '#type' => 'textfield',
        //     '#name'  => "object_entity_$delta",
        //     '#value' => $display_entity,
        //     '#attributes' => [
        //       'data-original-value' => $o['entity'],
        //       'data-label'          => $o['column'],
        //       'class'               => ['open-tree-modal'],
        //       'data-dialog-type'    => 'modal',
        //       'data-dialog-options' => json_encode(['width' => 800]),
        //       'data-url'            => Url::fromRoute('rep.tree_form', [
        //                                 'mode'        => 'modal',
        //                                 'elementtype' => 'entity',
        //                               ], ['query' => ['field_id' => "object_entity_$delta"]])->toString(),
        //     ],
        //   ],
        //   'bottom' => ['#type'=>'markup', '#markup'=>'</div>'],
        // ],
        'role' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'object_role_' . $delta,
            '#id' => 'object_role_' . $delta,
            '#value' => $this->formatDisplay($o['role'], $mode),
            '#attributes' => [
              'class' => ['open-tree-modal'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 800]),
              'data-url' => Url::fromRoute('rep.tree_form', [
                'mode' => 'modal',
                'elementtype' => 'role',
                'silent' => true,
                'prefix' => true,
              ], [
                'query' => [
                  'field_id'     => 'object_role_' . $delta,
                  'search_value' => UTILS::plainUri($this->formatDisplay($o['role'], 'plain:label')),
                ],
              ])->toString(),
              'data-field-id'    => 'object_role_' . $delta,
              'data-search-value'=> $o['role'],
              'data-elementtype' => 'role',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        // 'role' => [
        //   'top'    => ['#type'=>'markup', '#markup'=>'<div class="pt-3 col border border-white">'],
        //   'main'   => [
        //     '#type'=>'textfield',
        //     '#name'  => "object_role_$delta",
        //     '#value' => $this->formatDisplay($o['role'], $mode),
        //     '#attributes' => ['data-original-value' => $o['role']],
        //   ],
        //   'bottom' => ['#type'=>'markup', '#markup'=>'</div>'],
        // ],
        'relation' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'object_relation_' . $delta,
            '#id' => 'object_relation_' . $delta,
            '#value' => $this->formatDisplay($o['relation'], $mode),
            '#attributes' => [
              'class' => ['open-tree-modal'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 800]),
              'data-url' => Url::fromRoute('rep.tree_form', [
                'mode' => 'modal',
                'elementtype' => 'relation',
                'silent' => true,
                'prefix' => true,
              ], [
                'query' => [
                  'field_id'     => 'object_relation_' . $delta,
                  'search_value' => UTILS::plainUri($this->formatDisplay($o['relation'], 'plain:label')),
                ],
              ])->toString(),
              'data-field-id'    => 'object_relation_' . $delta,
              'data-search-value'=> $o['relation'],
              'data-elementtype' => 'relation',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        // 'relation' => [
        //   'top'    => ['#type'=>'markup', '#markup'=>'<div class="pt-3 col border border-white">'],
        //   'main'   => [
        //     '#type'=>'textfield',
        //     '#name'  => "object_relation_$delta",
        //     '#value' => $this->formatDisplay($o['relation'], $mode),
        //     '#attributes' => ['data-original-value' => $o['relation']],
        //   ],
        //   'bottom' => ['#type'=>'markup', '#markup'=>'</div>'],
        // ],
        'in_relation_to' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'object_in_relation_to_' . $delta,
            '#id' => 'object_in_relation_to_' . $delta,
            '#value' => $this->formatDisplay($o['in_relation_to'], $mode),
            '#attributes' => [
              'class' => ['open-tree-modal'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 800]),
              'data-url' => Url::fromRoute('rep.tree_form', [
                'mode' => 'modal',
                'elementtype' => 'entity',
                'silent' => true,
                'prefix' => true,
              ], [
                'query' => [
                  'field_id'     => 'object_in_relation_to_' . $delta,
                  'search_value' => UTILS::plainUri($this->formatDisplay($o['in_relation_to'], 'plain:label')),
                ],
              ])->toString(),
              'data-field-id'    => 'object_in_relation_to_' . $delta,
              'data-search-value'=> $o['in_relation_to'],
              'data-elementtype' => 'entity',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        // 'in_relation_to' => [
        //   'top'    => ['#type'=>'markup', '#markup'=>'<div class="pt-3 col border border-white">'],
        //   'main'   => [
        //     '#type'=>'textfield',
        //     '#name'  => "object_in_relation_to_$delta",
        //     '#value' => $this->formatDisplay($o['in_relation_to'], $mode),
        //     '#attributes' => ['data-original-value' => $o['in_relation_to']],
        //   ],
        //   'bottom' => ['#type'=>'markup', '#markup'=>'</div>'],
        // ],
        'was_derived_from' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'object_was_derived_from_' . $delta,
            '#id' => 'object_was_derived_from_' . $delta,
            '#value' => $this->formatDisplay($o['was_derived_from'], $mode),
            '#attributes' => [
              'class' => ['open-tree-modal'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 800]),
              'data-url' => Url::fromRoute('rep.tree_form', [
                'mode' => 'modal',
                'elementtype' => 'entity',
                'silent' => true,
                'prefix' => true,
              ], [
                'query' => [
                  'field_id'     => 'object_was_derived_from_' . $delta,
                  'search_value' => UTILS::plainUri($this->formatDisplay($o['was_derived_from'], 'plain:label')),
                ],
              ])->toString(),
              'data-field-id'    => 'object_was_derived_from_' . $delta,
              'data-search-value'=> $o['was_derived_from'],
              'data-elementtype' => 'entity',
            ],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        // 'was_derived_from' => [
        //   'top'    => ['#type'=>'markup', '#markup'=>'<div class="pt-3 col border border-white">'],
        //   'main'   => [
        //     '#type'=>'textfield',
        //     '#name'  => "object_was_derived_from_$delta",
        //     '#value' => $this->formatDisplay($o['was_derived_from'], $mode),
        //     '#attributes' => ['data-original-value' => $o['was_derived_from']],
        //   ],
        //   'bottom' => ['#type'=>'markup', '#markup'=>'</div>'],
        // ],
        'operations' => [
          'top'    => ['#type'=>'markup', '#markup'=>'<div class="pt-3 col-md-1 border border-white">'],
          'main'   => [
            '#type' => 'submit',
            '#name'  => "object_remove_$delta",
            '#value' => $this->t('Remove'),
            '#attributes' => ['class'=>['remove-row','btn','btn-sm','delete-element-button'],'id'=>"object-$delta"],
          ],
          'bottom' => ['#type'=>'markup', '#markup'=>"</div>$sep"],
        ],
      ];

      $rows[] = [$delta => $form_row];
    }

    return $rows;
  }

  protected function updateObjects(FormStateInterface $form_state) {
    $objects = \Drupal::state()->get('my_form_objects');
    $input = $form_state->getUserInput();
    if (isset($input) && is_array($input) &&
        isset($objects) && is_array($objects)) {

      foreach ($objects as $object_id => $object) {
        if (isset($object_id) && isset($object)) {
          $objects[$object_id]['column']            = $input['object_column_' . $object_id] ?? '';
          $objects[$object_id]['entity']            = $input['object_entity_' . $object_id] ?? '';
          $objects[$object_id]['role']              = $input['object_role_' . $object_id] ?? '';
          $objects[$object_id]['relation']          = $input['object_relation_' . $object_id] ?? '';
          $objects[$object_id]['in_relation_to']    = $input['object_in_relation_to_' . $object_id] ?? '';
          $objects[$object_id]['was_derived_from']  = $input['object_was_derived_from_' . $object_id] ?? '';
        }
      }
      \Drupal::state()->set('my_form_objects', $objects);
    }
    return;
  }

  protected function populateObjects($namespaces) {
    $objects = [];
    $objs = $this->getSemanticDataDictionary()->objects;
    if (count($objs) > 0) {
      foreach ($objs as $obj_id => $obj) {
        if (isset($obj_id) && isset($obj)) {
          $listPosition = $obj->listPosition;
          $objects[$listPosition]['column']            = $obj->label;
          $objects[$listPosition]['entity']            = Utils::namespaceUriWithNS($obj->entity,$namespaces);
          $objects[$listPosition]['role']              = Utils::namespaceUriWithNS($obj->role,$namespaces);
          $objects[$listPosition]['relation']          = Utils::namespaceUriWithNS($obj->relation,$namespaces);
          $objects[$listPosition]['in_relation_to']    = $obj->inRelationTo;
          $objects[$listPosition]['was_derived_from']  = $obj->wasDerivedFrom;
        }
      }
      ksort($objects);
    }
    \Drupal::state()->set('my_form_objects', $objects);

    return $objects;
  }

  protected function saveObjects($semanticDataDictionaryUri, array $objects) {
    if (!isset($semanticDataDictionaryUri)) {
      \Drupal::messenger()->addError(t("No semantic data dictionary's URI have been provided to save objects."));
      return;
    }
    if (!isset($objects) || !is_array($objects)) {
      \Drupal::messenger()->addWarning(t("Semantic data dictionary has no objects to be saved."));
      return;
    }

    foreach ($objects as $object_id => $object) {
      if (isset($object_id) && isset($object)) {
        try {
          $useremail = \Drupal::currentUser()->getEmail();

          $column = ' ';
          if ($objects[$object_id]['column'] != NULL && $objects[$object_id]['column'] != '') {
            $column = $objects[$object_id]['column'];
          }

          $entity = ' ';
          if ($objects[$object_id]['entity'] != NULL && $objects[$object_id]['entity'] != '') {
            $entity = $objects[$object_id]['entity'];
          }

          $role = ' ';
          if ($objects[$object_id]['role'] != NULL && $objects[$object_id]['role'] != '') {
            $role = $objects[$object_id]['role'];
          }

          $relation = ' ';
          if ($objects[$object_id]['relation'] != NULL && $objects[$object_id]['relation'] != '') {
            $relation = $objects[$object_id]['relation'];
          }

          $inRelationTo = ' ';
          if ($objects[$object_id]['in_relation_to'] != NULL && $objects[$object_id]['in_relation_to'] != '') {
            $inRelationTo = $objects[$object_id]['in_relation_to'];
          }

          $wasDerivedFrom = ' ';
          if ($objects[$object_id]['was_derived_from'] != NULL && $objects[$object_id]['was_derived_from'] != '') {
            $wasDerivedFrom = $objects[$object_id]['was_derived_from'];
          }

          $objectUri = str_replace(
            Constant::PREFIX_SEMANTIC_DATA_DICTIONARY,
            Constant::PREFIX_SDD_OBJECT,
            $semanticDataDictionaryUri) . '/' . $object_id;
          $objectJSON = '{"uri":"'. $objectUri .'",'.
              '"typeUri":"'.HASCO::SDD_OBJECT.'",'.
              '"hascoTypeUri":"'.HASCO::SDD_OBJECT.'",'.
              '"partOfSchema":"'.$semanticDataDictionaryUri.'",'.
              '"listPosition":"'.$object_id.'",'.
              '"label":"'.$column.'",'.
              '"entity":"' . $entity . '",' .
              '"role":"' . $role . '",' .
              '"relation":"' . $relation . '",' .
              '"inRelationTo":"' . $inRelationTo . '",' .
              '"wasDerivedFrom":"' . $wasDerivedFrom . '",' .
              '"comment":"Column ' . $column . ' of ' . $semanticDataDictionaryUri . '",'.
              '"hasSIRManagerEmail":"'.$useremail.'"}';
          $api = \Drupal::service('rep.api_connector');
          $api->elementAdd('sddobject',$objectJSON);

          //dpm($objectJSON);

        } catch(\Exception $e){
          \Drupal::messenger()->addError(t("An error occurred while saving an SDDObject: ".$e->getMessage()));
        }
      }
    }
    return;
  }

  public function addObjectRow() {

    // Retrieve existing rows from form state or initialize as empty.
    $objects = \Drupal::state()->get('my_form_objects') ?? [];

    // Add a new row to the table.
    $objects[] = [
      'column' => '',
      'entity' => '',
      'role' => '',
      'relation' => '',
      'in_relation_to' => '',
      'was_derived_from' => '',
    ];
    // Update the form state with the new rows.
    \Drupal::state()->set('my_form_objects', $objects);

    // Rebuild the table rows.
    $form['objects']['rows'] = $this->renderObjectRows($objects);

  }

  public function removeObjectRow($button_name) {
    $objects = \Drupal::state()->get('my_form_objects') ?? [];

    // from button name's value, determine which row to remove.
    $parts = explode('_', $button_name);
    $object_to_remove = (isset($parts) && is_array($parts)) ? (int) (end($parts)) : null;

    if (isset($object_to_remove) && $object_to_remove > -1) {
      unset($objects[$object_to_remove]);
      $objects = array_values($objects);
      \Drupal::state()->set('my_form_objects', $objects);
    }
    return;
  }

  /******************************
   *
   *    CODE'S FUNCTIONS
   *
   ******************************/

  /**
 * Render codebook rows with Bootstrap structure and display-mode toggling.
 *
 * @param array  $codes
 *   Each item: [
 *     'column',
 *     'code',
 *     'label',
 *     'class',
 *   ]
 * @param string $mode
 *   Display mode: 'prefix:label', 'just label', 'uri', 'prefix:uri'.
 *
 * @return array
 *   Renderable rows.
 */
  protected function renderCodeRows(array $codes, string $mode = 'prefix:uri'): array {
    $rows = [];
    $sep  = '<div class="w-100"></div>';

    foreach ($codes as $delta => $c) {
      $display_code = $this->formatDisplay($c['code'], $mode);
      $display_class= $this->formatDisplay($c['class'], $mode);

      $form_row = [
        'column' => [
          'top'    => ['#type'=>'markup', '#markup'=>'<div class="pt-3 col border border-white">'],
          'main'   => ['#type'=>'textfield', '#name'=>"code_column_$delta", '#default_value'=>$c['column']],
          'bottom' => ['#type'=>'markup', '#markup'=>'</div>'],
        ],
        'code' => [
          'top'    => ['#type'=>'markup', '#markup'=>'<div class="pt-3 col border border-white">'],
          'main'   => [
            '#type'=>'textfield',
            '#name'  => "code_code_$delta",
            '#value' => $display_code,
            '#attributes'=>[
              'data-original-value'=>$c['code'],
              'data-label'=>$c['label'],
            ],
          ],
          'bottom' => ['#type'=>'markup', '#markup'=>'</div>'],
        ],
        'label' => [
          'top'    => ['#type'=>'markup', '#markup'=>'<div class="pt-3 col border border-white">'],
          'main'   => ['#type'=>'textfield', '#name'=>"code_label_$delta", '#default_value'=>$c['label']],
          'bottom' => ['#type'=>'markup', '#markup'=>'</div>'],
        ],
        'class' => [
          'top'    => ['#type'=>'markup', '#markup'=>'<div class="pt-3 col border border-white">'],
          'main'   => [
            '#type'=>'textfield',
            '#name'  => "code_class_$delta",
            '#value' => $display_class,
            '#attributes'=>[
              'data-original-value'=>$c['class'],
              'data-label'=>$c['label'],
            ],
          ],
          'bottom' => ['#type'=>'markup', '#markup'=>'</div>'],
        ],
        'operations' => [
          'top'    => ['#type'=>'markup', '#markup'=>'<div class="pt-3 col-md-1 border border-white">'],
          'main'   => [
            '#type' => 'submit',
            '#name'  => "code_remove_$delta",
            '#value' => $this->t('Remove'),
            '#attributes'=>['class'=>['remove-row','btn','btn-sm','delete-element-button'],'id'=>"code-$delta"],
          ],
          'bottom' => ['#type'=>'markup', '#markup'=>"</div>$sep"],
        ],
      ];

      $rows[] = [$delta => $form_row];
    }

    return $rows;
  }

  protected function updateCodes(FormStateInterface $form_state) {
    $codes = \Drupal::state()->get('my_form_codes');
    $input = $form_state->getUserInput();
    if (isset($input) && is_array($input) &&
        isset($codes) && is_array($codes)) {

      foreach ($codes as $code_id => $code) {
        if (isset($code_id) && isset($code)) {
          $codes[$code_id]['column']  = $input['code_column_' . $code_id] ?? '';
          $codes[$code_id]['code']    = $input['code_code_' . $code_id] ?? '';
          $codes[$code_id]['label']   = $input['code_label_' . $code_id] ?? '';
          $codes[$code_id]['class']   = $input['code_class_' . $code_id] ?? '';
        }
      }
      \Drupal::state()->set('my_form_codes', $codes);
    }
    return;
  }

  protected function populateCodes($namespaces) {
    $codes = [];
    $possibleValues = $this->getSemanticDataDictionary()->possibleValues;
    if (count($possibleValues) > 0) {
      foreach ($possibleValues as $possibleValue_id => $possibleValue) {
        if (isset($possibleValue_id) && isset($possibleValue)) {
          $listPosition = $possibleValue->listPosition;
          $codes[$listPosition]['column']  = $possibleValue->isPossibleValueOf;
          $codes[$listPosition]['code']    = $possibleValue->hasCode;
          $codes[$listPosition]['label']   = $possibleValue->hasCodeLabel;
          $codes[$listPosition]['class']   = Utils::namespaceUriWithNS($possibleValue->hasClass,$namespaces);
        }
      }
      ksort($codes);
    }
    \Drupal::state()->set('my_form_codes', $codes);
    return $codes;
  }

  protected function saveCodes($semanticDataDictionaryUri, array $codes) {
    if (!isset($semanticDataDictionaryUri)) {
      \Drupal::messenger()->addError(t("No semantic data dictionary's URI have been provided to save possible values."));
      return;
    }
    if (!isset($codes) || !is_array($codes)) {
      \Drupal::messenger()->addWarning(t("Semantic data dictionary has no possible values to be saved."));
      return;
    }

    foreach ($codes as $code_id => $code) {
      if (isset($code_id) && isset($code)) {
        try {
          $useremail = \Drupal::currentUser()->getEmail();

          $column = ' ';
          if ($codes[$code_id]['column'] != NULL && $codes[$code_id]['column'] != '') {
            $column = $codes[$code_id]['column'];
          }

          $codeStr = ' ';
          if ($codes[$code_id]['code'] != NULL && $codes[$code_id]['code'] != '') {
            $codeStr = $codes[$code_id]['code'];
          }

          $codeLabel = ' ';
          if ($codes[$code_id]['label'] != NULL && $codes[$code_id]['label'] != '') {
            $codeLabel = $codes[$code_id]['label'];
          }

          $class = ' ';
          if ($codes[$code_id]['class'] != NULL && $codes[$code_id]['class'] != '') {
            $class = $codes[$code_id]['class'];
          }

          $codeUri = str_replace(
            Constant::PREFIX_SEMANTIC_DATA_DICTIONARY,
            Constant::PREFIX_POSSIBLE_VALUE,
            $semanticDataDictionaryUri) . '/' . $code_id;
          $codeJSON = '{"uri":"'. $codeUri .'",'.
              '"superUri":"'.HASCO::POSSIBLE_VALUE.'",'.
              '"hascoTypeUri":"'.HASCO::POSSIBLE_VALUE.'",'.
              '"partOfSchema":"'.$semanticDataDictionaryUri.'",'.
              '"listPosition":"'.$code_id.'",'.
              '"isPossibleValueOf":"'.$column.'",'.
              '"label":"'.$column.'",'.
              '"hasCode":"' . $codeStr . '",' .
              '"hasCodeLabel":"' . $codeLabel . '",' .
              '"hasClass":"' . $class . '",' .
              '"comment":"Possible value ' . $column . ' of ' . $column . ' of SDD ' . $semanticDataDictionaryUri . '",'.
              '"hasSIRManagerEmail":"'.$useremail.'"}';
          $api = \Drupal::service('rep.api_connector');
          $api->elementAdd('possiblevalue',$codeJSON);

          //dpm($codeJSON);

        } catch(\Exception $e){
          \Drupal::messenger()->addError(t("An error occurred while saving possible value(s): ".$e->getMessage()));
        }
      }
    }
    return;
  }

  public function addCodeRow() {
    $codes = \Drupal::state()->get('my_form_codes') ?? [];

    // Add a new row to the table.
    $codes[] = [
      'column' => '',
      'code' => '',
      'label' => '',
      'class' => '',
    ];
    \Drupal::state()->set('my_form_codes', $codes);

    // Rebuild the table rows.
    $form['codes']['rows'] = $this->renderCodeRows($codes);
    return;
  }

  public function removeCodeRow($button_name) {
    $codes = \Drupal::state()->get('my_form_codes') ?? [];

    // from button name's value, determine which row to remove.
    $parts = explode('_', $button_name);
    $code_to_remove = (isset($parts) && is_array($parts)) ? (int) (end($parts)) : null;

    if (isset($code_to_remove) && $code_to_remove > -1) {
      unset($codes[$code_to_remove]);
      $codes = array_values($codes);
      \Drupal::state()->set('my_form_codes', $codes);
    }
    return;
  }

  /* ================================================================================ *
   *
   *                                 SUBMIT FORM
   *
   * ================================================================================ */

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // IDENTIFY NAME OF BUTTON triggering submitForm()
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      // Release values cached in the editor before leaving it
      \Drupal::state()->delete('my_form_basic');
      \Drupal::state()->delete('my_form_variables');
      \Drupal::state()->delete('my_form_objects');
      \Drupal::state()->delete('my_form_codes');
      self::backUrl();
      return;
    }

    // If not leaving then UPDATE STATE OF VARIABLES, OBJECTS AND CODES
    // according to the current state of the editor
    if ($this->getState() === 'basic') {
      $this->updateBasic($form_state);
    }

    if ($this->getState() === 'dictionary') {
      $this->updateVariables($form_state);
      $this->updateObjects($form_state);
    }

    if ($this->getState() === 'codebook') {
      $this->updateCodes($form_state);
    }

    // Get the latest cached versions of values in the editor
    $basic = \Drupal::state()->get('my_form_basic');
    $variables = \Drupal::state()->get('my_form_variables');
    $objects = \Drupal::state()->get('my_form_objects');
    $codes = \Drupal::state()->get('my_form_codes');

    if ($button_name === 'new_variable') {
      $this->addVariableRow();
      return;
    }

    if (str_starts_with($button_name,'variable_remove_')) {
      $this->removeVariableRow($button_name);
      return;
    }

    if ($button_name === 'new_object') {
      $this->addObjectRow();
      return;
    }

    if (str_starts_with($button_name,'object_remove_')) {
      $this->removeObjectRow($button_name);
      return;
    }

    if ($button_name === 'new_code') {
      $this->addCodeRow();
      return;
    }

    if (str_starts_with($button_name,'code_remove_')) {
      $this->removeCodeRow($button_name);
      return;
    }

    if ($button_name === 'save') {
      try {
        $useremail = \Drupal::currentUser()->getEmail();
        $semanticDataDictionaryJSON = '{"uri":"'. $basic['uri'] .'",'.
            '"typeUri":"'.HASCO::SEMANTIC_DATA_DICTIONARY.'",'.
            '"hascoTypeUri":"'.HASCO::SEMANTIC_DATA_DICTIONARY.'",'.
            '"label":"'.$basic['name'].'",'.
            '"hasVersion":"'.$basic['version'].'",'.
            '"comment":"'.$basic['description'].'",'.
            '"hasSIRManagerEmail":"'.$useremail.'"}';

        $api = \Drupal::service('rep.api_connector');

        // The DELETE of the semantic data dictionary will also delete the
        // variables, objects and codes of the dictionary
        $api->elementDel('semanticdatadictionary',$basic['uri']);

        // In order to update the semantic dictionary it is necessary to
        // add the following to the dictionary: the dictionary itself, its
        // variables, its objects and its codes
        $api->elementAdd('semanticdatadictionary',$semanticDataDictionaryJSON);
        if (isset($variables)) {
          $this->saveVariables($basic['uri'],$variables);
        }
        if (isset($objects)) {
          $this->saveObjects($basic['uri'],$objects);
        }
        if (isset($codes)) {
          $this->saveCodes($basic['uri'],$codes);
        }

        // Release values cached in the editor
        \Drupal::state()->delete('my_form_basic');
        \Drupal::state()->delete('my_form_variables');
        \Drupal::state()->delete('my_form_objects');
        \Drupal::state()->delete('my_form_codes');

        \Drupal::messenger()->addMessage(t("Semantic Data Dictionary has been updated successfully."));
        self::backUrl();
        return;

      } catch(\Exception $e){
        \Drupal::messenger()->addMessage(t("An error occurred while updating a semantic data dictionary: ".$e->getMessage()));
        self::backUrl();
        return;
      }
    }

  }

  /**
   * Callback to open tree form.
   */
  public function openTreeModalCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // Obtenha a URL para carregar o modal (usando data-url do campo).
    $triggering_element = $form_state->getTriggeringElement();
    $url = $triggering_element['#attributes']['data-url'];

    // Adicione o comando para abrir o modal com o formulário.
    $response->addCommand(new OpenModalDialogCommand(
      $this->t('Tree Form'),
      '<iframe src="' . $url . '" style="width: 100%; height: 400px; border: none;"></iframe>',
      ['width' => '800']
    ));

    return $response;
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sem.edit_semantic_data_dictionary');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

    /**
   * AJAX callback for Display Mode changes.
   *
   * Returns the portion of the form inside #dict-wrapper.
   */
  public function displayModeAjaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['dict_wrapper'];
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'save') {
      // TODO
      /*
      $basic = \Drupal::state()->get('my_form_basic');
      if(strlen($basic['name']) < 1) {
        $form_state->setErrorByName(
          'semantic_data_dictionary_name',
          $this->t('Please enter a valid name for the Semantic Data Dictionary')
        );
      }
      */
    }
  }

  /**
   * Format a URI and its label according to the selected display mode.
   *
   * @param string $uri
   *   The raw URI value.
   * @param string $label
   *   The human-readable label.
   * @param string $mode
   *   One of: 'prefix:label', 'just label', 'uri', 'prefix:uri'.
   *
   * @return string
   *   The string to display in the textfield.
   */
  protected function formatDisplay(string $uri, string $mode = 'prefix:uri'): string {

    if (empty($uri) || substr($uri, 0, 2) === "??") return '';

    // GET VALUES
    $full_uri = Utils::plainUri($uri);
    $api = \Drupal::service('rep.api_connector');
    $values = $api->parseObjectResponse($api->getUri($full_uri),'getUri');

    if (!empty($values)) {
      switch ($mode) {
        case 'label':
          return $values->label;
        case 'prefix:label':
          return $values->uriNamespace;
        case 'prefix:uri':
        default:
          return $uri;
      }
    } else {
      return $uri;
    }
  }

}
