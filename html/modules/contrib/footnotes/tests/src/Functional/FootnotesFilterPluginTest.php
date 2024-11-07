<?php

namespace Drupal\Tests\footnotes\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\footnotes\Traits\FootnotesTestHelperTrait;

/**
 * Contains Footnotes Filter plugin functionality tests.
 *
 * @group footnotes
 */
class FootnotesFilterPluginTest extends BrowserTestBase {

  use StringTranslationTrait;
  use FootnotesTestHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'footnotes',
    'footnotes_test',
    'node',
    'block',
    'block_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

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
   * Tests CKEditor Filter plugin functionality.
   */
  public function testDefaultFunctionality() {
    // Build some default footnotes.
    $note1_build = $this->buildFootnote('Note <b>one</b> test');
    $note1 = $this->renderFootnote($note1_build);
    $note2_build = $this->buildFootnote('Note <b>two</b><ul><li><a href="https://drupal.org">Test</a> unordered list</li></ul> test');
    $note2 = $this->renderFootnote($note2_build);
    $body = '<p>' . $this->randomMachineName(100) . $note1 . '</p>';
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

    // Check the footnotes are rendered somewhere.
    $this->assertSession()->responseNotContains('<footnotes>');
    $this->assertSession()->responseContains($note1_build['#attributes']['data-text'][0]);
    $this->assertSession()->responseContains($note2_build['#attributes']['data-text'][0]);

    // Check that the automated numbering is output.
    $citations = $this->getSession()->getPage()->findAll('css', '.footnote__citation');
    $this->assertCount(2, $citations);
    foreach ($citations as $index => $citation) {
      $this->assertSame((string) ($index + 1), $citation->getText());
    }

    // Check that the footnotes are output on the page and contain the markup.
    $this->assertSession()->elementTextContains('css', '.footnotes', strip_tags($note1_build['#attributes']['data-text'][0]));
    $this->assertSession()->elementTextContains('css', '.footnotes', strip_tags($note2_build['#attributes']['data-text'][0]));

    // Css file exists.
    $this->assertSession()->responseContains('/assets/css/footnotes.css');
  }

  /**
   * Tests rendering the footnote references into a placeholder.
   */
  public function testFootnotesRenderPlaceholder() {
    // Build some default footnotes.
    $note1_build = $this->buildFootnote('Note <b>one</b> test');
    $note1 = $this->renderFootnote($note1_build);
    $note2_build = $this->buildFootnote('Note <b>two</b> test');
    $note2 = $this->renderFootnote($note2_build);
    $body = '<p>testFootnotesRenderPlaceholder' . $this->randomMachineName(100) . $note1 . '</p>';
    $body .= '<p>' . $this->randomMachineName(100) . $note2 . '</p>';
    $body .= '<footnotes-placeholder></footnotes-placeholder>';
    $body .= '<span class="after-footnotes">Test after footnotes</span>';

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

    // Ensure the footnotes section exists prior to the
    // 'after footnotes' text.
    $text = $this->getSession()->getPage()->find('css', 'article div div')->getText();
    $this->assertStringContainsString('Note two testTest after footnotes', $text);

    // Ensure there is only 1 footnotes section.
    $footnotes_reference_sections = $this->getSession()->getPage()->findAll('css', '.footnotes');
    $this->assertCount(1, $footnotes_reference_sections);
  }

  /**
   * Tests that placeholder <footnotes> renders the footnotes.
   */
  public function testMultipleFootnotesCombined() {
    // Build some default footnotes.
    $note1_build = $this->buildFootnote('Note <b>one</b> test');
    $note1 = $this->renderFootnote($note1_build);
    $note2_build = $this->buildFootnote('Note <b>two</b> test');
    $note2 = $this->renderFootnote($note2_build);
    $body = '<p>testMultipleFootnotesCombined' . $this->randomMachineName(100) . $note1 . $note2 . '</p>';

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
    $citation_wrapper = $this->getSession()->getPage()->find('css', '.footnote-multiple-citations-wrapper');
    $citations = $citation_wrapper->findAll('css', 'a');
    $this->assertCount(2, $citations);
  }

