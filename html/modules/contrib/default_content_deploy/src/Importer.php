<?php

namespace Drupal\default_content_deploy;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactory;
use Drupal\default_content_deploy\Event\PreSaveEntityEvent;
use Drupal\hal\LinkManager\LinkManagerInterface;
use Rogervila\ArrayDiffMultidimensional;
use Symfony\Component\Serializer\Serializer;

/**
 * A service for handling the import of content using batch API.
 */
class Importer {

  use DependencySerializationTrait;

  /**
   * Deploy manager.
   *
   * @var \Drupal\default_content_deploy\DeployManager
   */
  protected $deployManager;

  /**
   * The key-value store service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactory
   */
  protected $keyValueStorage;

  /**
   * Directory to import.
   *
   * @var string
   */
  private $folder;

  /**
   * Data to import.
   *
   * @var array
   */
  private $dataToImport = [];

  /**
   * Is remove changes of an old content.
   *
   * @var bool
   */
  protected $forceOverride;

  /**
   * The Entity repository manager.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The cache data.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The link manager service.
   *
   * @var \Drupal\hal\LinkManager\LinkManagerInterface
   */
  protected $linkManager;

  /**
   * DCD Exporter.
   *
   * @var \Drupal\default_content_deploy\Exporter
   */
  protected $exporter;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The event dispatcher.
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $eventDispatcher;

  /**
   * The batch context.
   *
   * @var array
   */
  protected $context;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Constructs the default content deploy manager.
   *
   * @param \Symfony\Component\Serializer\Serializer $serializer
   *   The serializer service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\hal\LinkManager\LinkManagerInterface $link_manager
   *   The link manager service.
   * @param \Drupal\default_content_deploy\DeployManager $deploy_manager
   *   Deploy manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The Entity repository manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache data.
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactory $key_value_factory
   *   The key value factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   */
  public function __construct(Serializer $serializer, EntityTypeManagerInterface $entity_type_manager, LinkManagerInterface $link_manager, DeployManager $deploy_manager, EntityRepositoryInterface $entity_repository, CacheBackendInterface $cache, Exporter $exporter, Connection $database, ContainerAwareEventDispatcher $event_dispatcher, KeyValueFactory $key_value_factory, ConfigFactoryInterface $config) {
    $this->serializer = $serializer;
    $this->entityTypeManager = $entity_type_manager;
    $this->linkManager = $link_manager;
    $this->deployManager = $deploy_manager;
    $this->entityRepository = $entity_repository;
    $this->cache = $cache;
    $this->exporter = $exporter;
    $this->database = $database;
    $this->eventDispatcher = $event_dispatcher;
    $this->keyValueStorage = $key_value_factory;
    $this->config = $config;
  }

  /**
   * Is remove changes of an old content.
   *
   * @param bool $is_override
   *
   * @return \Drupal\default_content_deploy\Importer
   */
  public function setForceOverride(bool $is_override) {
    $this->forceOverride = $is_override;
    return $this;
  }

  /**
   * Set directory to import.
   *
   * @param string $folder
   *   The content folder.
   *
   * @return \Drupal\default_content_deploy\Importer
   */
  public function setFolder(string $folder) {
    $this->folder = $folder;
    return $this;
  }

  /**
   * Get directory to import.
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
   * Get Imported data result.
   *
   * @return array
   */
  public function getResult() {
    return $this->dataToImport;
  }

  /**
   * Returns a list of file objects.
   *
   * @param string $directory
   *   Absolute path to the directory to search.
   *
   * @return object[]
   *   List of stdClass objects with name and uri properties.
   */
  public function scan($directory) {
    // Use Unix paths regardless of platform, skip dot directories, follow
    // symlinks (to allow extensions to be linked from elsewhere), and return
    // the RecursiveDirectoryIterator instance.
    $flags = \FilesystemIterator::UNIX_PATHS;
    $flags |= \FilesystemIterator::SKIP_DOTS;
    $flags |= \FilesystemIterator::CURRENT_AS_SELF;
    $directory_iterator = new \RecursiveDirectoryIterator($directory, $flags);
    $iterator = new \RecursiveIteratorIterator($directory_iterator);
    $files = $other_files = $alias_files = [];

    /* @var \SplFileInfo $file_info */
    foreach ($iterator as $file_info) {
      // Skip directories and non-json files.
      if ($file_info->isDir() || $file_info->getExtension() !== 'json') {
        continue;
      }

      $file = new \stdClass();
      $file->name = $file_info->getFilename();
      $file->uri = $file_info->getPathname();

      // Put url_alias files at the end of the array to ensure
      // nodes have already been imported.
      if (strpos($file->uri, '/url_alias/') !== false) {
        $alias_files[$file->uri] = $file;
      }
      else {
        $other_files[$file->uri] = $file;
      }
    }

    $files = array_merge($other_files, $alias_files);

    return $files;
  }

