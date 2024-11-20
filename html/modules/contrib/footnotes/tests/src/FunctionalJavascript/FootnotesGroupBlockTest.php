<?php

namespace Drupal\Tests\footnotes\FunctionalJavascript;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\block\Functional\AssertBlockAppearsTrait;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\Tests\footnotes\Traits\FootnotesTestHelperTrait;

/**
 * Contains Footnotes Group Block JS alternative test.
 *
 * @group footnotes
 */
class FootnotesGroupBlockTest extends WebDriverTestBase {

  use StringTranslationTrait;
  use CKEditor5TestTrait;
  use FootnotesTestHelperTrait;
  use AssertBlockAppearsTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'ckeditor5',
    'filter',
    'ckeditor5_test',
    'footnotes',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpFormatAndEditor();
    $this->setUpNodeTypeAndField();
    $this->setUpDefaultUserWithAccess();
  }

  /**
   * Tests CKEditor5 plugin dialog loads.
   */
  public function testGroupBlockWithJsOption() {

    // Build some default footnotes.
    $note1_build = $this->buildFootnote('Note <b>one</b> test');
    $note1 = $this->renderFootnote($note1_build);
    $note2_build = $this->buildFootnote('Note <b>two</b> test');
    $note2 = $this->renderFootnote($note2_build);
    $body = '<p>testFootnotesFooterDisable' . $this->randomMachineName(100) . $note1 . '</p>';
    $body .= '<p>' . $this->randomMachineName(100) . $note2 . '</p>';

    // Create a node.
    $node = $this->drupalCreateNode([
      'title' => $this->randomString(),
      'type' => 'page',
      'body' => [
        'value' => $body,
        'format' => 'filtered_html',
      ],
      'body2' => [
        'value' => $body,
        'format' => 'filtered_html',
      ],
    ]);
    $this->drupalGet('node/' . $node->id());

    // Check the footnotes in their default position.
    $footnotes_reference_areas = $this->getSession()->getPage()->findAll('css', '.js-footnotes');
    foreach ($footnotes_reference_areas as $footnotes_reference_area) {
      $reference_text = $footnotes_reference_area->getText();

      // For both reference sections, the automated numbering should start
      // at 1. So we will have 1,2,1,2. Only once we combine them
      // should we then have 1,2,3,4. This is tested below.
      $expected_text = '1 Note one test 2 Note two test';
      $this->assertSame($expected_text, $reference_text);
    }

    // Add the footnotes group block.
    $settings = [
      'theme' => 'stark',
      'region' => 'footer',
      'group_via_js' => TRUE,
    ];
    $this->drupalPlaceBlock('footnotes_group', $settings);

    // Trigger cache clear of the node.
    // When a developer changes the settings of the footnotes
    // filter plugin, they are warned they must clear caches.
    $node->setTitle('Resave');
    $node->save();

    $this->drupalGet('node/' . $node->id());

    // Lazy loaded JS block for the footnotes group should now appear.
    $this->assertSession()->waitForElementVisible('css', '#footnotes_group .js-footnote-reference');

    // The original footnotes reference area should now be gone.
    $non_block_footnote_reference_area = $this->getSession()->getPage()->find('css', '.js-footnotes');
    $this->assertNull($non_block_footnote_reference_area);

    // The new footnotes block should have the automated numbers reset. So
    // instead of 1,2,1,2, we should now have 1,2,3,4.
    $footnotes_group_text = $this->getSession()->getPage()->find('css', '#footnotes_group')->getText();
    $this->htmlOutput('Combined reference text: ' . $footnotes_group_text);
    $expected_text = '1 Note one test 2 Note two test 3 Note one test 4 Note two test';
    $this->assertSame($expected_text, $footnotes_group_text);
  }

}