  /**
   * Tests that identical footnote contents get collapsed.
   */
  public function testCollapseFootnotes() {
    // Build some default footnotes.
    $note1_build = $this->buildFootnote('Note <b>one</b> test');
    $note1 = $this->renderFootnote($note1_build);
    $note2_build = $this->buildFootnote('Note <b>two</b> test');
    $note2 = $this->renderFootnote($note2_build);
    $body = '<p>testCollapseFootnotes' . $this->randomMachineName(100) . $note1 . $note2 . $note1 . '</p>';
    $body .= '<p>Test ' . $note1 . '</p>';

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
    $page = $this->getSession()->getPage();
    $text = $page->find('css', 'article div div')->getText();

    // We expect 3 citations, but 1-2-1 since the 3rd is identical to
    // the first.
    $this->assertCount(4, $page->findAll('css', '.footnote__citation'));
    $this->assertStringContainsString('123 Test 4', $text);

    // Switch to collapsing.
    $this->updateEditorSettings([
      'footnotes_collapse' => TRUE,
      'footnotes_css' => TRUE,
      'footnotes_footer_disable' => FALSE,
    ]);

    // Trigger cache clear of the node.
    // When a developer changes the settings of the footnotes
    // filter plugin, they are warned they must clear caches.
    $node->setTitle('Resave');
    $node->save();

    $this->drupalGet('node/' . $node->id());
    $page = $this->getSession()->getPage();
    $text = $page->find('css', 'article div div')->getText();

    // We expect 3 citations, but 1-2-1 since the 3rd is identical to
    // the first.
    $this->assertCount(4, $page->findAll('css', '.footnote__citation'));
    $this->assertStringContainsString('121 Test 1', $text);

    // Ensure the footnotes reference section has 2 items, but 3 backlinks.
    $footnotes_reference_section = $this->getSession()->getPage()->find('css', '.footnotes');
    $this->assertCount(2, $footnotes_reference_section->findAll('css', '.footnotes__item-wrapper'));
    $this->assertCount(4, $footnotes_reference_section->findAll('css', '.footnotes__item-backlink'));
    $this->assertSame('1a1b1cNote one test2Note two test', $footnotes_reference_section->getText());
  }

  /**
   * Tests disabled footnotes appear in the block instead.
   */
  public function testFootnotesFooterDisable() {
    $this->updateEditorSettings([
      'footnotes_collapse' => FALSE,
      'footnotes_css' => TRUE,
      'footnotes_footer_disable' => TRUE,
    ]);

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
    ]);

    $this->drupalGet('node/' . $node->id());

    // Check the footnotes are not rendered anywhere.
    $this->assertSession()->responseNotContains('<footnotes>');
    $this->assertSession()->responseNotContains($note1_build['#attributes']['data-text'][0]);
    $this->assertSession()->responseNotContains($note2_build['#attributes']['data-text'][0]);

    // Add the footnotes group block.
    $settings = [
      'theme' => 'stark',
      'region' => 'footer',
    ];
    $this->drupalPlaceBlock('footnotes_group', $settings);

    // Trigger cache clear of the node.
    // When a developer changes the settings of the footnotes
    // filter plugin, they are warned they must clear caches.
    $node->setTitle('Resave');
    $node->save();

    // Check the footnotes are now rendered again, but in the block.
    $this->drupalGet('node/' . $node->id());
  }

  /**
   * Tests plain manual html.
   */
  public function testPlainManualHtml() {
    // Build a footnote with the markup
    // <footnotes data-value="">Note <b>one</b> test</footnotes>
    // as this is needed for content editors not using
    // CK Editor and manually inputting footnotes.
    $html = 'Note <b>one</b> test';
    $note1_build = [
      '#type' => 'html_tag',
      '#tag' => 'footnotes',
      '#value' => $html,
      '#attributes' => [
        'data-value' => [],
      ],
    ];
    $note1 = $this->renderFootnote($note1_build);
    $body = '<p>' . $this->randomMachineName(100) . $note1 . '</p>';

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

    // Check the footnotes are rendered somewhere.
    $this->assertSession()->responseNotContains('<footnotes>');
    $this->assertSession()->responseContains($html);
  }

  /**
   * Tests citations with unique texts but same (duplicate) value.
   */
  public function testCitationUniqueTextSameValue() {
    $this->updateEditorSettings([
      'footnotes_collapse' => TRUE,
      'footnotes_css' => TRUE,
      'footnotes_footer_disable' => FALSE,
    ]);

    // Build a footnote with two citations have unique texts but the same value
    // and ensure that the unique texts forces the references to output
    // on separate lines.
    $note1_build = $this->buildFootnote('Note <b>one</b> test one', '1');
    $note1 = $this->renderFootnote($note1_build);
    $note2_build = $this->buildFootnote('Note <b>two</b> test', '2');
    $note2 = $this->renderFootnote($note2_build);
    $note3_build = $this->buildFootnote('Note <b>one</b> test two', '1');
    $note3 = $this->renderFootnote($note3_build);
    $body = '<p>testCitationUniqueTextSameValue' . $this->randomMachineName(100) . $note1 . '</p>';
    $body .= '<p>' . $this->randomMachineName(100) . $note2 . '</p>';
    $body .= '<p>' . $this->randomMachineName(100) . $note3 . '</p>';

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

    // Ensure the footnotes reference section has 3 items and 3 backlinks.
    // The items with 1 as the value, get grouped together, but still
    // remain as separate lines since the citation texts differ.
    $footnotes_reference_section = $this->getSession()->getPage()->find('css', '.footnotes');
    $this->assertSame('1Note one test one1Note one test two2Note two test', $footnotes_reference_section->getText());
    $this->assertCount(3, $footnotes_reference_section->findAll('css', '.footnotes__item-wrapper'));
    $this->assertCount(3, $footnotes_reference_section->findAll('css', '.footnotes__item-backlink'));
  }

}