  /**
   * Import data from JSON and create new entities, or update existing.
   *
   * @return $this
   */
  public function import() {
    // Get a list of all files to import.
    $files = $this->scan($this->getFolder());

    // Save files to KeyValueStoreInterface to ensure they are not
    // stored in the class and subsequently added to the queue database table.
    $this->setFilesToImport($files);

    // Process files in batches.
    $operations = [];
    $total = count($files);
    $current = 1;

    if ($total == 0) {
      \Drupal::messenger()->addMessage(t('Nothing to import.'));
      return;
    }

    // Initialize progress tracking here.
    if (!isset($this->context['sandbox']['progress'])) {
      $this->context['sandbox']['progress'] = 0;
      $this->context['sandbox']['total'] = $total;
    }

    foreach ($files as $file) {
      $operations[] = [
        [$this, 'processFile'],
        [$file, $current, $total],
      ];

      $current++;
    }

    $batch = [
      'title' => t('Importing Content'),
      'operations' => $operations,
      'finished' => [$this, 'importFinished'],
    ];

    batch_set($batch);

    if (PHP_SAPI === 'cli') {
      // Process hatch with drush.
      drush_backend_batch_process();
    }
  }

  /**
   * Prepare file for import.
   *
   * @param $file
   *   The file object to use for import.
   * @param $current
   *   Indicates progress of the batch operations.
   * @param $total
   *   Total number of batch operations.
   * @param array &$context
   *   Reference to an array that stores the context of the batch process for status updates.
   *
   * @return $this
   *
   * @throws \Exception
   */
  public function processFile($file, $current, $total, &$context) {
    // Set files array into $context so we can access across batch operations.
    if (empty($context['results']['files'])) {
      $config = $this->config->get('default_content_deploy.content_directory');
      $support_old_content = $config->get('support_old_content');

      $context['results']['files'] = $this->getFilesToImport();
      $context['results']['start'] = microtime(TRUE);
      $context['results']['support_old_content'] = $support_old_content;
      // Delete KeyValueStorage values since they are now stored in $context.
      $this->deleteFilesToImport();
    }

    // Check that the current file has been processed already.
    if (!empty($context['results']['processed']) && in_array($file->name, $context['results']['processed'])) {
      $context['message'] = t('Importing entity @current of @total (@time)', [
        '@current' => $current,
        '@total' => $total,
        '@time' => $this->getElapsedTime($context['results']['start']),
      ]);

      return $this;
    }

    // Get parsed file contents.
    $parsed_data = file_get_contents($file->uri);

    // Try to decode the parsed data.
    try {
      $decode = $this->serializer->decode($parsed_data, 'hal_json');
    }
    catch (\Exception $e) {
      throw new \RuntimeException(sprintf('Unable to decode %s', $file->uri), $e->getCode(), $e);
    }

    // Get references for current entity.
    $references = $this->getReferences($decode, $context['results']['files']);

    // Record that we have checked references of current file.
    $context['results']['processed'][] = $file->name;

    // If there are references then process them recursively.
    if ($references) {
      foreach ($references as $reference) {
        $this->processFile($reference, $current, $total, $context);
      }
    }

    // Prepare data for import.
    $link = $decode['_links']['type']['href'];
    $data_to_import = [
      'data' => $decode,
      'entity_type_id' => $this->getEntityTypeByLink($link),
      'references' => $references,
      'filename' => $file->name,
    ];
    $this->preAddToImport($data_to_import, $context);

    // Import entity.
    $this->importEntity($data_to_import, $total, $current, $context);

    // Pass export data to results.
    $uuid = $data_to_import['data']['uuid'][0]['value'];
    $context['results']['data_to_import'][$uuid]['status'] = $data_to_import['status'];
    $context['message'] = t('Importing entity @current of @total (@time)', [
      '@current' => $current,
      '@total' => $total,
      '@time' => $this->getElapsedTime($context['results']['start']),
    ]);

    return $this;
  }

