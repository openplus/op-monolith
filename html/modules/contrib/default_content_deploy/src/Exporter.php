<?php

namespace Drupal\default_content_deploy;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\default_content_deploy\Event\PostSerializeEvent;
use Drupal\default_content_deploy\Event\PreSerializeEvent;
use Drupal\default_content_deploy\Form\SettingsForm;
use Drupal\default_content_deploy\Queue\DefaultContentDeployBatch;
use Drupal\hal\LinkManager\LinkManagerInterface;
use Symfony\Component\Serializer\Serializer;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\Plugin\DataType\SectionData;
use Drupal\layout_builder\SectionComponent;

class Exporter implements ExporterInterface
{

  use DependencySerializationTrait;
  use StringTranslationTrait;
  use AdministratorTrait;

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
   * Serializer.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The account switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

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
   * Entity type IDs which needs skip.
   *
   * @var array
   */
  private $skipEntityTypeIds;

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

  private $linkDomain = '';

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
   * @var bool
   */
  protected $verbose = FALSE;


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
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switcher service.
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
  public function __construct(Connection $database, DeployManager $deploy_manager, EntityTypeManagerInterface $entityTypeManager, Serializer $serializer, AccountSwitcherInterface $account_switcher, FileSystemInterface $file_system, LinkManagerInterface $link_manager, ContainerAwareEventDispatcher $eventDispatcher, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config, LanguageManagerInterface $language_manager, EntityRepositoryInterface $entity_repository) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->serializer = $serializer;
    $this->accountSwitcher = $account_switcher;
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
   * {@inheritdoc}
   */
  public function setEntityTypeId(string $entity_type): void {
    $content_entity_types = $this->deployManager->getContentEntityTypes();

    if (!array_key_exists($entity_type, $content_entity_types)) {
      throw new \InvalidArgumentException(sprintf('Entity type "%s" does not exist', $entity_type));
    }

    $this->entityTypeId = (string) $entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityBundle(string $bundle): void {
    $this->bundle = $bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityIds(array $entity_ids): void {
    $this->entityIds = $entity_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function setSkipEntityIds(array $skip_entity_ids): void {
    $this->skipEntityIds = $skip_entity_ids;
  }

  public function setSkipEntityTypeIds(array $skip_entity_type_ids): void {
    $this->skipEntityTypeIds = $skip_entity_type_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getSkipEntityTypeIds(): array {
    if (empty($this->skipEntityTypeIds)) {
      $config = $this->config->get(SettingsForm::CONFIG);
     return $config->get('skip_entity_types') ?? [];
    }

    return $this->skipEntityTypeIds;
  }

  /**
   * {@inheritdoc}
   */
  public function setMode(string $mode): void {
    $available_modes = ['all', 'reference', 'default'];

    if (in_array($mode, $available_modes)) {
      $this->mode = $mode;
    }
    else {
      throw new \Exception('The selected mode is not available');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setForceUpdate(bool $is_update): void {
    $this->forceUpdate = $is_update;
  }

  /**
   * {@inheritdoc}
   */
  public function setLinkDomain(string $link_domain): void {
    $this->linkDomain = $link_domain;
  }

  /**
   * {@inheritdoc}
   */
  public function getLinkDomain(): string {
    if (empty($this->linkDomain)) {
      return $this->deployManager->getCurrentHost();
    }

    return $this->linkDomain;
  }

  /**
   * {@inheritdoc}
   */
  public function setDateTime(\DateTimeInterface $date_time): void {
    $this->dateTime = $date_time;
  }

  /**
   * {@inheritdoc}
   */
  public function setTextDependencies($text_dependencies = NULL): void {
    if (is_null($text_dependencies)) {
      $config = $this->config->get(SettingsForm::CONFIG);
      $text_dependencies = (bool) $config->get('text_dependencies');
    }

    $this->includeTextDependencies = $text_dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getDateTime(): ?\DateTimeInterface {
    return $this->dateTime;
  }

  /**
   * {@inheritdoc}
   */
  public function getTime(): int {
    return $this->dateTime ? $this->dateTime->getTimestamp() : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function setFolder(string $folder): void {
    $this->folder = $folder;
  }

  /**
   * Get directory to export.
   *
   * @return string
   *   The content folder.
   *
   * @throws \Exception
   */
  protected function getFolder(): string {
    $folder = $this->folder ?: $this->deployManager->getContentFolder();

    if (!isset($folder)) {
      throw new \Exception('Directory for content deploy is not set.');
    }

    return $folder;
  }

  /**
   * {@inheritdoc}
   */
  public function getTextDependencies(): bool {
    if (is_null($this->includeTextDependencies)) {
      $this->setTextDependencies();
    }

    return $this->includeTextDependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function setVerbose(bool $verbose): void {
    $this->verbose = $verbose;
  }

  /**
   * {@inheritdoc}
   */
  public function export(): void {
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
  }

  /**
   * Export content in batch.
   *
   * @param bool $with_references
   *   Indicates if export should consider referenced entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function exportBatch(bool $with_references = FALSE): void {
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

    $operations[] = $this->getInitializeContextOperation();

    if ($total = count($exported_entity_ids)) {
      $current = 1;
      $export_type = $with_references ? 'exportBatchWithReferences' : 'exportBatchDefault';
      foreach ($exported_entity_ids as $entity_id) {
        $operations[] = [
          [static::class, 'exportFile'],
          [$export_type, $entity_type, $entity_id, $current++, $total],
        ];
      }
    }

    // Set up batch information.
    $batch_definition = [
      'title' => $this->t('Exporting content'),
      'operations' => $operations,
      'finished' => [static::class, 'exportFinished'],
      'progressive' => TRUE,
      'queue' => [
        'class' => DefaultContentDeployBatch::class,
        'name' => 'default_content_deploy:export:' . \Drupal::time()->getCurrentMicroTime(),
      ],
    ];

    batch_set($batch_definition);
  }

  protected function getInitializeContextOperation(): array {
    $context = [
      'dateTime' => $this->getDateTime(),
      'folder' => $this->getFolder(),
      'includeTextDependencies' => $this->getTextDependencies(),
      'skipEntityTypeIds' => $this->getSkipEntityTypeIds(),
      'mode' => $this->mode,
      'verbose' => $this->verbose,
      'linkDomain' => $this->linkDomain,
    ];

    return [
      [static::class, 'initializeContext'],
      [$context],
    ];

  }

  public static function initializeContext(array $vars, array &$context): void {
    $context['results'] = array_merge($context['results'] ?? [], $vars);
  }

  protected function synchronizeContext(array &$context): void {
    $this->dateTime = &$context['results']['dateTime'];
    $this->folder = &$context['results']['folder'];
    $this->includeTextDependencies = &$context['results']['includeTextDependencies'];
    $this->skipEntityTypeIds = &$context['results']['skipEntityTypeIds'];
    $this->mode = &$context['results']['mode'];
    $this->verbose = &$context['results']['verbose'];
    $this->linkDomain = &$context['results']['linkDomain'];
  }

  public static function exportFile(string $export_type, string $entity_type, string|int $entity_id, int $current, int $total, array &$context): void {
    /** @var ExporterInterface $exporter */
    $exporter = \Drupal::service('default_content_deploy.exporter');
    $exporter->synchronizeContext($context);
    $exporter->{$export_type}($entity_type, $entity_id, $current, $total, $context);
  }

  /**
   * Prepares and exports a single entity to a JSON file.
   *
   * @param string $entity_type
   *   The type of the entity being exported.
   * @param int $entity_id
   *   The ID of the entity being exported.
   * @param int $current
   *   The current item.
   * @param int $total
   *   The total number of items.
   * @param array $context
   *   The batch context.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function exportBatchDefault(string $entity_type, string|int $entity_id, int $current, int $total, array &$context): void {
    $uuid = FALSE;
    if ($entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id)) {
      $uuid = $entity->get('uuid')->value;
      if (!$this->skipEntity($entity, $context)) {
        if ($serialized_entity = $this->getSerializedContent($entity)) {
          $this->writeSerializedEntity($entity_type, $serialized_entity, $uuid);
          $context['results']['exported_entities'][$entity_type][] = $uuid;
          if ($this->verbose) {
            $context['message'] = $this->t('Exported @type entity (ID @id, bundle @bundle) @current of @total', [
              '@type' => $entity_type,
              '@id' => $entity_id,
              '@bundle' => $entity->bundle(),
              '@current' => $current,
              '@total' => $total,
            ]);
          }

          return;
        }
      }
    }

    $context['results']['skipped_entities'][$entity_type][] = $uuid;

    if ($this->verbose) {
      $context['message'] = $this->t('Skipped @type entity (ID @id, bundle @bundle) @current of @total', [
        '@type' => $entity_type,
        '@id' => $entity_id,
        '@bundle' => $entity->bundle(),
        '@current' => $current,
        '@total' => $total,
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function exportEntity(ContentEntityInterface $entity, ?bool $with_references = FALSE): bool {
    $this->setMode($with_references ? 'reference' : 'default');
    $uuid = $entity->get('uuid')->value;
    $context = [];
    if ($serialized_entity = $this->getSerializedContent($entity)) {
      $this->writeSerializedEntity($entity->getEntityTypeId(), $serialized_entity, $uuid);

      if ($with_references) {
        $indexed_dependencies = [$entity->uuid() => $entity];
        $referenced_entities = $this->getEntityReferencesRecursive($entity, $context, 0, $indexed_dependencies);

        foreach ($referenced_entities as $uuid => $referenced_entity) {
          $referenced_entity_type = $referenced_entity->getEntityTypeId();

          if ($serialized_entity = $this->getSerializedContent($referenced_entity)) {
            $this->writeSerializedEntity($referenced_entity_type, $serialized_entity, $uuid);
          }
        }
      }

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Prepares and exports a single entity to a JSON file with references.
   *
   * @param string $entity_type
   *   The type of the entity being exported.
   * @param string|int $entity_id
   *   The ID of the entity being exported.
   * @param int $current
   *   The current item.
   * @param int $total
   *   The total number of items.
   * @param array $context
   *   The batch context.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function exportBatchWithReferences(string $entity_type, string|int $entity_id, int $current, int $total, array &$context): void {
    if ($entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id)) {

      if (!$this->skipEntity($entity, $context)) {
        $indexed_dependencies = [$entity->uuid() => $entity];
        $referenced_entities = $this->getEntityReferencesRecursive($entity, $context, 0, $indexed_dependencies);
        $referenced_entities[$entity->get('uuid')->value] = $entity;

        foreach ($referenced_entities as $uuid => $referenced_entity) {
          $referenced_entity_type = $referenced_entity->getEntityTypeId();

          if ($serialized_entity = $this->getSerializedContent($referenced_entity)) {
            $this->writeSerializedEntity($referenced_entity_type, $serialized_entity, $uuid);
            $context['results']['exported_entities'][$referenced_entity_type][] = $uuid;
          }
        }

        if ($this->verbose) {
          $context['message'] = $this->t('Exported @type entity (ID @id, bundle @bundle) @current of @total', [
            '@type' => $entity_type,
            '@id' => $entity_id,
            '@bundle' => $entity->bundle(),
            '@current' => $current,
            '@total' => $total,
          ]);
        }
      }
      else {
        $context['results']['skipped_entities'][$entity->getEntityTypeId()][] = $entity->uuid();

        if ($this->verbose) {
          $context['message'] = $this->t('Skipped @type entity (ID @id, bundle @bundle) @current of @total', [
            '@type' => $entity_type,
            '@id' => $entity_id,
            '@bundle' => $entity->bundle(),
            '@current' => $current,
            '@total' => $total,
          ]);
        }
      }
    }
  }

  /**
   * Prepare all content on the site to export.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function exportAllBatch(): void {
    $content_entity_types = $this->deployManager->getContentEntityTypes();

    if ($this->forceUpdate) {
      $this->fileSystem->deleteRecursive($this->getFolder());
    }

    $operations[] = $this->getInitializeContextOperation();

    $total = 0;
    $current = 1;
    foreach ($content_entity_types as $entity_type => $label) {
      if (in_array($entity_type, $this->getSkipEntityTypeIds())) {
        continue;
      }

      $this->setEntityTypeId($entity_type);
      $entity_ids = $this->getEntityIdsForExport();
      $total += count($entity_ids);

      foreach ($entity_ids as $entity_id) {
        $operations[] = [
          [static::class, 'exportFile'],
          ['exportBatchDefault', $entity_type, $entity_id, $current++, $total],
        ];
      }
    }

    // Use the accumulated total count for batch operations.
    foreach ($operations as $key => &$operation) {
      if ($key === 0) {
        continue;
      }
      $operation[1][4] = $total;
    }
    unset($operation);

    // Set up batch information.
    $batch_definition = [
      'title' => $this->t('Exporting content'),
      'operations' => $operations,
      'finished' => [static::class, 'exportFinished'],
      'progressive' => TRUE,
      'queue' => [
        'class' => DefaultContentDeployBatch::class,
        'name' => 'default_content_deploy:export:' . \Drupal::time()->getCurrentMicroTime(),
      ],
    ];

    batch_set($batch_definition);
  }

  /**
   * Callback function to handle batch processing completion.
   *
   * @param bool $success
   *   Indicates whether the batch processing was successful.
   * @param array $results
   *    The results.
   * @param array $operations
   *    The operations.
   */
  public static function exportFinished($success, $results, $operations): void {
    if ($success) {
      // Batch processing completed successfully.
      \Drupal::messenger()->addMessage(t('Batch export completed successfully.'));

      $counts = [];
      if (isset($results['exported_entities'])) {
        foreach ($results['exported_entities'] as $entity_type => $entities) {
          $counts[$entity_type]['exported'] = count($entities);  // Count the number of entities per type
        }
      }
      if (isset($results['skipped_entities'])) {
        foreach ($results['skipped_entities'] as $entity_type => $entities) {
          $counts[$entity_type]['skipped'] = count($entities);  // Count the number of entities per type
        }
      }

      // Output result counts per entity type.
      foreach ($counts as $type => $count) {
        if ($count['skipped'] ?? 0) {
          \Drupal::messenger()
            ->addMessage(t('@type: @exported exported, @skipped skipped dynamically (excluded bundle, changed timestamp, event, erroneous, ...)', [
              '@type' => $type,
              '@exported' => $count['exported'] ?? 0,
              '@skipped' => $count['skipped'] ?? 0,
            ]));
        }
        else {
          \Drupal::messenger()
            ->addMessage(t('@type: @exported exported,', [
              '@type' => $type,
              '@exported' => $count['exported'] ?? 0,
            ]));
        }
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
  private function getEntityIdsForExport(): array {
    $entity_ids = $this->entityIds;

    // If the Entity IDs option is null then load all IDs.
    if (empty($entity_ids)) {
      $entity_type_definition = $this->entityTypeManager->getDefinition($this->entityTypeId);
      $key_bundle = $entity_type_definition->getKey('bundle');
      $entity_class = $entity_type_definition->getClass();

      $query = $this->entityTypeManager->getStorage($this->entityTypeId)->getQuery();
      $query->accessCheck(FALSE);

      if ($key_bundle && $this->bundle) {
        $query->condition($key_bundle, $this->bundle);
      }

      $time = $this->getTime();
      if ($time && in_array(EntityChangedInterface::class, class_implements($entity_class))) {
        $query->condition('changed', $time, '>=');
      }

      $entity_ids = $query->execute();
    }

    // Remove skipped entities from $exported_entity_ids.
    if (!empty($this->skipEntityIds)) {
      $entity_ids = array_diff($entity_ids, $this->skipEntityIds);
    }

    // For debugging, limit the number of entities.
    // return array_slice($entity_ids, 0, 10, true);

    return $entity_ids;
  }

  protected function skipEntity(EntityInterface $entity, array &$context): bool {
    if (!($entity instanceof ContentEntityInterface)) {
      return TRUE;
    }

    $type_id = $entity->getEntityTypeId();
    $uuid = $entity->uuid();

    // Do not process entity if it has already been written to the file system.
    if (
      !empty($context['results']['exported_entities'][$type_id]) &&
      in_array($uuid, $context['results']['exported_entities'][$type_id])
    ) {
      return TRUE;
    }

    // Do not process entity if it has already been skipped.
    if (
      !empty($context['results']['skipped_entities'][$type_id]) &&
      in_array($uuid, $context['results']['skipped_entities'][$type_id])
    ) {
      return TRUE;
    }

    $time = $this->getTime();
    if ($time && ($entity instanceof EntityChangedInterface && $entity->getChangedTimeAcrossTranslations() < $time)) {
      $context['results']['skipped_entities'][$type_id][] = $uuid;
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Writes serialized entity to a folder.
   *
   * @throws \Exception
   */
  private function writeSerializedEntity(string $entity_type, string $serialized_entity, string $uuid): void {
    // Ensure that the folder per entity type exists.
    $entity_type_folder = "{$this->getFolder()}/{$entity_type}";
    $this->fileSystem->prepareDirectory($entity_type_folder, FileSystemInterface::CREATE_DIRECTORY);

    file_put_contents("{$entity_type_folder}/{$uuid}.json", $serialized_entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getSerializedContent(ContentEntityInterface $entity, ?bool $add_metadata = TRUE): string {
    $folder = $this->getFolder();

    $event = new PreSerializeEvent($entity, $this->mode, $folder);
    $this->eventDispatcher->dispatch($event);
    $entity = $event->getEntity();

    // Entity could have been removed from the export by an event subscriber!
    if ($entity) {
      if (PHP_SAPI === 'cli') {
        $root_user = $this->getAdministrator();
        $this->accountSwitcher->switchTo($root_user);
      }

      $this->linkManager->setLinkDomain($this->getLinkDomain());
      $content = $this->serializer->serialize($entity, 'hal_json');

      $entity_array = $this->serializer->decode($content, 'json');

      // Remove revision.
      if ($entity->getEntityType()->hasKey('revision')) {
        unset($entity_array[$entity->getEntityType()->getKey('revision')]);
      }

      // Add user password hash.
      if ($entity->getEntityTypeId() === 'user') {
        $entity_array['pass'][0]['value'] = $entity->getPassword();
      }

      if ($add_metadata) {
        $entity_array['_dcd_metadata']['export_timestamp'] = \Drupal::time()->getRequestTime();
      }
      else {
        unset($entity_array['_dcd_metadata']);
      }

      $content = $this->serializer->serialize($entity_array, 'json', [
        'json_encode_options' => JSON_PRETTY_PRINT,
      ]);

      $event = new PostSerializeEvent($entity, $content, $this->mode, $folder);
      $this->eventDispatcher->dispatch($event);

      $this->linkManager->setLinkDomain(FALSE);

      if (PHP_SAPI === 'cli') {
        $this->accountSwitcher->switchBack();
      }

      return $event->getContent();
    }

    return '';
  }

  /**
   * Get the referenced entities without loading them.
   *
   * This is faster than calling referencedEntities() on the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *
   * @return array
   */
  protected function getReferencedEntityIds(ContentEntityInterface $entity): array {
    $referenced_entities = [];
    $skip_entity_types = $this->getSkipEntityTypeIds();

    // Gather a list of referenced entities.
    foreach ($entity->getFields() as $field_items) {
      foreach ($field_items as $field_item) {
        // Loop over all properties of a field item.
        foreach ($field_item->getProperties(TRUE) as $property) {
          if ($property instanceof EntityReference) {
            $entity_type = $property->getTargetDefinition()->getEntityTypeId();
            if (!in_array($entity_type, $skip_entity_types)) {
              try {
                $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type);
                if ($entity_type_definition->getGroup() === 'content') {
                  $referenced_entities[$entity_type] = $property->getTargetIdentifier();
                }
              }
              catch (\Exception $e) {
                // Ignore any broken definition.
              }
            }
          }
        }
      }
    }

    return $referenced_entities;
  }

  /**
   * Returns all layout builder referenced blocks of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Keyed array of entities indexed by entity type and ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getEntityLayoutBuilderDependencies(ContentEntityInterface $entity): array {
    $entity_dependencies = [];

    if ($this->moduleHandler->moduleExists('layout_builder') && !in_array('block_content', $this->getSkipEntityTypeIds())) {
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
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEntityProcessedTextDependencies(ContentEntityInterface $entity): array {
    $skip_entity_types = $this->getSkipEntityTypeIds();
    $entity_dependencies = [];

    $field_definitions = $entity->getFieldDefinitions();
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
          foreach ($xpath->query('//*[@data-entity-type and @data-entity-uuid]') as $node) {
            $entity_type = $node->getAttribute('data-entity-type');

            if (!in_array($entity_type, $skip_entity_types)) {
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
   * @param array $context
   *   The batch context.
   * @param int $depth
   *   Guard against infinite recursion.
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $indexed_dependencies
   *   Previously discovered dependencies.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Keyed array of entities indexed by UUID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getEntityReferencesRecursive(ContentEntityInterface $entity, array $context, ?int $depth = 0, ?array &$indexed_dependencies = []): array {
    $entity_dependencies = [];
    $languages = $entity->getTranslationLanguages();

    foreach (array_keys($languages) as $langcode) {
      $translation = $entity->getTranslation($langcode);
      $entityIds = $this->getReferencedEntityIds($translation);
      foreach ($entityIds as $entityTypeId => $entityId) {
        if (in_array($entityTypeId, $this->getSkipEntityTypeIds())) {
          continue;
        }

        // Ignore entity reference if the referenced entity could not be loaded.
        // Some entities have "NULL" references, for example a top level
        // taxonomy term references parent 0, which isn't an entity.
        if ($referenced_entity = $this->entityTypeManager->getStorage($entityTypeId)->load($entityId)) {
          $entity_dependencies[$entityTypeId][$entityId] = $referenced_entity;
        }
      }

      foreach ($this->getEntityLayoutBuilderDependencies($translation) as $referenced_entity) {
        if (in_array($referenced_entity->getEntityTypeId(), $this->getSkipEntityTypeIds())) {
          continue;
        }

        $entity_dependencies[$referenced_entity->getEntityTypeId()][$referenced_entity->id()] = $referenced_entity;
      }
    }

    if ($this->getTextDependencies()) {
      foreach ($this->getEntityProcessedTextDependencies($entity) as $referenced_entity) {
        if (in_array($referenced_entity->getEntityTypeId(), $this->getSkipEntityTypeIds())) {
          continue;
        }

        if ($referenced_entity instanceof ContentEntityInterface) {
          $entity_dependencies[$referenced_entity->getEntityTypeId()][$referenced_entity->id()] = $referenced_entity;
        }
        else {
          \Drupal::logger('default_content_deploy')->warning(
            t('Invalid text dependency found in @entity_type with ID @id', [
              '@entity_type' => $entity->getEntityTypeId(),
              '@id' => $entity->id(),
            ])
          );
        }
      }
    }

    foreach ($entity_dependencies as $entity_type_dependencies) {
      /** @var ContentEntityInterface $dependent_entity */
      foreach ($entity_type_dependencies as $dependent_entity) {
        // Using UUID to keep dependencies unique to prevent recursion.
        $key = $dependent_entity->uuid();
        if (!isset($indexed_dependencies[$key]) && !$this->skipEntity($dependent_entity, $context)) {
          $indexed_dependencies[$key] = $dependent_entity;
          // Build in some support against infinite recursion.
          if ($depth < 10) {
            $indexed_dependencies += $this->getEntityReferencesRecursive($dependent_entity, $context, $depth + 1, $indexed_dependencies);
          }
        }
      }
    }

    return $indexed_dependencies;
  }

}
