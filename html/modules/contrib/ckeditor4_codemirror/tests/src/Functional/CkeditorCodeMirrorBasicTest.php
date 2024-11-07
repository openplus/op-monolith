<?php

namespace Drupal\Tests\ckeditor4_codemirror\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;

/**
 * Test enabling the module and adding source highlighting to a text format.
 *
 * @ingroup ckeditor4_codemirror
 *
 * @group ckeditor4_codemirror
 */
class CkeditorCodeMirrorBasicTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'node',
    'editor',
    'ckeditor',
    'ckeditor4_codemirror',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The user for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $privilegedUser;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create text format, associate CKEditor.
    $full_html_format = FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
      'weight' => 0,
      'filters' => [],
    ]);
    $full_html_format->save();
    $editor = Editor::create([
      'format' => 'full_html',
      'editor' => 'ckeditor',
    ]);
    $editor->save();

    // Create node type.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    $this->privilegedUser = $this->drupalCreateUser([
      'administer site configuration',
      'administer filters',
      'create article content',
      'edit any article content',
      'use text format full_html',
    ]);
    $this->drupalLogin($this->privilegedUser);
  }

  /**
   * Check the library status on "Status report" page.
   */
  public function testCheckStatusReportPage() {
    $this->container->get('module_handler')->loadInclude('install', 'ckeditor4_codemirror');

    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('admin/reports/status');

    $library_path = _ckeditor4_codemirror_get_library_path();
    if (file_exists(DRUPAL_ROOT . '/' . $library_path . '/codemirror/plugin.js')) {
      $this->assertSession()->responseContains(
        $this->t('CKEditor4 CodeMirror plugin version %version installed at %path.',
          [
            '%path' => base_path() . $library_path,
            '%version' => _ckeditor4_codemirror_get_version(),
          ])
      );
    }
    else {
      $this->assertSession()->pageTextContains('CKEditor4 CodeMirror plugin was not found.');
    }
  }

  /**
   * Enable CKEditor4 CodeMirror plugin.
   */
  public function testEnableCkeditorCodeMirrorPlugin() {
    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('admin/config/content/formats/manage/full_html');
    $this->assertSession()->pageTextContains('Enable CodeMirror source view syntax highlighting.');
    $this->assertSession()->checkboxNotChecked('edit-editor-settings-plugins-codemirror-enable');

    // Enable the plugin.
    $edit = ['editor[settings][plugins][codemirror][enable]' => '1'];
    $this->submitForm($edit, $this->t('Save configuration'));
    $this->assertSession()->pageTextContains($this->t('The text format Full HTML has been updated.'));

    // Check for the plugin on node add page.
    $this->drupalGet('node/add/article');
    $editor_settings = $this->getDrupalSettings()['editor']['formats']['full_html']['editorSettings'];

    $library_path = _ckeditor4_codemirror_get_library_path();
    if (file_exists(DRUPAL_ROOT . '/' . $library_path . '/codemirror/plugin.js')) {
      $ckeditor_enabled = $editor_settings['codemirror']['enable'];
      $this->assertTrue($ckeditor_enabled);
    }
  }

}