  /**
   * Manipulate entity data before importing.
   *
   * @param $data
   *   Entity data to manipulate before import.
   * @param array &$context
   *   Reference to an array that stores the context of the batch process for status updates.
   *
   * @return $this
   */
  protected function preAddToImport(&$data, &$context) {
    $decode = $data['data'];
    $uuid = $decode['uuid'][0]['value'];
    $entity_type_id = $data['entity_type_id'];
    $entity_type_object = $this->entityTypeManager->getDefinition($entity_type_id);

    // Get keys for entity.
    $key_id = $entity_type_object->getKey('id');
    $key_revision_id = $entity_type_object->getKey('revision');

    // Some old exports don't have the entity ID.
    if (isset($decode[$key_id][0]['value'])) {
      $context['results']['old_entity_lookup'][$decode[$key_id][0]['value']] = $uuid;
    }

    // Try to load the entity.
    $entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $uuid);

    if ($entity) {
      $data['entity_id'] = $entity->id();
      $is_new = FALSE;
      $status = 'update';

      // Replace entity ID.
      $decode[$key_id][0]['value'] = $entity->id();

      // Skip if the Changed time the same or less in the file.
      if ($entity instanceof EntityChangedInterface) {
        // If an entity was refactored to implement the EntityChangedInterface,
        // older exports don't contain the changed field.
        if (isset($decode['changed'])) {
          $changed_time_file = 0;
          foreach ($decode['changed'] as $changed) {
            $changed_time = strtotime($changed['value']);
            if ($changed_time > $changed_time_file) {
              $changed_time_file = $changed_time;
            }
          }

          if (!$this->forceOverride && $changed_time_file <= $entity->getChangedTimeAcrossTranslations()) {
            $status = 'skip';
          }
        }
      }
      elseif (!$this->forceOverride) {
        $this->linkManager->setLinkDomain($this->getLinkDomain($data));
        $current_entity_decoded = $this->serializer->decode($this->exporter->getSerializedContent($entity), 'hal_json');

        if ($entity_type_id == 'path_alias') {
          // Make sure the path matches to accound for updatePathAliasTargetId().
          $decode['path'] = $current_entity_decoded['path'];
          $decode['_links']['type']['href'] = $current_entity_decoded['_links']['type']['href'];
        }

        $diff = ArrayDiffMultidimensional::looseComparison($decode, $current_entity_decoded);
        if (!$diff) {
          $status = 'skip';
        }
      }
    }
    else {
      $status = 'create';
      $is_new = TRUE;

      // Ignore ID for creating a new entity.
      unset($decode[$key_id]);
    }

    // @see path_entity_base_field_info().
    // @todo offer an event to register other content types.
    if (in_array($entity_type_id, ['taxonomy_term', 'node', 'media'])) {
      unset($decode['path']);
    }

    // Ignore revision and id of entity.
    unset($decode[$key_revision_id]);

    $data['is_new'] = $is_new;
    $data['status'] = $status;
    $data['data'] = $decode;
    $data['key_id'] = $key_id;

