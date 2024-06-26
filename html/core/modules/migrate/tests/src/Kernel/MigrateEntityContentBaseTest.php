<?php


namespace Drupal\Tests\migrate\Kernel;

use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Tests\StubTestTrait;
use Drupal\migrate_entity_test\Entity\StringIdEntityTest;

/**
 * Tests the EntityContentBase destination.
 *
 * @group migrate
 */
class MigrateEntityContentBaseTest extends KernelTestBase {

  use StubTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'migrate',
    'user',
    'language',
    'entity_test',
    'field',
  ];

  /**
   * The storage for entity_test_mul.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $storage;

  /**
   * A content migrate destination.
   *
   * @var \Drupal\migrate\Plugin\MigrateDestinationInterface
   */
  protected $destination;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Enable two required fields with default values: a single-value field and
    // a multi-value field.
    \Drupal::state()->set('entity_test.required_default_field', TRUE);
    \Drupal::state()->set('entity_test.required_multi_default_field', TRUE);
    $this->installEntitySchema('entity_test_mul');

    $this->installEntitySchema('entity_test_with_bundle');
    $this->installEntitySchema('entity_test_no_bundle');

    ConfigurableLanguage::createFromLangcode('en')->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $this->storage = $this->container->get('entity_type.manager')->getStorage('entity_test_mul');
  }

  /**
   * Check the existing translations of an entity.
   *
   * @param int $id
   *   The entity ID.
   * @param string $default
   *   The expected default translation language code.
   * @param string[] $others
   *   The expected other translation language codes.
   *
   * @internal
   */
  protected function assertTranslations(int $id, string $default, array $others = []): void {
    $entity = $this->storage->load($id);
    $this->assertNotEmpty($entity, "Entity exists");
    $this->assertEquals($default, $entity->language()->getId(), "Entity default translation");
    $translations = array_keys($entity->getTranslationLanguages(FALSE));
    sort($others);
    sort($translations);
    $this->assertEquals($others, $translations, "Entity translations");
  }

  /**
   * Create the destination plugin to test.
   *
   * @param array $configuration
   *   The plugin configuration.
   */
  protected function createDestination(array $configuration) {
    $this->destination = new EntityContentBase(
      $configuration,
      'fake_plugin_id',
      [],
      $this->createMock(MigrationInterface::class),
      $this->storage,
      [],
      $this->container->get('entity_field.manager'),
      $this->container->get('plugin.manager.field.field_type'),
      $this->container->get('account_switcher'),
      $this->container->get('entity_type.bundle.info')
    );
  }

  /**
   * Tests importing and rolling back translated entities.
   */
  public function testTranslated() {
    // Create a destination.
    $this->createDestination(['translations' => TRUE]);

    // Create some pre-existing entities.
    $this->storage->create(['id' => 1, 'langcode' => 'en'])->save();
    $this->storage->create(['id' => 2, 'langcode' => 'fr'])->save();
    $translated = $this->storage->create(['id' => 3, 'langcode' => 'en']);
    $translated->save();
    $translated->addTranslation('fr')->save();

    // Pre-assert that things are as expected.
    $this->assertTranslations(1, 'en');
    $this->assertTranslations(2, 'fr');
    $this->assertTranslations(3, 'en', ['fr']);
    $this->assertNull($this->storage->load(4));

    $destination_rows = [
      // Existing default translation.
      ['id' => 1, 'langcode' => 'en', 'action' => MigrateIdMapInterface::ROLLBACK_PRESERVE],
      // New translation.
      ['id' => 2, 'langcode' => 'en', 'action' => MigrateIdMapInterface::ROLLBACK_DELETE],
      // Existing non-default translation.
      ['id' => 3, 'langcode' => 'fr', 'action' => MigrateIdMapInterface::ROLLBACK_PRESERVE],
      // Brand new row.
      ['id' => 4, 'langcode' => 'fr', 'action' => MigrateIdMapInterface::ROLLBACK_DELETE],
    ];
    $rollback_actions = [];

    // Import some rows.
    foreach ($destination_rows as $idx => $destination_row) {
      $row = new Row();
      foreach ($destination_row as $key => $value) {
        $row->setDestinationProperty($key, $value);
      }
      $this->destination->import($row);

      // Check that the rollback action is correct, and save it.
      $this->assertEquals($destination_row['action'], $this->destination->rollbackAction());
      $rollback_actions[$idx] = $this->destination->rollbackAction();
    }

    $this->assertTranslations(1, 'en');
    $this->assertTranslations(2, 'fr', ['en']);
    $this->assertTranslations(3, 'en', ['fr']);
    $this->assertTranslations(4, 'fr');

    // Rollback the rows.
    foreach ($destination_rows as $idx => $destination_row) {
      if ($rollback_actions[$idx] == MigrateIdMapInterface::ROLLBACK_DELETE) {
        $this->destination->rollback($destination_row);
      }
    }

    // No change, update of existing translation.
    $this->assertTranslations(1, 'en');
    // Remove added translation.
    $this->assertTranslations(2, 'fr');
    // No change, update of existing translation.
    $this->assertTranslations(3, 'en', ['fr']);
    // No change, can't remove default translation.
    $this->assertTranslations(4, 'fr');
  }

  /**
   * Tests creation of ID columns table with definitions taken from entity type.
   */
  public function testEntityWithStringId() {
    $this->enableModules(['migrate_entity_test']);
    $this->installEntitySchema('migrate_string_id_entity_test');

    $definition = [
      'source' => [
        'plugin' => 'embedded_data',
        'data_rows' => [
          ['id' => 123, 'version' => 'foo'],
          // This integer needs an 'int' schema with 'big' size. If 'destid1'
          // is not correctly taking the definition from the destination entity
          // type, the import will fail with a SQL exception.
          ['id' => 123456789012, 'version' => 'bar'],
        ],
        'ids' => [
          'id' => ['type' => 'integer', 'size' => 'big'],
          'version' => ['type' => 'string'],
        ],
      ],
      'process' => [
        'id' => 'id',
        'version' => 'version',
      ],
      'destination' => [
        'plugin' => 'entity:migrate_string_id_entity_test',
      ],
    ];

    $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($definition);
    $executable = new MigrateExecutable($migration);
    $result = $executable->import();
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    /** @var \Drupal\migrate\Plugin\MigrateIdMapInterface $id_map_plugin */
    $id_map_plugin = $migration->getIdMap();

    // Check that the destination has been stored.
    $map_row = $id_map_plugin->getRowBySource(['id' => 123, 'version' => 'foo']);
    $this->assertEquals(123, $map_row['destid1']);
    $map_row = $id_map_plugin->getRowBySource(['id' => 123456789012, 'version' => 'bar']);
    $this->assertEquals(123456789012, $map_row['destid1']);
  }

  /**
   * Tests empty destinations.
   */
  public function testEmptyDestinations() {
    $this->enableModules(['migrate_entity_test']);
    $this->installEntitySchema('migrate_string_id_entity_test');

    $definition = [
      'source' => [
        'plugin' => 'embedded_data',
        'data_rows' => [
          ['id' => 123, 'version' => 'foo'],
          // This integer needs an 'int' schema with 'big' size. If 'destid1'
          // is not correctly taking the definition from the destination entity
          // type, the import will fail with an SQL exception.
          ['id' => 123456789012, 'version' => 'bar'],
        ],
        'ids' => [
          'id' => ['type' => 'integer', 'size' => 'big'],
          'version' => ['type' => 'string'],
        ],
        'constants' => ['null' => NULL],
      ],
      'process' => [
        'id' => 'id',
        'version' => 'version',
      ],
      'destination' => [
        'plugin' => 'entity:migrate_string_id_entity_test',
      ],
    ];

    $migration = \Drupal::service('plugin.manager.migration')
      ->createStubMigration($definition);
    $executable = new MigrateExecutable($migration);
    $executable->import();

    /** @var \Drupal\migrate_entity_test\Entity\StringIdEntityTest $entity */
    $entity = StringIdEntityTest::load('123');
    $this->assertSame('foo', $entity->version->value);
    $entity = StringIdEntityTest::load('123456789012');
    $this->assertSame('bar', $entity->version->value);

    // Rerun the migration forcing the version to NULL.
    $definition['process'] = [
      'id' => 'id',
      'version' => 'constants/null',
    ];

    $migration = \Drupal::service('plugin.manager.migration')
      ->createStubMigration($definition);
    $executable = new MigrateExecutable($migration);
    $executable->import();

    /** @var \Drupal\migrate_entity_test\Entity\StringIdEntityTest $entity */
    $entity = StringIdEntityTest::load('123');
    $this->assertNull($entity->version->value);
    $entity = StringIdEntityTest::load('123456789012');
    $this->assertNull($entity->version->value);
  }

  /**
   * Tests stub rows.
   */
  public function testStubRows() {
    // Create a destination.
    $this->createDestination([]);

    // Import a stub row.
    $row = new Row([], [], TRUE);
    $row->setDestinationProperty('type', 'test');
    $ids = $this->destination->import($row);
    $this->assertCount(1, $ids);

    // Make sure the entity was saved.
    $entity = EntityTestMul::load(reset($ids));
    $this->assertInstanceOf(EntityTestMul::class, $entity);
    // Make sure the default value was applied to the required fields.
    $single_field_name = 'required_default_field';
    $single_default_value = $entity->getFieldDefinition($single_field_name)->getDefaultValueLiteral();
    $this->assertSame($single_default_value, $entity->get($single_field_name)->getValue());

    $multi_field_name = 'required_multi_default_field';
    $multi_default_value = $entity->getFieldDefinition($multi_field_name)->getDefaultValueLiteral();
    $count = 3;
    $this->assertCount($count, $multi_default_value);
    for ($i = 0; $i < $count; ++$i) {
      $this->assertSame($multi_default_value[$i], $entity->get($multi_field_name)->get($i)->getValue());
    }
  }

  /**
   * Tests bundle is properly provided for stubs without bundle support.
   *
   * @todo Remove this test in when native PHP type-hints will be added for
   *   EntityFieldManagerInterface::getFieldDefinitions(). See
   *   https://www.drupal.org/project/drupal/issues/3050720.
   */
  public function testBundleFallbackForStub(): void {
    $this->enableModules(['migrate_entity_test']);
    $this->installEntitySchema('migrate_string_id_entity_test');

    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type_bundle_info = $this->container->get('entity_type.bundle.info');
    $entity_display_repository = $this
      ->container
      ->get('entity_display.repository');
    $typed_data_manager = $this->container->get('typed_data_manager');
    $language_manager = $this->container->get('language_manager');
    $keyvalue = $this->container->get('keyvalue');
    $module_handler = $this->container->get('module_handler');
    $cache_discovery = $this->container->get('cache.discovery');
    $entity_last_installed_schema_repository = $this
      ->container
      ->get('entity.last_installed_schema.repository');

    $decorated_entity_field_manager = new class ($entity_type_manager, $entity_type_bundle_info, $entity_display_repository, $typed_data_manager, $language_manager, $keyvalue, $module_handler, $cache_discovery, $entity_last_installed_schema_repository) extends EntityFieldManager {

      /**
       * {@inheritdoc}
       */
      public function getFieldDefinitions($entity_type_id, $bundle) {
        if (\is_null($bundle)) {
          throw new \Exception("Bundle value shouldn't be NULL.");
        }

        return parent::getFieldDefinitions($entity_type_id, $bundle);
      }

    };

    $this->container->set('entity_field.manager', $decorated_entity_field_manager);
    $this->createEntityStub('migrate_string_id_entity_test');
  }

  /**
   * Test destination fields() method.
   */
  public function testFields() {
    // Create two bundles for the entity_test_with_bundle entity type.
    $bundle_storage = $this->container->get('entity_type.manager')->getStorage('entity_test_bundle');
    $bundle_storage->create([
      'id' => 'test_bundle_no_fields',
      'label' => 'Test bundle without fields',
    ])->save();
    $bundle_storage->create([
      'id' => 'test_bundle_with_fields',
      'label' => 'Test bundle with fields',
    ])->save();

    // Test with a migration with a default bundle that does not have fields.
    $no_fields_definition = Yaml::decode(
      <<<EOT
      id: no_fields
      label: Migrate to no bundle specified destination
      source:
        plugin: embedded_data
        data_rows: {}
        constants:
          type: test_bundle_no_fields
      process:
        type: constants/type
        title: title
      destination:
        plugin: entity:entity_test_with_bundle
        default_bundle: test_bundle_no_fields
      EOT
    );

    $no_fields_migration = \Drupal::service('plugin.manager.migration')->createStubMigration($no_fields_definition);
    $no_fields_destination = $no_fields_migration->getDestinationPlugin();
    $this->assertArrayHasKey('id', $no_fields_destination->fields());
    $this->assertArrayNotHasKey('field_text', $no_fields_destination->fields());

    // Test with a migration with a default bundle that has fields.
    $with_fields_definition = Yaml::decode(
      <<<EOT
      id: with_fields
      label: Migrate to bundle specified destination
      source:
        plugin: embedded_data
        data_rows: {}
        constants:
          type: test_bundle_with_fields
      process:
        type: constants/type
        title: title
      destination:
        plugin: entity:entity_test_with_bundle
        default_bundle: test_bundle_with_fields
      EOT
    );

    $with_fields_migration = \Drupal::service('plugin.manager.migration')->createStubMigration($with_fields_definition);
    $with_fields_destination = $with_fields_migration->getDestinationPlugin();

    $this->assertArrayHasKey('id', $with_fields_destination->fields());
    $this->assertArrayNotHasKey('field_text', $with_fields_destination->fields());

    // Create a text field attached to 'test_node_type_with_fields' node type.
    FieldStorageConfig::create([
      'type' => 'string',
      'entity_type' => 'entity_test_with_bundle',
      'field_name' => 'field_text',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'entity_test_with_bundle',
      'bundle' => 'test_bundle_with_fields',
      'field_name' => 'field_text',
    ])->save();

    $this->assertArrayHasKey('field_text', $with_fields_destination->fields());
    // The no_fields migration has default bundle of test_bundle_no_fields so it
    // shouldn't show the fields on other node types.
    $this->assertArrayNotHasKey('field_text', $no_fields_destination->fields());

    // Test with an entity type with no bundles.
    $no_bundle_with_fields_definition = Yaml::decode(
      <<<EOT
      id: no_bundle_with_fields
      label: Migrate to specified destination
      source:
        plugin: embedded_data
        data_rows: {}
      process:
        title: title
      destination:
        plugin: entity:entity_test_no_bundle
      EOT
    );

    $no_bundle_with_fields_migration = \Drupal::service('plugin.manager.migration')->createStubMigration($no_bundle_with_fields_definition);
    $no_bundle_with_fields_destination = $no_bundle_with_fields_migration->getDestinationPlugin();

    $this->assertArrayHasKey('id', $no_bundle_with_fields_destination->fields());
    $this->assertArrayNotHasKey('field_text', $no_bundle_with_fields_destination->fields());

    // Create a text field attached to the entity_test_no_bundle entity.
    FieldStorageConfig::create([
      'type' => 'string',
      'entity_type' => 'entity_test_no_bundle',
      'field_name' => 'field_text',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'entity_test_no_bundle',
      'bundle' => 'entity_test_no_bundle',
      'field_name' => 'field_text',
    ])->save();

    $this->assertArrayHasKey('field_text', $no_bundle_with_fields_destination->fields());
  }

}
