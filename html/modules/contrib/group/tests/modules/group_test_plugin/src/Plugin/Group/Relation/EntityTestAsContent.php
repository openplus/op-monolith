<?php

namespace Drupal\group_test_plugin\Plugin\Group\Relation;

use Drupal\group\Plugin\Group\Relation\GroupRelationBase;

/**
 * Provides a group relation type for test entities.
 *
 * @GroupRelationType(
 *   id = "entity_test_as_content",
 *   label = @Translation("Group test entity"),
 *   description = @Translation("Adds test entities to groups."),
 *   entity_type_id = "entity_test_with_owner",
 *   entity_access = TRUE,
 *   pretty_path_key = "entity_test_with_owner",
 *   reference_label = @Translation("Test entity"),
 *   reference_description = @Translation("The name of the test entity you want to add to the group"),
 *   admin_permission = "administer entity_test_as_content"
 * )
 */
class EntityTestAsContent extends GroupRelationBase {
}