    return $this;
  }

  /**
   * Imports a single entity as part of the batch process.
   *
   * @param array $data
   *   Array containing the entity data to be imported.
   * @param int $total
   *   Total number of entities to be processed in this batch for progress tracking.
   * @param int $current
   *   Index of the current entity being processed in the total batch.
   * @param array &$context
   *   Reference to an array that stores the context of the batch process for status updates.
   *
   * @return $this
   */
  public function importEntity($data, $total, $current, &$context) {
    $uuid = $data['data']['uuid'][0]['value'];
    $entity_status = $data['status'];

    if ($entity_status == 'skip') {
      // Skip this record, update ID lookup.
      $context['results']['entity_lookup'][$uuid] = $data['entity_id'];
    }
    else {
      $entity_type_id = $data['entity_type_id'];
      $entity_type_object = $this->entityTypeManager->getDefinition($entity_type_id);

      $this->linkManager->setLinkDomain($this->getLinkDomain($data));
      $class = $entity_type_object->getClass();

      // Processes to run against older content.
      if ($context['results']['support_old_content']) {
        // Update internal links.
        $this->updateInternalLinks($data, $context);
      }

      switch ($entity_type_id) {
        case 'path_alias':
          // Update url_alias reference to entity.
          $this->updatePathAliasTargetId($data, $context);
          break;

        case 'paragraph':
          // Update target revision IDs.
          $this->updateTargetRevisionId($data);
          break;
      }

      // Prepare the entity for save.
      $entity = $this->serializer->denormalize($data['data'], $class, 'hal_json', ['request_method' => 'POST']);
      $this->eventDispatcher->dispatch(new PreSaveEntityEvent($entity, $data));

      // Save entity.
      $entity->enforceIsNew($data['is_new']);
      $entity->save();

      // Update entity ID lookup with the UUID and entity ID.
      $context['results']['entity_lookup'][$uuid] = $entity->id();

      if ($entity_type_id === 'user') {
        // Workaround: store the hashed password directly in the database
        // and avoid the entity API which doesn't provide support for
        // setting password hashes directly.
        $hashed_pass = $data['pass'][0]['value'] ?? FALSE;
        if ($hashed_pass) {
          $this->database->update('users_field_data')
            ->fields([
              'pass' => $hashed_pass,
            ])
            ->condition('uid', $entity->id(), '=')
            ->execute();
        }
      }
    }

    // Provide update progress.
    $context['message'] = t('Importing entity @current of @total', ['@current' => $current, '@total' => $total]);

    return $this;
  }

  /**
   * Callback function to handle batch completion.
   *
   * @param bool $success
   *   Indicates whether the batch processing was successful.
   * @param array $results
   *   The stored results for batch processing.
   * @param array $operations
   *   An array of the operations that were run during the batch process.
   */
  public function importFinished($success, $results, $operations) {
    if ($success) {
      // Get elapsed time for the overall batch process.
      $elapsed_time = $this->getElapsedTime($results['start']);

      // Batch processing completed successfully.
      \Drupal::messenger()->addMessage(t('Batch import completed successfully in') . ' ' . $elapsed_time);

      // Output result counts.
      $results = !empty($results['data_to_import']) ? $results['data_to_import'] : [];
      $array_column = array_column($results, 'status');
      $count = array_count_values($array_column);

      \Drupal::messenger()->addMessage(t('Created: @count', [
        '@count' => isset($count['create']) ? $count['create'] : 0,
      ]));

      \Drupal::messenger()->addMessage(t('Updated: @count', [
        '@count' => isset($count['update']) ? $count['update'] : 0,
      ]));

      \Drupal::messenger()->addMessage(t('Skipped: @count', [
        '@count' => isset($count['skip']) ? $count['skip'] : 0,
      ]));
    }
    else {
      // Batch processing encountered an error.
      \Drupal::messenger()->addMessage(t('An error occurred during the batch import process.'), 'error');
    }
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

  /**
   * Gets url from file for set to Link manager.
   *
   * @param array $file
   */
  protected function getLinkDomain($file) {
    $link = $file['data']['_links']['type']['href'];
    $url_data = parse_url($link);
    $host = "{$url_data['scheme']}://{$url_data['host']}";
    return (!isset($url_data['port'])) ? $host : "{$host}:{$url_data['port']}";
  }

  /**
   * Get all reference by entity array content.
   *
   * @param array $content
   * @param array $files
   *
   * @return array
   */
  private function getReferences(array $content, $files) {
    $references = [];

    if (isset($content['_embedded'])) {
      foreach ($content['_embedded'] as $link) {
        foreach ($link as $reference) {
          if ($reference) {
            $uuid = $reference['uuid'][0]['value'];
            $path = $this->getPathToFileByName($uuid, $files);

            if ($path) {
              $references[$uuid] = $files[$path];
            }
          }
        }
      }
    }

    return $references;
  }

  /**
   * Get path to file by Name.
   *
   * @param string $name
   * @param array $files
   *
   * @return false|int|string
   */
  private function getPathToFileByName($name, $files) {
    $array_column = array_column($files, 'name', 'uri');
    return array_search($name . '.json', $array_column);
  }

  /**
   * Get reference to all JSON files for import.
   *
   * @return array|NULL
   */
  private function getFilesToImport() {
    // Get files from keyValueStorage.
    $files = $this->keyValueStorage->get('dcd_batch_files')->get('dcd_batch_files');

    return $files;
  }

  /**
   * Set reference for all JSON files to import.
   *
   * @param array $files
   *
   * @return array|NULL
   */
  private function setFilesToImport($files) {
    // Get files from keyValueStorage.
    $this->keyValueStorage->get('dcd_batch_files')->set('dcd_batch_files', $files);
  }

  /**
   * Delete reference to JSON files for import.
   *
   * @return array|NULL
   */
  private function deleteFilesToImport() {
    // Get files from keyValueStorage.
    $this->keyValueStorage->get('dcd_batch_files')->delete('dcd_batch_files');
  }

  /**
   * Get Entity type ID by link.
   *
   * @param string $link
   *
   * @return string|string[]
   */
  private function getEntityTypeByLink($link) {
    $type = $this->linkManager->getTypeInternalIds($link);

    if ($type) {
      $entity_type_id = $type['entity_type'];
    }
    else {
      $components = array_reverse(explode('/', $link));
      $entity_type_id = $components[1];
      // @todo remove this line when core is >= 9.2
      $this->cache->invalidate('hal:links:types');
    }

    return $entity_type_id;
  }

  /**
   * If this entity contains a reference field with target revision is value,
   * we should to update it.
   *
   * @param $decode
   *
   * @return $this
   */
  private function updateTargetRevisionId(&$decode) {
    if (isset($decode['_embedded'])) {
      foreach ($decode['_embedded'] as $link_key => $link) {
        if (array_column($link, 'target_revision_id')) {
          foreach ($link as $ref_key => $reference) {
            $url = $reference['_links']['type']['href'];
            $uuid = $reference['uuid'][0]['value'];
            $entity_type = $this->getEntityTypeByLink($url);
            $entity = $this->entityRepository->loadEntityByUuid($entity_type, $uuid);

            // Update the Target revision id if child entity exist on this site.
            if ($entity) {
              $revision_id = $entity->getRevisionId();
              $decode['_embedded'][$link_key][$ref_key]['target_revision_id'] = $revision_id;
            }
          }
        }
      }
    }

    return $this;
  }

  /**
   * Rewrite internal links to target entity IDs that were assigned during
   * import.
   *
   * @param array $decode
   *   The decoded entity.
   *
   * @return $this
   */
  private function updateInternalLinks(array &$decode, $context) {
    foreach ($decode['data'] as $field => $items) {
      foreach ($items as $index => $item) {
        foreach ($item as $name => $value) {
          if ('uri' === $name) {
            if (str_starts_with($value, 'internal:')) {
              $decode['data'][$field][$index][$name] = 'internal:' . $this->getUpdatedInternalPath(str_replace('internal:', '', $value), $context);
            }
            elseif (str_starts_with($value, 'entity:')) {
              $decode['data'][$field][$index][$name] = 'entity:' . trim($this->getUpdatedInternalPath(str_replace('entity:', '/', $value), $context, $item['target_uuid'] ?? NULL), '/');
            }
          }
        }
      }
    }

    return $this;
  }

  /**
   * Rewrite path aliases to target entity IDs that were assigned during import.
   *
   * @param array $decode
   *   The decoded entity.
   */
  private function updatePathAliasTargetId(array &$decode, $context) {
    if ($path = $decode['date']['path'][0]['value'] ?? NULL) {
      $decode['data']['path'][0]['value'] = $this->getUpdatedInternalPath($path, $context);
    }
  }

  /**
   * Rewrite a path to target entity IDs that were assigned during import.
   *
   * @param string $path
   *   The path from exported content.
   * @param string $uuid
   *   The UUID of the existing reference.
   *
   * @return string
   *   The updated path.
   */
  private function getUpdatedInternalPath($path, $context, $uuid = NULL): string {
    // The regex is designed to match URLs or paths where the structure consists of:
    //  - A first segment that is alphanumeric (including underscores),
    //  - Followed by a second segment that is purely numeric,
    //  - Optionally followed by path segments, query parameters, or fragment identifiers.
    // @todo this logic will not work with taxonomy_term entities /taxonomy/term/[ID]
    if (preg_match('@^/(\w+)/(\d+)([/?#].*|)$@', $path, $matches)) {
      if (!$uuid) {
        $oldEntityIdLookup = $context['results']['old_entity_lookup'];
        $uuid_lookup = $oldEntityIdLookup[$matches[2]] ?? [];

        if (!empty($uuid_lookup)) {
          $uuid = $uuid_lookup;
        }
      }

      if ($uuid) {
        $entityIdLookup = $context['results']['entity_lookup'];

        if ($id = $entityIdLookup[$uuid] ?? NULL) {
          return '/' . $matches[1] . '/' . $id . $matches[3];
        }
      }
    }

    return $path;
  }

}
