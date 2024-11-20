<?php

namespace Drupal\Tests\footnotes\Traits;

use Drupal\Core\Render\RendererInterface;
use Drupal\editor\Entity\Editor;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\node\Entity\NodeType;

/**
 * Helper trait used by multiple tests.
 */
trait FootnotesTestHelperTrait {

  /**
   * The FilterFormat config entity used for testing.
   *
   * @var \Drupal\filter\FilterFormatInterface
   */
  protected $filterFormat;

  /**
   * The account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Set up the text format and associate an editor.
   */
  protected function setUpFormatAndEditor(): void {

    // Create a text format and associate CKEditor.
    $this->filterFormat = FilterFormat::create([
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
      'filters' => [
        'filter_footnotes' => [
          'status' => TRUE,
          'settings' => [
            'footnotes_collapse' => FALSE,
            'footnotes_css' => TRUE,
            'footnotes_footer_disable' => FALSE,
          ],
        ],
      ],
    ]);
    $this->filterFormat->save();

    Editor::create([
      'format' => 'filtered_html',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => [
          'items' => [
            'source',
            'bold',
            'italic',
            'footnotes',
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Update the editor settings.
   *
   * @param array $settings
   *   The updated settings for the format.
   */
  protected function updateEditorSettings(array $settings) {
    $format = FilterFormat::load('filtered_html');
    $filters = $format->get('filters');
    $filters['filter_footnotes']['settings'] = $settings;
    $format->set('filters', $filters);
    $format->save();
  }

  /**
   * Set up the node type and field.
   */
  protected function setUpNodeTypeAndField(): void {

    // Create a node type for testing.
    NodeType::create([
      'type' => 'page',
      'name' => 'page',
    ])->save();

    // Create a body field instance for the 'page' node type.
    $field_storage = FieldStorageConfig::loadByName('node', 'body');
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'page',
      'label' => 'Body',
      'settings' => ['display_summary' => TRUE],
      'required' => TRUE,
    ])->save();

    // Create a second body field instance.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'body2',
      'type' => 'text_with_summary',
      'entity_type' => 'node',
      'cardinality' => 1,
      'settings' => [],
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_name' => 'body2',
      'field_storage' => $field_storage,
      'bundle' => 'page',
      'label' => 'Body2',
      'settings' => ['display_summary' => TRUE],
      'required' => TRUE,
    ])->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    // Create a form display for the default form mode.
    $display_repository->getFormDisplay('node', 'page')
      ->setComponent('body', [
        'type' => 'text_textarea_with_summary',
      ])
      ->setComponent('body2', [
        'type' => 'text_textarea_with_summary',
      ])
      ->save();

    // Create a display for the full view mode.
    $display_repository->getViewDisplay('node', 'page', 'full')
      ->setComponent('body', [
        'type' => 'text_default',
        'settings' => [],
      ])
      ->setComponent('body2', [
        'type' => 'text_default',
        'settings' => [],
      ])
      ->save();
  }

  /**
   * Set up default user with access to the text format.
   */
  protected function setUpDefaultUserWithAccess(): void {

    $this->account = $this->drupalCreateUser([
      'administer nodes',
      'administer blocks',
      'create page content',
      'use text format filtered_html',
      'use text format footnote',
    ]);
    $this->drupalLogin($this->account);
  }

  /**
   * Render the built footnote.
   *
   * @param array $build
   *   The footnote render array.
   *
   * @return string
   *   The rendered markup.
   */
  protected function renderFootnote(array $build): string {
    if (!$this->renderer instanceof RendererInterface) {
      $this->renderer = \Drupal::service('renderer');
    }
    return (string) $this->renderer->renderRoot($build);
  }

  /**
   * Build the footnote render array.
   *
   * @param string $html
   *   The html for the reference content.
   * @param string $value
   *   The citation value.
   *
   * @return array
   *   The footnote render array.
   */
  protected function buildFootnote(string $html, string $value = ''): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'footnotes',
      '#value' => '',
      '#attributes' => [
        'data-text' => [$html],
        'data-value' => [$value],
      ],
    ];
  }

}
