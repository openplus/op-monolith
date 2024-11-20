<?php

use Drupal\Core\Entity\EntityInterface;
use Drupal\taxonomy\TermInterface;
/**
 * @file
 * Document all supported APIs of convert_bundles module.
 */
/**
 * Provides an ability to alter entity during convert bundle process.
 *
 * @param \Drupal\Core\Entity\EntityInterface $old_entity
 *   The old entity object before its bundle get converted.
 * @param \Drupal\Core\Entity\EntityInterface $new_entity
 *   The entity object with already converted bundle and new fields.
 *
 * @return void
 */
function hook_convert_bundle_alter(EntityInterface $old_entity, EntityInterface &$new_entity): void {

  // Example to change parent of taxonomy term entity during
  // convert bundle process.
  if ($old_entity instanceof TermInterface
    && $new_entity instanceof TermInterface) {

    $new_entity->set('parent', ['target_id' => 123]);
  }
}
