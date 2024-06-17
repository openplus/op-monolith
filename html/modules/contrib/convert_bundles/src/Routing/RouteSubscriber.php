<?php

namespace Drupal\convert_bundles\Routing;

use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for convert_bundles routes.
 *
 * @see \Drupal\convert_bundles\Plugin\Derivative\ConvertBundlesLocalTask
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a new RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, EntityTypeBundleInfo $entity_type_bundle_info) {
    $this->entityTypeManager = $entity_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      if (count($bundles) < 2) {
        continue;
      }

      $route = new Route("/$entity_type_id/{{$entity_type_id}}/convertbundles");
      $route
        ->addDefaults([
          '_controller' => '\Drupal\convert_bundles\Controller\EntityController::getEntity',
          '_title' => 'Convert Bundle',
        ])
        ->addRequirements([
          '_permission' => "convert $entity_type_id bundle",
        ])
        ->setOption('_admin_route', TRUE)
        ->setOption('_convert_bundles_entity_type_id', $entity_type_id)
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);

      $collection->add("entity.$entity_type_id.convert_bundles", $route);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', 100];
    return $events;
  }

}
