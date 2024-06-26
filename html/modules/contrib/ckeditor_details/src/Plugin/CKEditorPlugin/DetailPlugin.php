<?php

namespace Drupal\ckeditor_details\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginInterface;
use Drupal\ckeditor\CKEditorPluginButtonsInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "detail" plugin.
 *
 * @CKEditorPlugin(
 *   id = "detail",
 *   label = @Translation("Accordion")
 * )
 */
class DetailPlugin extends PluginBase implements CKEditorPluginInterface, CKEditorPluginButtonsInterface {

  /**
   * Implements \Drupal\ckeditor\Plugin\CKEditorPluginInterface::getDependencies().
   */
  public function getDependencies(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return ['core/drupal.jquery'];
  }

  /**
   * Implements \Drupal\ckeditor\Plugin\CKEditorPluginInterface::isInternal().
   */
  public function isInternal() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    // @todo switch to dependency injection.
    return [
      'detail' => [
        'label' => t('Add accordion'),
        'image' => \Drupal::service('extension.list.module')->getPath('ckeditor_details') . '/js/plugins/detail/icons/detail.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    // @todo switch to dependency injection.
    return \Drupal::service('extension.list.module')->getPath('ckeditor_details') . '/js/plugins/detail/plugin.js';
  }

}
