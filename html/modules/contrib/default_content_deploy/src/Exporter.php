<?php

namespace Drupal\default_content_deploy;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\default_content_deploy\Event\PreSerializeEvent;
use Drupal\hal\LinkManager\LinkManagerInterface;
use Symfony\Component\Serializer\Serializer;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\Plugin\DataType\SectionData;
use Drupal\layout_builder\SectionComponent;

/**
 * A service for handling export of default content.
 */
class Exporter {

  use DependencySerializationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Text dependencies option.
   *
   * @var bool
   */
  private $includeTextDependencies;

  /**
   * DCD Manager.
   *
   * @var \Drupal\default_content_deploy\DeployManager
   */
  protected $deployManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * DB connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Serializer.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * Entity type ID.
   *
   * @var string
   */
  private $entityTypeId;

  /**
   * Type of a entity content.
   *
   * @var string
   */
  private $bundle;

  /**
   * Entity IDs for export.
   *
   * @var array
   */
  private $entityIds;

  /**
   * Directory to export.
   *
   * @var string
   */
  private $folder;

  /**
   * Entity IDs which needs skip.
   *
   * @var array
   */
  private $skipEntityIds;

  /**
   * Array of entity types and with there values for export.
   *
   * @var array
   */
  private $exportedEntities = [];

  /**
   * Type of export.
   *
   * @var string
   */
  private $mode;

  /**
   * Is remove old content.
   *
   * @var bool
   */
  private $forceUpdate;

  /**
   * @var \DateTimeInterface
   */
  private $dateTime;

  /**
   * The link manager service.
   *
   * @var \Drupal\hal\LinkManager\LinkManagerInterface
   */
  protected $linkManager;

  /**
   * The event dispatcher.
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $eventDispatcher;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Exporter constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   DB connection.
   * @param \Drupal\default_content_deploy\DeployManager $deploy_manager
   *   DCD Manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager.
   * @param \Symfony\Component\Serializer\Serializer $serializer
   *   Serializer.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\hal\LinkManager\LinkManagerInterface $link_manager
   *   The link manager service.
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $event_dispatcher
   *    The event dispatcher.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(Connection $database, DeployManager $deploy_manager, EntityTypeManagerInterface $entityTypeManager, Serializer $serializer, FileSystemInterface $file_system, LinkManagerInterface $link_manager, ContainerAwareEventDispatcher $eventDispatcher, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config, LanguageManagerInterface $language_manager, EntityRepositoryInterface $entity_repository) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->serializer = $serializer;
    $this->deployManager = $deploy_manager;
    $this->fileSystem = $file_system;
    $this->linkManager = $link_manager;
    $this->eventDispatcher = $eventDispatcher;
    $this->moduleHandler = $module_handler;
    $this->config = $config;
    $this->languageManager = $language_manager;
    $this->entityRepository = $entity_repository;
  }

  /**
   * Set entity type ID.
   *
   * @param string $entity_type
   *   Entity Type.
   *
   * @return \Drupal\default_content_deploy\Exporter
   */
  public function setEntityTypeId($entity_type) {
    $content_entity_types = $this->deployManager->getContentEntityTypes();

    if (!array_key_exists($entity_type, $content_entity_types)) {
      throw new \InvalidArgumentException(sprintf('Entity type "%s" does not exist', $entity_type));
    }

    $this->entityTypeId = (string) $entity_type;

    return $this;
  }

  /**
   * Set type of a entity content.
   *
   * @param string $bundle
   *  Bundle of the entity type.
   *
   * @return \Drupal\default_content_deploy\Exporter
   */
  public function setEntityBundle($bundle) {
    $this->bundle = $bundle;
    return $this;
  }

  /**
   * Set entity IDs for export.
   *
   * @param array $entity_ids
   *   The IDs of entity.
   *
   * @return \Drupal\default_content_deploy\Exporter
   */
  public function setEntityIds(array $entity_ids) {
    $this->entityIds = $entity_ids;
    return $this;
  }

