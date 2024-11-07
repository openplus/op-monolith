<?php

namespace Drupal\footnotes\Upgrade;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provide Drush commands for upgrading Footnotes major versions.
 */
class FootnotesUpgradeDrushCommand extends DrushCommands implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * ConfigEntityUpdater constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\footnotes\Upgrade\FootnotesUpgradeBatchManager $batchManager
   *   The batch manager.
   */
  public function __construct(
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FootnotesUpgradeBatchManager $batchManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('footnotes.batch_manager')
    );
  }

  /**
   * Upgrade from 3x to 4x.
   *
   * @param string $entity_type
   *   The entity type that may contain formatted text. Examples
   *    include 'node', 'paragraph', 'taxonomy_term', 'block'.
   * @param array $options
   *   Array of options as described below.
   *
   * @command footnotes:upgrade-3-to-4
   *
   * @option use-data-text Puts the reference content within data-text
   *   like CK Editor 5 does. Set to false if you do not use CK Editor
   *   and instead write plain html.
   */
  public function upgrade3to4(string $entity_type, array $options = ['use-data-text' => TRUE]): void {

    // Get the entity type storage.
    try {
      $storage = $this->entityTypeManager->getStorage($entity_type);
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $exception) {
      $message = $this->t('Unable to load the storage for the provided entity type: @message', [
        '@message' => $exception->getMessage(),
      ]);
      $this->output()->writeln($message);

      return;
    }

    if (in_array($options['use-data-text'], ['false', 'FALSE', 0, '0'], TRUE)) {
      $options['use-data-text'] = FALSE;
    }

    // Get all formatted text fields.
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
    $formatted_types = ['text', 'text_long', 'text_with_summary'];

    foreach ($bundle_info as $bundle => $bundle_data) {
      $field_definitions = [];
      try {
        $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
      }
      catch (\Exception $exception) {
      }

      foreach ($field_definitions as $field_name => $field_definition) {
        $field_type = $field_definition->getType();

        if (in_array($field_type, $formatted_types)) {
          $formatted_fields[] = [
            'field_name' => $field_name,
            'entity_type' => $entity_type,
          ];
        }
      }
    }

    if (empty($formatted_fields)) {
      $message = $this->t('No fields were found that support formatted text for the entity type: @entity_type', [
        '@entity_type' => $entity_type,
      ]);
      $this->output()->writeln($message);

      return;
    }

    // Find all entities using the fields.
    foreach ($formatted_fields as $formatted_field_data) {
      $field_name = $formatted_field_data['field_name'];
      $entity_type = $formatted_field_data['entity_type'];

      // Store all entity IDs that need updating, along with
      // which fields within those entities.
      $query = $storage->getQuery();
      $entity_ids = $query
        ->exists($field_name . '.value')
        ->accessCheck(FALSE)
        ->execute();

      if ($entity_ids) {
        foreach ($entity_ids as $entity_id) {
          $entity_id_fields[$entity_id][] = $field_name;
        }
      }
    }

    // Prepare the batch operations.
    if (!empty($entity_id_fields)) {
      $operations = [];
      foreach ($entity_id_fields as $entity_id => $fields) {
        $operations[] = [
          '\Drupal\footnotes\Upgrade\FootnotesUpgradeBatchManager::processItem',
          [
            $entity_type,
            $entity_id,
            $fields,
            $options,
          ],
        ];
      }

      // Start the drush batch.
      $batch = [
        'title' => $this->t('Updating @entity_type', ['@entity_type' => $entity_type]),
        'operations' => $operations,
        'finished' => '\Drupal\footnotes\Upgrade\FootnotesUpgradeBatchManager::processFinished',
      ];
      batch_set($batch);
      drush_backend_batch_process();
    }
    else {
      $message = $this->t('There we no entities to process for entity type: @entity_type', [
        '@entity_type' => $entity_type,
      ]);
      $this->output()->writeln($message);
    }
  }

}
