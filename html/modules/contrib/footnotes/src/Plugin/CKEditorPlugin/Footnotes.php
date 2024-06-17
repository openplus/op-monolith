<?php

namespace Drupal\footnotes\Plugin\CKEditorPlugin;

use Drupal;
use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines the "Footnotes" plugin.
 *
 * @CKEditorPlugin(
 *   id = "footnotes",
 *   label = @Translation("FootnotesButton")
 * )
 */
class Footnotes extends CKEditorPluginBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getDependencies(Editor $editor) {
    return ['fakeobjects'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return Drupal::service('extension.list.module')->getPath('footnotes') . '/assets/js/ckeditor/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'footnotes' => [
        'label' => $this->t('Footnotes'),
        'image' => Drupal::service('extension.list.module')->getPath('footnotes') . '/assets/js/ckeditor/icons/footnotes.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [];
  }

}
