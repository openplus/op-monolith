<?php

namespace Drupal\config_translation\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;

/**
 * Defines the config translation list builder for entity display entities.
 */
class ConfigTranslationEntityDisplayListBuilder extends ConfigTranslationFieldListBuilder {

  /**
   * Context display id.
   *
   * @var string
   */
  protected $displayContext;

  /**
   * Constructs a new ConfigTranslationEntityDisplayListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($entity_type, $storage, $entity_type_manager, $entity_type_bundle_info);
    // @todo There must be a better way to get this information?
    $this->displayContext = preg_replace('/^entity_(.+)_display$/', '\1', $this->entityType->id());
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    // It is not possible to use the standard load method, because this needs
    // all display entities only for the given baseEntityType.
    $ids = \Drupal::entityQuery($this->entityType->id())
      ->condition('id', $this->baseEntityType . '.', 'STARTS_WITH')
      ->execute();
    return $this->storage->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterLabels() {
    $info = parent::getFilterLabels();
    $bundle = $this->baseEntityInfo->getBundleLabel() ?: $this->t('Bundle');
    $bundle = mb_strtolower($bundle);
    $info['placeholder'] = $this->t('Enter mode or @bundle', [
      '@bundle' => $bundle,
    ]);
    $info['description'] = $this->t('Enter a part of the mode or @bundle to filter by.', [
      '@bundle' => $bundle,
    ]);
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = parent::buildRow($entity);
    $row['label']['data'] = $entity->getMode() == 'default' ? $this->t('Default') : $this->entityTypeManager
      ->getStorage('entity_' . $this->displayContext . '_mode')
      ->load($entity->getTargetEntityTypeId() . '.' . $entity->getMode())
      ->label();
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = parent::buildHeader();
    $header['label'] = $this->entityType->getLabel();
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    // Entity displays have no canonical no direct edit-form links so we
    // hard-code the route to the translation operation.
    // @todo Use config-translation-overview link template like field_ui does.
    $route_parameters = [
      $this->displayContext . '_mode_name' => $entity->getMode(),
    ];

    $bundle_type = $this->entityTypeManager
      ->getDefinition($entity->getTargetEntityTypeId())
      ->getBundleEntityType();
    if ($bundle_type) {
      $route_parameters[$bundle_type] = $entity->getTargetBundle();
    }

    $operations['translate'] = [
      'title' => $this->t('Translate'),
      'url' => $this->ensureDestination(Url::fromRoute("entity.{$this->entityType->id()}.config_translation_overview.{$entity->getTargetEntityTypeId()}", $route_parameters)),
    ];

    return $operations;
  }

}
