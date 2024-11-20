<?php

namespace Drupal\convert_bundles;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions for the convert bundle options.
 */
class Permissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity Type Bundle Info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a new Permissions.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'), $container->get('entity_type.bundle.info'));
  }

  /**
   * Gets permissions.
   *
   * @return array
   *   Array of permissions.
   */
  public function get() {
    $permissions = [];
    $entity_type_definitions = $this->entityTypeManager->getDefinitions();

    foreach ($entity_type_definitions as $entity_type_definition) {
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_definition->id());
      if (count($bundles) < 2) {
        continue;
      }
      $permissions += $this->buildPermissions($entity_type_definition);
    }

    return $permissions;
  }

  /**
   * Returns a list of permissions for a given type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type_definition
   *   Entity Type Definition.
   *
   * @return array
   *   Array of permissions.
   */
  protected function buildPermissions(EntityTypeInterface $entity_type_definition) {
    $type_id = $entity_type_definition->id();
    $type_name = ['%type_name' => $entity_type_definition->getLabel()];

    return [
      "convert $type_id bundle" => [
        'title' => $this->t('%type_name: Convert bundle', $type_name),
      ],
    ];
  }

}
