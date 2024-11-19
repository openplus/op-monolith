<?php

namespace Drupal\Tests\footnotes\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\footnotes\Upgrade\FootnotesUpgradeBatchManager;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\footnotes\Traits\FootnotesTestHelperTrait;
use Drush\TestTraits\DrushTestTrait;

/**
 * Contains Footnotes Filter plugin functionality tests.
 *
 * @group footnotes
 */
class FootnotesUpgradeTest extends BrowserTestBase {
  use DrushTestTrait;
  use StringTranslationTrait;
  use FootnotesTestHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'footnotes',
    'footnotes_upgrade_test',
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
  public function testUpgrade3to4() {
    $original = '<p>Aenean viverra rhoncus pede. Donec posuere 
           vulputate arcu. <fn value="">Test footnote 1</fn>Fusce a quam. Aenean ut eros et nisl 
           sagittis vestibulum. Nulla neque dolor, <fn value="specific-value">Test footnote 2</fn>sagittis eget, 
           iaculis[fn value=""]Test footnote 3[/fn]quis, molestie[fn value="another-value"]Test footnote 4[/fn] 
           <fn value=test>Test footnote 5</fn>non, velit[fn]Test footnote 6[/fn].</p>';
    $expected_updated_with_data_text = '<p>Aenean viverra rhoncus pede. Donec posuere vulputate arcu. <footnotes data-value="" data-text="Test footnote 1"></footnotes>Fusce a quam. Aenean ut eros et nisl sagittis vestibulum. Nulla neque dolor, <footnotes data-value="specific-value" data-text="Test footnote 2"></footnotes>sagittis eget, iaculis<footnotes data-value="" data-text="Test footnote 3"></footnotes>quis, molestie<footnotes data-value="another-value" data-text="Test footnote 4"></footnotes> <footnotes data-value="test" data-text="Test footnote 5"></footnotes>non, velit<footnotes data-value="" data-text="Test footnote 6"></footnotes>.</p>';
    $expected_updated_no_data_text = '<p>Aenean viverra rhoncus pede. Donec posuere vulputate arcu. <footnotes data-value="">Test footnote 1</footnotes>Fusce a quam. Aenean ut eros et nisl sagittis vestibulum. Nulla neque dolor, <footnotes data-value="specific-value">Test footnote 2</footnotes>sagittis eget, iaculis<footnotes data-value="">Test footnote 3</footnotes>quis, molestie<footnotes data-value="another-value">Test footnote 4</footnotes> <footnotes data-value="test">Test footnote 5</footnotes>non, velit<footnotes data-value="">Test footnote 6</footnotes>.</p>';

    // Create a node.
    $node = $this->drupalCreateNode([
      'title' => $this->randomString(),
      'type' => 'page',
      'body' => [
        'value' => $original,
        'format' => 'filtered_html',
      ],
    ]);

    // Fake the batch update.
    $context = [];
    FootnotesUpgradeBatchManager::processItem(
      'node',
      $node->id(),
      ['body'],
      ['use-data-text' => TRUE],
      $context
    );

    $updated_node = Node::load($node->id());
    $updated_value = $updated_node->get('body')->value;
    $updated_value = str_replace(["\n", "\r"], '', $updated_value);
    $updated_value = preg_replace('/\s+/', ' ', trim($updated_value));
    $this->assertSame($expected_updated_with_data_text, $updated_value);

    // Create a second node.
    $node2 = $this->drupalCreateNode([
      'title' => $this->randomString(),
      'type' => 'page',
      'body' => [
        'value' => $original,
        'format' => 'filtered_html',
      ],
    ]);

    // Fake the batch update, but with use-data-text as false.
    $context = [];
    FootnotesUpgradeBatchManager::processItem(
      'node',
      $node2->id(),
      ['body'],
      ['use-data-text' => FALSE],
      $context
    );

    $updated_node2 = Node::load($node2->id());
    $updated_value = $updated_node2->get('body')->value;
    $updated_value = str_replace(["\n", "\r"], '', $updated_value);
    $updated_value = preg_replace('/\s+/', ' ', trim($updated_value));
    $this->assertSame($expected_updated_no_data_text, $updated_value);
  }

}
