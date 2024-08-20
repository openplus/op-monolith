<?php

namespace Drupal\Tests\library_manager\Functional;

/**
 * Tests library definition assets.
 *
 * @group library_manager
 */
class LibraryDefinitionAssetsTest extends TestBase {

  /**
   * Test callback.
   */
  public function testCreateDefinition() {

    $libraries_path = DRUPAL_ROOT . '/sites/default/files/libraries/custom/';

    $edit = [
      'id' => 'beta',
      'version' => '1.0.0',
    ];
    $this->drupalGet('admin/structure/library/definition/add');
    $this->submitForm($edit, 'Save');

    // Create new file.
    $edit = [
      'file_name' => 'example-1.js',
      'code' => 'alert(123)',
    ];
    $this->drupalGet('admin/structure/library/definition/beta/js/add');
    $this->submitForm($edit, 'Save');
    $this->assertTrue(file_exists($libraries_path . 'beta/example-1.js'));

    // Rename the file.
    $edit = [
      'file_name' => 'example-2.js',
    ];
    $this->drupalGet('admin/structure/library/definition/beta/js/1/edit');
    $this->submitForm($edit, 'Save');
    $this->assertFalse(file_exists($libraries_path . 'beta/example-1.js'));
    $this->assertTrue(file_exists($libraries_path . 'beta/example-2.js'));
    // Delete the file.
    $this->drupalGet('admin/structure/library/definition/beta/js/1/delete');
    $this->submitForm([], 'Delete');
    $this->assertFalse(file_exists($libraries_path . 'beta/example-2.js'));

    // Create new file.
    $edit = [
      'file_name' => 'example-3.css',
      'code' => 'body {color: blue;}',
    ];
    $this->drupalGet('admin/structure/library/definition/beta/css/add');
    $this->submitForm($edit, 'Save');
    $this->assertTrue(file_exists($libraries_path . 'beta/example-3.css'));
    // Delete the definition.
    $this->drupalGet('admin/structure/library/definition/beta/delete');
    $this->submitForm([], 'Delete');
    $this->assertFalse(file_exists($libraries_path . 'beta'));
  }

}