  /**
   * Set entity IDs which needs skip.
   *
   * @param array $skip_entity_ids
   *   The IDs of entity for skip.
   *
   * @return $this
   */
  public function setSkipEntityIds(array $skip_entity_ids) {
    $this->skipEntityIds = $skip_entity_ids;
    return $this;
  }

  /**
   * Set type of export.
   *
   * @param string $mode
   *  Value type of export.
   *
   * @return \Drupal\default_content_deploy\Exporter
   *
   * @throws \Exception
   */
  public function setMode($mode) {
    $available_modes = ['all', 'reference', 'default'];

    if (in_array($mode, $available_modes)) {
      $this->mode = $mode;
    }
    else {
      throw new \Exception('The selected mode is not available');
    }

    return $this;
  }

  /**
   * Is remove old content.
   *
   * @param bool $is_update
   *
   * @return \Drupal\default_content_deploy\Exporter
   */
  public function setForceUpdate(bool $is_update) {
    $this->forceUpdate = $is_update;
    return $this;
  }

  /**
   * @param \DateTimeInterface $date_time
   *
   * @return \Drupal\default_content_deploy\Exporter
   */
  public function setDateTime(\DateTimeInterface $date_time) {
    $this->dateTime = $date_time;
    return $this;
  }

  /**
   * Set the value of text_dependencies option.
   *
   * @param bool|null $text_dependencies
   *   The value of the text_dependencies option. If null, it will be
   *   obtained from the configuration.
   *
   * @return $this
   */
  public function setTextDependencies($text_dependencies = NULL) {
    if (is_null($text_dependencies)) {
      $config = $this->config->get('default_content_deploy.content_directory');
      $text_dependencies = $config->get('text_dependencies');
    }

    $this->includeTextDependencies = $text_dependencies;

    return $this;
  }

  /**
   * @return \DateTimeInterface|null
   */
  public function getDateTime() {
    return $this->dateTime;
  }

  /**
   * @return int
   */
  public function getTime() {
    return $this->dateTime ? $this->dateTime->getTimestamp() : 0;
  }

  /**
   * Set directory to export.
   *
   * @param string $folder
   *   The content folder.
   *
   * @return \Drupal\default_content_deploy\Exporter
   */
  public function setFolder(string $folder) {
    $this->folder = $folder;
    return $this;
  }

  /**
   * Get directory to export.
   *
   * @return string
   *   The content folder.
   *
   * @throws \Exception
   */
  protected function getFolder() {
    $folder = $this->folder ?: $this->deployManager->getContentFolder();

    if (!isset($folder)) {
      throw new \Exception('Directory for content deploy is not set.');
    }

    return $folder;
  }

  /**
   * Array with entity types for display result.
   *
   * @return array
   *   Array with entity types.
   */
  public function getResult() {
    return $this->exportedEntities;
  }

  /**
   * Get the value of text_dependencies option.
   *
   * @return bool
   *   The value of the text_dependencies option.
   */
  public function getTextDependencies() {
    $text_dependencies = $this->includeTextDependencies;
    return $text_dependencies;
  }

  /**
   * Export entities by entity type, id or bundle.
   *
   * @return \Drupal\default_content_deploy\Exporter
   *
   * @throws \Exception
   */
  public function export() {
    switch ($this->mode) {
      case 'default':
        $this->exportBatch();
        break;

      case 'reference':
        $this->exportBatch(TRUE);
        break;

      case 'all':
        $this->exportAllBatch();
        break;
    }

    return $this;
  }

