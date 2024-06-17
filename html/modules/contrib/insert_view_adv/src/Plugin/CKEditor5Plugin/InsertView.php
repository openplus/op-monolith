<?php

namespace Drupal\insert_view_adv\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 Media Library plugin.
 *
 * Provides media library support and options for the CKEditor 5 build.
 *
 * @internal
 *   Plugin classes are internal.
 */
class InsertView extends CKEditor5PluginDefault {

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $dynamic_plugin_config = $static_plugin_config;
    $dynamic_plugin_config['insertViewAdv']['libraryURL'] = Url::fromRoute('insert_view_adv.editor_dialog')
      ->setRouteParameter('filter_format', $editor->getFilterFormat()->id())
      ->toString(TRUE)
      ->getGeneratedUrl();;
    $dynamic_plugin_config['insertViewAdv']['previewURL'] = Url::fromRoute('insert_view_adv.editor_preview')
      ->setRouteParameter('filter_format', $editor->getFilterFormat()->id())
      ->toString(TRUE)
      ->getGeneratedUrl();
    return $dynamic_plugin_config;
  }

}
