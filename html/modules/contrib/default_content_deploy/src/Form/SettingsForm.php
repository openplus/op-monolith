<?php

/**
 * @file
 * Contains \Drupal\default_content_deploy\Form\SettingsForm.
 */

namespace Drupal\default_content_deploy\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Directory config name.
   */
  const CONFIG = 'default_content_deploy.settings';

  /**
   * The Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dcd_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::CONFIG,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::CONFIG);

    $form = $this->getCommonFormElements($form, [
      'content_directory' => $config->get('content_directory'),
      'skip_computed_fields' => $config->get('skip_computed_fields'),
      'skip_processed_values' => $config->get('skip_processed_values'),
      'text_dependencies' => $config->get('text_dependencies'),
      'skip_entity_types' => $config->get('skip_entity_types') ?? [],
    ]);

    $form['skip_computed_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip computed fields'),
      '#default_value' => $config->get('skip_computed_fields'),
      '#description' => 'If selected, computed fields will not be included in the export.',
    ];

    $form['skip_processed_values'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip processed values'),
      '#default_value' => $config->get('skip_processed_values'),
      '#description' => 'If selected, processed values will not be included in the export.',
    ];

    $form['batch_ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('Batch TTL'),
      '#default_value' => $config->get('batch_ttl') ?? 14400,
      '#description' => 'TTL in seconds for batch items until the garbage collection for orphaned items removes them.',
    ];

    return parent::buildForm($form, $form_state);
  }

  public function getCommonFormElements(array $form, array $defaults) {
    $form['content_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content Directory'),
      '#default_value' => $defaults['content_directory'],
      '#description' => 'Specify the path relative to index.php. For example: ../content',
      '#required' => TRUE,
    ];

    $form['text_dependencies'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Export processed text dependencies'),
      '#default_value' => $defaults['text_dependencies'],
      '#description' => 'If selected, embedded entities within processed text fields will be included in the export.',
    ];

    $all_entity_types = $this->entityTypeManager->getDefinitions();
    $content_entity_types = [];

    // Filter the entity types.
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_type_options */
    foreach ($all_entity_types as $entity_type) {
      if (($entity_type instanceof ContentEntityTypeInterface)) {
        $content_entity_types[$entity_type->id()] = $entity_type->getLabel();
      }
    }

    // Entity types.
    $form['skip_entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Skip entity types to be exported indirectly by reference or in site export'),
      '#description' => $this->t('Check which entity types should not be exported.'),
      '#options' => $content_entity_types,
      // If no custom settings exist, content entities are enabled by default.
      '#default_value' => $defaults['skip_entity_types'] ?? [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::CONFIG)
      ->set('content_directory', $form_state->getValue('content_directory'))
      ->set('skip_computed_fields', (bool) $form_state->getValue('skip_computed_fields'))
      ->set('skip_processed_values', (bool) $form_state->getValue('skip_processed_values'))
      ->set('skip_entity_types', array_values(array_filter($form_state->getValue('skip_entity_types'))))
      ->set('text_dependencies', (bool) $form_state->getValue('text_dependencies'))
      ->set('batch_ttl', (int) $form_state->getValue('batch_ttl'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