  /**
   * Export content in batch.
   *
   * @param boolean $with_references
   *   Indicates if export should consider referenced entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function exportBatch($with_references = FALSE) {
    $entity_type = $this->entityTypeId;
    $exported_entity_ids = $this->getEntityIdsForExport();

    // Exit if there is nothing to export.
    if (empty($exported_entity_ids)) {
      \Drupal::messenger()->addMessage(t('Nothing to export.'), 'error');
      return;
    }

    if ($this->forceUpdate) {
      $this->fileSystem->deleteRecursive($this->getFolder());
    }

    $total = count($exported_entity_ids);
    $current = 1;

    $export_type = $with_references ? "exportBatchWithReferences" : "exportBatchDefault";

    foreach ($exported_entity_ids as $entity_id) {
      $operations[] = [
        [$this, $export_type],
        [$entity_type, $entity_id, $current, $total],
      ];

      $current++;
    }

    // Set up batch information.
    $batch = [
      'title' => t('Exporting content'),
      'operations' => $operations,
      'finished' => [$this, 'exportFinished'],
    ];

    batch_set($batch);

    if (PHP_SAPI === 'cli') {
      drush_backend_batch_process();
     }
  }

  /**
   * Prepares and exports a single entity to a JSON file.
   *
   * @param string $entity_type
   *   The type of the entity being exported.
   * @param int $entity_id
   *   The ID of the entity being exported.
   * @param array $context
   *   The batch context.
   */
  public function exportBatchDefault($entity_type, $entity_id, $current, $total, &$context) {
    // Set the start time so we can access across batch operations.
    if (empty($context['results']['start'])) {
      $context['results']['start'] = microtime(TRUE);
    }

    // Prepare export of entity.
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    $exported_entity = $this->getSerializedContent($entity);

    // Remove or add a new fields to serialize entities data.
    $entity_array = $this->serializer->decode($exported_entity, 'hal_json');
    $entity_type_object = $this->entityTypeManager->getDefinition($entity_type);
    $id_key = $entity_type_object->getKey('id');
    $entity_id = $entity_array[$id_key][0]['value'];

    unset($entity_array[$entity_type_object->getKey('revision')]);

    if ($entity_type === 'user') {
      $entity_array['pass'][0]['value'] = $entity->getPassword();
    }

    $data = $this->serializer->serialize($entity_array, 'hal_json', [
      'json_encode_options' => JSON_PRETTY_PRINT
    ]);

    // Write serialized entity to JSON file.
    $uuid = $entity->get('uuid')->value;
    $entity_type_folder = "{$this->getFolder()}/{$entity_type}";
    $this->fileSystem->prepareDirectory($entity_type_folder, FileSystemInterface::CREATE_DIRECTORY);

    file_put_contents("{$entity_type_folder}/{$uuid}.json", $data);

    $context['results']['exported_entities'][$entity_type][] = $uuid;
    $context['message'] = t('Exporting entity @current of @total (@time)', [
      '@current' => $current,
      '@total' => $total,
      '@time' => $this->getElapsedTime($context['results']['start']),
    ]);
  }

  /**
   * Prepares and exports a single entity to a JSON file with references.
   *
   * @param string $entity_type
   *   The type of the entity being exported.
   * @param int $entity_id
   *   The ID of the entity being exported.
   * @param array $context
   *   The batch context.
   */
  public function exportBatchWithReferences($entity_type, $entity_id, $current, $total, &$context) {
    // Set the start time so we can access across batch operations.
    if (empty($context['results']['start'])) {
      $context['results']['start'] = microtime(TRUE);
    }

    // Get referenced entities.
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);

    $entities = [];
    if ($entity instanceof ContentEntityInterface) {
      $indexed_dependencies = [$entity->uuid() => $entity];
      $entities = $this->getEntityReferencesRecursive($entity, 0, $indexed_dependencies);
    }

    foreach ($entities as $uuid => $exported_entity) {
      $entity_type = $exported_entity->getEntityTypeId();

      // Do not process entity if it has already been written to the file system.
      if (!empty($context['results']['exported_entities'][$entity_type])) {
        if (in_array($uuid, $context['results']['exported_entities'][$entity_type])) {
          continue;
        }
      }

      $serialized_entity = $this->getSerializedContent($exported_entity);
      $entity_array = $this->serializer->decode($serialized_entity, 'hal_json');
      $entity_type_object = $this->entityTypeManager->getDefinition($entity_type);
      $id_key = $entity_type_object->getKey('id');
      $entity_id = $entity_array[$id_key][0]['value'];
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);

