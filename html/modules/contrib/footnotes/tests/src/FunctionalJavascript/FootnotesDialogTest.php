<?php

namespace Drupal\Tests\footnotes\FunctionalJavascript;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\block\Functional\AssertBlockAppearsTrait;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\Tests\footnotes\Traits\FootnotesTestHelperTrait;

/**
 * Contains Footnotes Dialog JS alternative test.
 *
 * @group footnotes
 */
class FootnotesDialogTest extends WebDriverTestBase {

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
   * Tests dialog behaviour.
   */
  public function testDialogBehaviour() {

    // Build some default footnotes.
    $note1_build = $this->buildFootnote('Note <b>one</b> test');
    $note1 = $this->renderFootnote($note1_build);
    $note2_build = $this->buildFootnote('Note <b>two</b> test');
    $note2 = $this->renderFootnote($note2_build);
    $body = '<p>testFootnotesDialog' . $this->randomMachineName(100) . $note1 . '</p>';
    $body .= '<p>' . $this->randomMachineName(100) . $note2 . '</p>';

    // Create a node.
    $node = $this->drupalCreateNode([
      'title' => $this->randomString(),
      'type' => 'page',
      'body' => [
        'value' => $body,
        'format' => 'filtered_html',
      ],
    ]);
    $this->drupalGet('node/' . $node->id());

    // Check that an anchor link was clicked.
    $this->click('.js-footnote-citation');
    $this->assertStringContainsString('#', $this->getUrl());

    // Switch to dialog.
    $this->updateEditorSettings([
      'footnotes_dialog' => TRUE,
    ]);

    // Trigger cache clear of the node.
    // When a developer changes the settings of the footnotes
    // filter plugin, they are warned they must clear caches.
    $node->setTitle('Resave');
    $node->save();

    $this->drupalGet('node/' . $node->id());

    // Check that anchor link click was prevented.
    $this->click('.js-footnote-citation');
    $this->assertStringNotContainsString('#', $this->getUrl());

    // Check that the dialog exists and contains the footnote reference.
    $this->assertSession()->assertVisibleInViewport('css', '#js-footnotes-dialog');
    $this->assertSession()->elementContains('css', '#js-footnotes-dialog-citation-number', '1');
    $reference_html = 'Note <b>one</b> test';
    $this->assertSession()->elementContains('css', '#js-footnotes-dialog', $reference_html);

    // Check that the dialog CSS is attached.
    $this->assertSession()->responseContains('assets/js/footnotes.dialog.js');
    $this->assertSession()->responseContains('assets/css/footnotes-dialog.css');
    $this->assertSession()->responseContains('assets/css/footnotes.css');

    // Switch to CSS disabled.
    $this->updateEditorSettings([
      'footnotes_css' => FALSE,
    ]);
    $node->setTitle('Resave');
    $node->save();
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->responseNotContains('assets/css/footnotes.css');
  }

}
