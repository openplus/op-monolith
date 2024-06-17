<?php

namespace Drupal\insert_view_adv\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\filter\Entity\FilterFormat;
use Drupal\views\Views;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class InsertViewDialog.
 *
 * @package Drupal\insert_view_adv\Form
 */
class InsertViewDialog extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * InsertViewDialog constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'insert_view_adv_dialog';
  }

  /**
   * Get the field info.
   *
   * Gets labels and description for argument in dialog form.
   *
   * @param array $argument
   *   Argument definition.
   *
   * @return array
   *   Argument information.
   */
  private static function getFieldInfo(array $argument) {
    $info = [];
    $bundle_info = [];
    $argument = array_shift($argument);
    if (!empty($argument['table'])) {
      $keys = explode('__', $argument['table']);
      if (!empty($keys[1])) {
        $info = FieldStorageConfig::loadByName($keys[0], $keys[1]);
        // If it is entity reference field try to get the target type and
        // selector settings.
        if ($info && $info->getType() == 'entity_reference') {
          $bundles = $info->getBundles();
          $bundles_machine_names = array_keys($bundles);
          $bundle_info = FieldConfig::loadByName($keys[0], $bundles_machine_names[0], $keys[1]);
        }
      }
    }
    return ['info' => $info, 'bundle_info' => $bundle_info];
  }

  /**
   * Ajax callback to get the view arguments.
   *
   * @param array $form
   *   Form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return array
   *   Form element for arguments of the view.
   */
  public static function getArguments(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $arguments = 0;
    $num_args = $form_state->get('num_args');
    if (!empty($values['inserted_view_adv'])) {
      $current_view = $values['inserted_view_adv'];
      if (!empty($form['#view_arguments'][$current_view])) {
        $arguments = count($form['#view_arguments'][$current_view]);
      }
    }
    $num_args += $arguments;
    $form_state->set('num_args', $num_args);
    $form_state->setRebuild(TRUE);
    return $form['arguments'];
  }

  /**
   * Create the argument field.
   *
   * @param array $form
   *   Form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param string $view_block
   *   Name of the view block.
   * @param int $num
   *   Delta of the argument.
   */
  public function renderArgument(array &$form, FormStateInterface $form_state, $view_block, $num) {
    if (!empty($form['#view_arguments'][$view_block][$num])) {
      $argument = $form['#view_arguments'][$view_block][$num];
      // Get field info.
      $info = InsertViewDialog::getFieldInfo($argument);
      $field_info = $info['info'];
      $bundle_info = $info['bundle_info'];
      if ($field_info) {
        $form['arguments']['argument'][$num] = [
          '#type' => ($field_info->getType() == 'entity_reference') ? 'entity_autocomplete' : 'textfield',
          '#title' => empty($bundle_info) ? $field_info->getLabel() : $bundle_info->getLabel(),
          '#description' => empty($bundle_info) ? $field_info->getDescription() : $bundle_info->getDescription(),
          '#default_value' => isset($this->getUserInput($form_state, 'arguments')[$num]) ? $this->getUserInput($form_state, 'arguments')[$num] : NULL,
        ];
        // If it is entity reference and some more settings.
        if (($field_info->getType() == 'entity_reference')) {
          $info_settings = $field_info->getSettings();
          $bundle_settings = $bundle_info->getSettings();
          $form['arguments']['argument'][$num]['#target_type'] = $info_settings['target_type'];
          $form['arguments']['argument'][$num]['#selection_handler'] = $bundle_settings['handler'];
          if (!empty($bundle_settings['handler_settings'])) {
            $form['arguments']['argument'][$num]['#selection_settings'] = $bundle_settings['handler_settings'];
          }
          if (isset($form['arguments']['argument'][$num]['#default_value'])) {
            // Default value could be only entity, let's load one.
            $entity_storage = $this->entityTypeManager
              ->getStorage($info_settings['target_type']);
            $entity = $entity_storage->load($form['arguments']['argument'][$num]['#default_value']);
            $form['arguments']['argument'][$num]['#default_value'] = $entity;
          }
          else {
            $form['arguments']['argument'][$num]['#default_value'] = NULL;
          }
        }
      }
      else {
        $argument_definition = reset($argument);
        if ($argument_definition['table'] == 'taxonomy_index') {
          $argument_definition['table'] = 'taxonomy_term_field_data';
        }
        $property = $argument_definition['id'];
        $entity_definitions = $this->entityTypeManager->getDefinitions();
        $not_found = TRUE;
        if (!empty($argument_definition['relationship']) && $argument_definition['relationship'] == 'none') {
          foreach ($entity_definitions as $entity_type_id => $definition) {
            if (!$definition->getBaseTable()) {
              continue;
            }
            $tables = [
              $definition->getBaseTable(),
              $definition->getDataTable(),
            ];
            if (in_array($argument_definition['table'], $tables)) {
              try {
                $base_fields = $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id);
                if ($definition->getKey('id') == $property || (!empty($base_fields[$property]) && $base_fields[$property]->getType() == 'entity_reference')) {
                  $field_info = $base_fields[$property];
                  $target_entity_type_id = $field_info->getFieldStorageDefinition()->getSetting('target_type');
                  $entity_storage = $this->entityTypeManager
                    ->getStorage($target_entity_type_id);
                  $entity = $entity_storage->load($this->getUserInput($form_state, 'arguments')[$num]);
                  $form['arguments']['argument'][$num] = [
                    '#type' => 'entity_autocomplete',
                    '#title' => $field_info->getLabel(),
                    '#description' => $field_info->getDescription(),
                    '#target_type' => $target_entity_type_id,
                    '#default_value' => $entity,
                  ];
                  $not_found = FALSE;
                }
              }
              catch (\LogicException $e) {
                continue;
              }
            }
          }
        }
        if ($not_found) {
          $default = $this->getUserInput($form_state, 'arguments');
          $form['arguments']['argument'][$num] = [
            '#type' => 'textfield',
            '#title' => $property,
            '#default_value' => isset($default[$num]) ? $default[$num] : NULL,
          ];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, FilterFormat $filter_format = NULL) {
    // Add AJAX support.
    $form['#prefix'] = '<div id="insert-view-dialog-form">';
    $form['#suffix'] = '</div>';

    // Ensure relevant dialog libraries are attached.
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $filter_settings = $filter_format->filters('insert_view_adv')->settings;
    $allowed_views = array_filter($filter_settings['allowed_views']);
    $views_list = Views::getEnabledViews();
    $arguments = [];
    $options = ['' => $this->t('-- Choose the view --')];
    foreach ($views_list as $machine_name => $view) {
      foreach ($view->get('display') as $display) {
        // Get display name with the view label.
        $key = $machine_name . '=' . $display['id'];
        if (!empty($allowed_views) && empty($allowed_views[$key])) {
          continue;
        }
        if (empty($options[$machine_name])) {
          $options[$machine_name] = [];
        }
        $options[$machine_name][$key] = $view->label() . ' ' . $display['display_title'];
        if (empty($display['display_options']['arguments']) && $display['id'] != 'default') {
          $master_display = $view->getDisplay('default');
          if (!empty($master_display['display_options']['arguments'])) {
            $display['display_options']['arguments'] = $master_display['display_options']['arguments'];
          }
        }
        // Get arguments.
        if (!empty($display['display_options']['arguments']) && $display['display_options']['arguments']) {
          foreach ($display['display_options']['arguments'] as $field => $item) {
            $arguments[$machine_name . '=' . $display['id']][] = [$field => $item];
          }
        }
      }
    }
    // Pass the arguments to form so we have access to arguments in ajax call.
    $form['#view_arguments'] = $arguments;

    // Check if the widget edit form is called.
    $current_view = $this->getUserInput($form_state, 'inserted_view_adv');
    if ($current_view == '') {
      // Try to get the value from submitted.
      $values = $form_state->getUserInput();
      if (!empty($values['inserted_view_adv'])) {
        $current_view = $values['inserted_view_adv'];
      }
    }

    // Select box with the list of views blocks grouped by view.
    $form['inserted_view_adv'] = [
      '#type' => 'select',
      '#title' => $this->t('View to insert'),
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => $current_view,
      '#ajax' => [
        // Trigger ajax call on change to get the arguments of the views block.
        'callback' => 'Drupal\insert_view_adv\Form\InsertViewDialog::getArguments',
        'event' => 'change',
        'wrapper' => 'arguments',
      ],
    ];

    // Create a settings form from the existing video formatter.
    $form['arguments'] = [];
    if (!empty($filter_settings['hide_argument_input'])) {
      $form['arguments']['#access'] = FALSE;
    }
    $form['arguments']['#type'] = 'fieldset';
    $form['arguments']['#prefix'] = '<div id="arguments">';
    $form['arguments']['#suffix'] = '</div>';
    $form['arguments']['#title'] = $this->t('Arguments');
    $form['arguments']['argument'] = ['#tree' => TRUE];

    if ($current_view && !empty($form['#view_arguments'][$current_view]) && is_array($form['#view_arguments'][$current_view])) {
      $argument_field = count($form['#view_arguments'][$current_view]);
      $form_state->set('num_args', $argument_field);
    }
    else {
      $argument_field = $form_state->get('num_args');
    }
    if (empty($argument_field)) {
      $form_state->set('num_args', 0);
    }
    for ($i = 0; $i < $argument_field; $i++) {
      $this->renderArgument($form, $form_state, $current_view, $i);
    }

    // If there are no arguments show the message.
    if (count($form['arguments']['argument']) == 1) {
      $form['arguments']['argument']['#markup'] = $this->t('No arguments provided');
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['save_modal'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => [],
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'event' => 'click',
        'wrapper' => 'insert-view-dialog-form',
      ],
    ];
    return $form;
  }

  /**
   * Get a value from the widget in the WYSIWYG.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state to extract values from.
   * @param string $key
   *   The key to get from the selected WYSIWYG element.
   *
   * @return string
   *   The default value.
   */
  protected function getUserInput(FormStateInterface $form_state, $key) {
    return isset($form_state->getUserInput()['editor_object'][$key]) ? $form_state->getUserInput()['editor_object'][$key] : '';
  }

  /**
   * Get the values from the form required for the client.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state from the dialog submission.
   *
   * @return array
   *   An array of values sent to the client for use in the WYSIWYG.
   */
  protected function getClientValues(FormStateInterface $form_state) {
    $view_id = '';
    $display_id = '';
    [$view_id, $display_id] = explode('=', $form_state->getValue('inserted_view_adv'));
    $arguments = array_filter($form_state->getValue('argument', []));;
    return [
      'attributes' => [
        'data-view-id' => $view_id,
        'data-display-id' => $display_id,
        'data-arguments' => implode('/', $arguments),
      ],
    ];
  }

  /**
   * An AJAX submit callback to validate the WYSIWYG modal.
   *
   * @param array $form
   *   Form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response from ajax form submit.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    if (!$form_state->getErrors()) {
      // Provide the commands for the widget.
      $response->addCommand(new EditorDialogSave($this->getClientValues($form_state)));
      $response->addCommand(new CloseModalDialogCommand());
    }
    else {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new HtmlCommand(NULL, $form));
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The AJAX commands were already added in the AJAX callback. Do nothing in
    // the submit form.
  }

}
