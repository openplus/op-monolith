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
  const DIRECTORY = 'default_content_deploy.content_directory';

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
      static::DIRECTORY,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::DIRECTORY);

    $form['content_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content Directory'),
      '#default_value' => $config->get('content_directory'),
      '#description' => 'Specify the path relative to index.php. For example: ../content',
    ];

    $form['text_dependencies'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Export processed text dependencies'),
      '#default_value' => $config->get('text_dependencies'),
      '#description' => 'If selected, embedded entities within processed text fields will be included in the export.',
    ];

    $form['support_old_content'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Support old content'),
      '#default_value' => $config->get('support_old_content'),
      '#description' => 'If selected, the import process with run additional processes to ensure reference integrity for older content.',
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
    $form['enabled_entity_types'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Enabled entity types'),
      '#description' => $this->t('Check which entity types should be exported by reference.'),
      '#tree' => TRUE,
    ];

    $form['enabled_entity_types']['entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled entity types'),
      '#options' => $content_entity_types,
      // If no custom settings exist, content entities are enabled by default.
      '#default_value' => $config->get('enabled_entity_types') ?: array_keys($content_entity_types),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::DIRECTORY)
      ->set('content_directory', $form_state->getValue('content_directory'))
      ->set('enabled_entity_types', array_values(array_filter($form_state->getValue('enabled_entity_types')['entity_types'])))
      ->set('text_dependencies', $form_state->getValue('text_dependencies'))
      ->set('support_old_content', $form_state->getValue('support_old_content'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