      unset($entity_array[$entity_type_object->getKey('revision')]);

      if ($entity_type === 'user') {
        $entity_array['pass'][0]['value'] = $entity->getPassword();
      }

      $data = $this->serializer->serialize($entity_array, 'hal_json', [
        'json_encode_options' => JSON_PRETTY_PRINT
      ]);

      // Write serialized entity to JSON file.
      $entity_type_folder = "{$this->getFolder()}/{$entity_type}";
      $this->fileSystem->prepareDirectory($entity_type_folder, FileSystemInterface::CREATE_DIRECTORY);

      file_put_contents("{$entity_type_folder}/{$uuid}.json", $data);
      $context['results']['exported_entities'][$entity_type][] = $uuid;
    }

    $context['message'] = t('Exporting entity @current of @total (@time)', [
      '@current' => $current,
      '@total' => $total,
      '@time' => $this->getElapsedTime($context['results']['start']),
    ]);
  }

  /**
   * Prepare all content on the site to export.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function exportAllBatch() {
    $content_entity_types = $this->deployManager->getContentEntityTypes();

    if ($this->forceUpdate) {
      $this->fileSystem->deleteRecursive($this->getFolder());
    }

    $time = $this->getTime();
    $total = 0;
    $current = 1;

    foreach ($content_entity_types as $entity_type => $label) {
      // Skip specified entities in --skip_entity_type option.
      if (!$this->skipEntityIds || !in_array($entity_type, $this->skipEntityIds)) {
        $this->setEntityTypeId($entity_type);
        $query = $this->entityTypeManager->getStorage($entity_type)->getQuery();
        $query->accessCheck(FALSE);
        $entity_ids = array_values($query->execute());
        $total += count($entity_ids);

        foreach ($entity_ids as $entity_id) {
          $operations[] = [
            [$this, 'exportBatchDefault'],
            [$entity_type, $entity_id, $current, $total],
          ];

          $current++;
        }
      }
    }

    // Use the accumulated total count for batch operations.
    foreach ($operations as &$operation) {
      $operation[1][3] = $total;
    }

    // Set up batch information.
    $batch = [
      'title' => t('Exporting content'),
      'operations' => $operations,
      'finished' => [$this, 'exportFinished'],
    ];

    batch_set($batch);

    if (PHP_SAPI === 'cli') {
      drush_backend_batch_process();
    }
  }

  /**
   * Callback function to handle batch processing completion.
   *
   * @param bool $success
   *   Indicates whether the batch processing was successful.
   */
  public function exportFinished($success, $results, $operations) {
    if ($success) {
      // Get elapsed time for the overall batch process.
      $elapsed_time = $this->getElapsedTime($results['start']);

      // Batch processing completed successfully.
      \Drupal::messenger()->addMessage(t('Batch export completed successfully in') . ' ' . $elapsed_time);

      $type_counts = [];
      if (isset($results['exported_entities'])) {
        foreach ($results['exported_entities'] as $entity_type => $entities) {
          $type_counts[$entity_type] = count($entities);  // Count the number of entities per type
        }
      }

      // Output result counts per entity type.
      foreach ($type_counts as $type => $count) {
        \Drupal::messenger()->addMessage(t('@type: @count exported', [
          '@type' => $type,
          '@count' => $count,
        ]));
      }
    }
    else {
      // Batch processing encountered an error.
      \Drupal::messenger()->addMessage(t('An error occurred during the batch export process.'), 'error');
    }
  }

  /**
   * Get all entity IDs for export.
   *
   * @return array
   *   Return array of entity ids.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getEntityIdsForExport() {
    $skip_entities = $this->skipEntityIds;
    $entity_ids = $this->entityIds;
    $entity_type = $this->entityTypeId;
    $entity_bundle = $this->bundle;
    $key_bundle = $this->entityTypeManager->getDefinition($entity_type)->getKey('bundle');

    // If the Entity IDs option is null then load all IDs.
    if (empty($entity_ids)) {
      $query = $this->entityTypeManager->getStorage($entity_type)->getQuery();
      $query->accessCheck(FALSE);

      if ($entity_bundle) {
        $query->condition($key_bundle, $entity_bundle);
      }

      $entity_ids = $query->execute();
    }

    // Remove skipped entities from $exported_entity_ids.
    if (!empty($skip_entities)) {
      $entity_ids = array_diff($entity_ids, $skip_entities);
    }

    return $entity_ids;
  }

  /**
   * Exports a single entity as importContent expects it.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *
   * @return string
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getSerializedContent(ContentEntityInterface $entity) {
    $content = '';

    $event = new PreSerializeEvent($entity, $this->mode);
    $this->eventDispatcher->dispatch($event);
    $entity = $event->getEntity();

    if ($entity) {
      $host = $this->deployManager->getCurrentHost();
      $this->linkManager->setLinkDomain($host);
      $content = $this->serializer->serialize($entity, 'hal_json', ['json_encode_options' => JSON_PRETTY_PRINT]);
      $this->linkManager->setLinkDomain(FALSE);
    }

    return $content;
  }

  /**
   * Returns all layout builder referenced blocks of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Keyed array of entities indexed by entity type and ID.
   */
  private function getEntityLayoutBuilderDependencies(ContentEntityInterface $entity) {
    $entity_dependencies = [];

    if ($this->moduleHandler->moduleExists('layout_builder')) {
      // Gather a list of referenced entities, modeled after ContentEntityBase::referencedEntities().
      foreach ($entity->getFields() as $field_key => $field_items) {
        foreach ($field_items as $field_item) {
          // Loop over all properties of a field item.
          foreach ($field_item->getProperties(TRUE) as $property) {
            // Look only at LayoutBuilder SectionData fields.
            if ($property instanceof SectionData) {
              $section = $property->getValue();
              if ($section instanceof Section) {
                // Get list of components inside the LayoutBuilder Section.
                $components = $section->getComponents();
                foreach ($components as $component_uuid => $component) {
                  // Gather components of type "inline_block:html_block", by block revision_id.
                  if ($component instanceof SectionComponent) {
                    $configuration = $component->get('configuration');
                    if ($configuration['id'] === 'inline_block:html_block' && !empty($configuration['block_revision_id'])) {
                      $block_revision_id = $configuration['block_revision_id'];
                      $block_revision = $this->entityTypeManager
                        ->getStorage('block_content')
                        ->loadRevision($block_revision_id);
                      $entity_dependencies[] = $block_revision;
                    }
                    // Gather components of type 'block_content:*', by uuid.
                    else {
                      if (substr($configuration['id'], 0, 14) === 'block_content:') {
                        if ($block_uuid = substr($configuration['id'], 14)) {
                          $block_loaded_by_uuid = $this->entityTypeManager
                            ->getStorage('block_content')
                            ->loadByProperties(['uuid' => $block_uuid]);
                          $entity_dependencies[] = reset($block_loaded_by_uuid);
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }

    return $entity_dependencies;
  }

  /**
   * Returns all processed text references of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Keyed array of entities indexed by entity type and ID.
   */
  private function getEntityProcessedTextDependencies(ContentEntityInterface $entity) {
    $config = $this->config->get('default_content_deploy.content_directory');
    $enabled_entity_types  = $config->get('enabled_entity_types');
    $entity_dependencies = [];

    $field_definitions = $entity->getFieldDefinitions();
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    $languages = $entity->getTranslationLanguages();

    foreach ($languages as $langcode => $language) {
      $entity = $this->entityRepository->getTranslationFromContext($entity, $langcode);

      foreach ($field_definitions as $key => $field) {
        $field_config = $field->getConfig($bundle);
        $field_storage_definition = $field_config->getFieldStorageDefinition();
        $field_storage_property_definition = $field_storage_definition->getPropertyDefinitions();

        if (isset($field_storage_property_definition['processed'])) {
          $field_name = $field_config->getName();
          $field_data = $entity->get($field_name)->getString();

          $dom = Html::load($field_data);
          $xpath = new \DOMXPath($dom);

          // Iterate over all elements with a data-entity-type attribute.
          foreach ($xpath->query('//*[@data-entity-type]') as $node) {
            $entity_type = $node->getAttribute('data-entity-type');

            if (in_array($entity_type, $enabled_entity_types)) {
              $uuid = $node->getAttribute('data-entity-uuid');

              // Only add the dependency if it does not already exist.
              if (!in_array($uuid,  $entity_dependencies)) {
                $entity_loaded_by_uuid = $this->entityTypeManager
                  ->getStorage($entity_type)
                  ->loadByProperties(['uuid' => $uuid]);
                $entity_dependencies[] = reset($entity_loaded_by_uuid);
              }
            }
          }
        }
      }
    }

    return $entity_dependencies;
  }

  /**
   * Returns all referenced entities of an entity.
   *
   * This method is also recursive to support use-cases like a node -> media
   * -> file.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param int $depth
   *   Guard against infinite recursion.
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $indexed_dependencies
   *   Previously discovered dependencies.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Keyed array of entities indexed by entity type and ID.
   */
  private function getEntityReferencesRecursive(ContentEntityInterface $entity, $depth = 0, array &$indexed_dependencies = []) {
    $entity_dependencies = [];
    $languages = $entity->getTranslationLanguages();

    foreach (array_keys($languages) as $langcode) {
      $entity = $this->entityRepository->getTranslationFromContext($entity, $langcode);
      $entity_dependencies = array_merge($entity_dependencies, $entity->referencedEntities());
    }

    $entity_layout_builder_dependencies = $this->getEntityLayoutBuilderDependencies($entity);

    $entity_processed_text_dependencies = [];
    $text_dependencies = $this->getTextDependencies();

    if ($text_dependencies) {
      $entity_processed_text_dependencies = $this->getEntityProcessedTextDependencies($entity);
    }

    $entity_dependencies = array_merge($entity_dependencies, $entity_layout_builder_dependencies, $entity_processed_text_dependencies);

    foreach ($entity_dependencies as $dependent_entity) {
      // Config entities should not be exported but rather provided by default
      // config.
      if (!($dependent_entity instanceof ContentEntityInterface)) {
        continue;
      }

      // Return if entity is not in the configured referencable entity types to export.
      $config = $this->config->get('default_content_deploy.content_directory');
      $enabled_entity_types  = $config->get('enabled_entity_types');
      if (!in_array($dependent_entity->getEntityTypeId(), $enabled_entity_types)) {
        continue;
      }

      // Using UUID to keep dependencies unique to prevent recursion.
      $key = $dependent_entity->uuid();
      if (isset($indexed_dependencies[$key])) {
        // Do not add already indexed dependencies.
        continue;
      }

      $indexed_dependencies[$key] = $dependent_entity;
      // Build in some support against infinite recursion.
      if ($depth < 6) {
        $indexed_dependencies += $this->getEntityReferencesRecursive($dependent_entity, $depth + 1, $indexed_dependencies);
      }
    }

    return $indexed_dependencies;
  }

  /**
   * Calculates and formats the elapsed time.
   *
   * @param float $start
   *   The start time of the overall batch process.
   *
   * @return string
   *   The formatted elapsed time in minutes.
   */
  public function getElapsedTime($start) {
    $end = microtime(TRUE);
    $diff = $end - $start;
    $elapsed_time = number_format($diff / 60, 2) . ' ' . t('minutes');

    return $elapsed_time;
  }

}
