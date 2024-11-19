<?php

declare(strict_types=1);

namespace Drupal\footnotes\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;

/**
 * Plugin class to add dialog url for embedded content.
 */
class Footnotes extends CKEditor5PluginDefault {

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    // Set dialogue URL.
    $embedded_content_dialog_url = Url::fromRoute('footnotes.dialog', [
      'editor' => $editor->id(),
    ])->toString(TRUE)->getGeneratedUrl();
    $static_plugin_config['footnotes']['dialogURL'] = $embedded_content_dialog_url;

    // Set preview URL.
    $embedded_content_preview_url = Url::fromRoute('footnotes.preview', [
      'editor' => $editor->id(),
    ])->toString(TRUE)->getGeneratedUrl();
    $static_plugin_config['footnotes']['previewURL'] = $embedded_content_preview_url;
    return $static_plugin_config;
  }

}
