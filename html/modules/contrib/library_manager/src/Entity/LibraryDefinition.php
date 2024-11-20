<?php

namespace Drupal\library_manager\Entity;

use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\file\Entity\File;
use Drupal\library_manager\LibraryDefinitionInterface;

/**
 * Defines the library definition entity type.
 *
 * @ConfigEntityType(
 *   id = "library_definition",
 *   label = @Translation("Library definition"),
 *   handlers = {
 *     "list_builder" = "Drupal\library_manager\Controller\LibraryDefinitionListBuilder",
 *     "form" = {
 *       "add" = "Drupal\library_manager\Form\LibraryDefinitionForm",
 *       "edit" = "Drupal\library_manager\Form\LibraryDefinitionForm",
 *       "delete" = "Drupal\library_manager\Form\LibraryDefinitionDeleteForm",
 *       "duplicate" = "Drupal\library_manager\Form\LibraryDefinitionDuplicateForm",
 *       "build" = "Drupal\library_manager\Form\LibraryDefinitionBuildForm",
 *       "add_js" = "Drupal\library_manager\Form\LibraryDefinitionJsForm",
 *       "edit_js" = "Drupal\library_manager\Form\LibraryDefinitionJsForm",
 *       "delete_js" = "Drupal\library_manager\Form\LibraryDefinitionJsDeleteForm",
 *       "add_css" = "Drupal\library_manager\Form\LibraryDefinitionCssForm",
 *       "edit_css" = "Drupal\library_manager\Form\LibraryDefinitionCssForm",
 *       "delete_css" = "Drupal\library_manager\Form\LibraryDefinitionCssDeleteForm"
 *     }
 *   },
 *   config_prefix = "library_definition",
 *   admin_permission = "administer libraries",
 *   links = {
 *     "collection" = "/admin/structure/libraries/definitions",
 *     "add-form" = "/admin/structure/libraries/definitions/add",
 *     "edit-form" = "/admin/structure/libraries/definitions/{library_definition}",
 *     "delete-form" = "/admin/structure/libraries/definitions/{library_definition}/delete",
 *     "duplicate-form" = "/admin/structure/libraries/definitions/{library_definition}/duplicate",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id" = "id",
 *     "target" = "target",
 *     "remote" = "remote",
 *     "version" = "version",
 *     "license" = "license",
 *     "js" = "js",
 *     "css" = "css",
 *     "library_dependencies" = "library_dependencies",
 *     "load" = "load",
 *     "visibility" = "visibility"
 *   }
 * )
 */
class LibraryDefinition extends ConfigEntityBase implements LibraryDefinitionInterface, EntityWithPluginCollectionInterface {

  /**
   * The library definition ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The target library.
   *
   * @var string
   */
  protected $target;

  /**
   * The URL of the library.
   *
   * @var string
   */
  protected $remote;

  /**
   * The version of the library.
   *
   * @var string
   */
  protected $version;

  /**
   * The version of the library.
   *
   * @var array
   */
  protected $license = [
    'name' => '',
    'url' => '',
    'gpl-compatible' => FALSE,
  ];

  /**
   * The library JS files.
   *
   * @var array
   */
  protected $js = [];

  /**
   * The library CSS files.
   *
   * @var array
   */
  protected $css = [];

  /**
   * The library dependencies.
   *
   * @var array
   */
  protected $library_dependencies = [];

  /**
   * The library visibility settings.
   *
   * @var array
   *
   * @see library_manager_page_attachments()
   */
  protected $visibility = [];

  /**
   * {@inheritdoc}
   */
  public function getJsFile($file_name) {
    return isset($this->js[$file_name]) ? $this->js[$file_name] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCssFile($file_name) {
    return isset($this->css[$file_name]) ? $this->css[$file_name] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibility() {
    return $this->getVisibilityConditions()->getConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setVisibilityConfig($instance_id, array $configuration) {
    $conditions = $this->getVisibilityConditions();
    if (!$conditions->has($instance_id)) {
      $configuration['id'] = $instance_id;
      $conditions->addInstanceId($instance_id, $configuration);
    }
    else {
      $conditions->setInstanceConfiguration($instance_id, $configuration);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibilityConditions() {
    if (!isset($this->visibilityCollection)) {
      $this->visibilityCollection = new ConditionPluginCollection($this->conditionPluginManager(), $this->get('visibility'));
    }
    return $this->visibilityCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibilityCondition($instance_id) {
    return $this->getVisibilityConditions()->get($instance_id);
  }

  /**
   * Gets the condition plugin manager.
   *
   * @return \Drupal\Core\Executable\ExecutableManagerInterface
   *   The condition plugin manager.
   */
  protected function conditionPluginManager() {
    if (!isset($this->conditionPluginManager)) {
      $this->conditionPluginManager = \Drupal::service('plugin.manager.condition');
    }
    return $this->conditionPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return ['visibility' => $this->getVisibilityConditions()];
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    if ($update) {
      $original_upload_file_ids = [];
      /** @var \Drupal\library_manager\Entity\LibraryDefinition $orignal */
      $original = $this->original;
      $original_css = $original->get('css');
      $original_js = $original->get('js');
      foreach ($original_css as $value) {
        if (!empty($value['file_upload'])) {
          $original_upload_file_ids[$value['file_upload']] = $value['file_upload'];
        }
      }
      foreach ($original_js as $value) {
        if (!empty($value['file_upload'])) {
          $original_upload_file_ids[$value['file_upload']] = $value['file_upload'];
        }
      }

      $upload_file_ids = [];
      $css = $this->get('css');
      $js = $this->get('js');
      foreach ($css as $value) {
        if (!empty($value['file_upload'])) {
          $upload_file_ids[$value['file_upload']] = $value['file_upload'];
        }
      }
      foreach ($js as $value) {
        if (!empty($value['file_upload'])) {
          $upload_file_ids[$value['file_upload']] = $value['file_upload'];
        }
      }

      /** @var FileUsageInterface $file_usage */
      $file_usage = \Drupal::service('file.usage');
      $entity_id = $this->id();
      foreach ($upload_file_ids as $file_id) {
        if (!isset($original_upload_file_ids[$file_id])) {
          $file = File::load($file_id);
          if (!empty($file)) {
            $file_usage->add($file, 'library_manager', 'library_definition', $entity_id);
          }
        }
      }
      foreach ($original_upload_file_ids as $file_id) {
        if (!isset($upload_file_ids[$file_id])) {
          $file = File::load($file_id);
          if (!empty($file)) {
            $file_usage->delete($file, 'library_manager', 'library_definition', $entity_id);
          }
        }
      }
    }
    parent::postSave($storage, $update);
    \Drupal::service('library.discovery')->clearCachedDefinitions();
    drupal_static_reset('library_manager_build_libraries');
    \Drupal::service('library.discovery')->getLibraryByName('library_manager', $this->id);
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);
    $libraries_path = \Drupal::config('library_manager.settings')->get('libraries_path');
    foreach ($entities as $entity) {
      try {
        \Drupal::service('file_system')->deleteRecursive($libraries_path . '/' . $entity->id());
      }
      catch (\Exception $e) {
        // There was a transient error.
      }
    }
  }

}
