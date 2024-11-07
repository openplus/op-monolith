<?php
// phpcs:ignoreFile
/**
 * @file
 * A database agnostic dump for testing purposes.
 *
 * This file was generated by the Drupal 9.2.6 db-tools.php script.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

include 'drupal7' . DIRECTORY_SEPARATOR . 'field_config.php';
include 'drupal7' . DIRECTORY_SEPARATOR . 'field_config_instance.php';
include 'drupal7' . DIRECTORY_SEPARATOR . 'filter.php';
include 'drupal7' . DIRECTORY_SEPARATOR . 'filter_format.php';
include 'drupal7' . DIRECTORY_SEPARATOR . 'node_type.php';
include 'drupal7' . DIRECTORY_SEPARATOR . 'system.php';
include 'drupal7' . DIRECTORY_SEPARATOR . 'variable.php';
