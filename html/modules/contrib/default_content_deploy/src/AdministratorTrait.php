<?php

namespace Drupal\default_content_deploy;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\User;

trait AdministratorTrait {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @return \Drupal\user\Entity\User
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \RuntimeException
   */
  public function getAdministrator(): User {
    static $root_user = NULL;

    if (!$root_user) {
      $query = $this->entityTypeManager()->getStorage('user')->getQuery();
      $ids = $query->condition('status', 1)
        ->condition('roles', 'administrator')
        ->accessCheck(FALSE)
        ->execute();
      $users = User::loadMultiple($ids);
      $root_user = reset($users);
      if (!$root_user) {
        throw new \RuntimeException('No administrators found');
      }
    }

    return $root_user;
  }

  /**
   * Gets the Entity Type Manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The Entity Type Manager.
   */
  private function entityTypeManager(): EntityTypeManagerInterface {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }

    return $this->entityTypeManager;
  }

}
