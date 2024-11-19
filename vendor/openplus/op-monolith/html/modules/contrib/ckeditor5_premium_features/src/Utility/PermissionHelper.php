<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Utility;

use Drupal\ckeditor5_premium_features\CollaborationPermissions;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Helper class for handling text format permissions.
 */
class PermissionHelper {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(
    protected EntityTypeManager $entityTypeManager
  ) {
  }

  /**
   * Revokes collaboration permissions for given text format for all roles.
   *
   * @param \Drupal\filter\Entity\FilterFormat $filterFormat
   *   The filter format entity.
   */
  public function deleteCollaborationPermissions($filterFormat) {
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    foreach ($roles as $role) {
      $permissions = CollaborationPermissions::PERMISSIONS;
      foreach ($permissions as $permission) {
        $permissionName = CollaborationPermissions::getPermissionName($filterFormat, $permission);
        if ($role->hasPermission($permissionName)) {
          $role->revokePermission($permissionName);
        }
      }
      $role->save();
    }
  }

}
