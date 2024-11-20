<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_export_word\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5_premium_features\Plugin\CKEditor5Plugin\ExportBase;
use Drupal\ckeditor5_premium_features_export_word\Form\SettingsForm;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 "Export to Word" plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class ExportWord extends ExportBase {

  const EXPORT_PDF_PLUGIN_ID = 'exportWord';

  const EXPORT_FILE_EXTENSION = '.docx';

  const CONFIGURATION_ID = 'ckeditor5_premium_features_export_word.settings';

  const EXPORT_SETTING_FORM = SettingsForm::class;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get('config.factory'),
      $container->get('ckeditor5_premium_features_export_word.config_handler.export_settings'),
      $container->get('ckeditor5_premium_features.file_name_generator'),
      $container->get('ckeditor5_premium_features.css_style_provider'),
      $container->get('file_system'),
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'converter_url' => NULL,
      'converter_options' => [
        'format' => NULL,
        'margin_top' => [
          'value' => NULL,
          'units' => NULL,
        ],
        'margin_bottom' => [
          'value' => NULL,
          'units' => NULL,
        ],
        'margin_left' => [
          'value' => NULL,
          'units' => NULL,
        ],
        'margin_right' => [
          'value' => NULL,
          'units' => NULL,
        ],
        'custom_css' => NULL,
        'header' => [
          [
            'html' => NULL,
            'css' => NULL,
            'type' => NULL,
          ],
        ],
        'footer' => [
          [
            'html' => NULL,
            'css' => NULL,
            'type' => NULL,
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config = parent::getDynamicPluginConfig($static_plugin_config, $editor);

    $options = &$static_plugin_config[$this->getFeaturedPluginId()]['converterOptions'];

    // Word converter requires a different name than the PDF converter.
    if (isset($options['page_orientation'])) {
      $options['orientation'] = $options['page_orientation'];
    }

    foreach (['footer', 'header'] as $item) {
      if (isset($options[$item]) && is_array($options[$item])) {
        $this->cleanUpEmptyHtmlElements($options[$item]);
      }
    }

    return $static_plugin_config;
  }

  /**
   * Removes items that have the empty HTML content.
   *
   * @param array $element
   *   The element to be processed.
   */
  private function cleanUpEmptyHtmlElements(array &$element): void {
    foreach ($element as $key => $item) {
      if (empty($item['html'])) {
        unset($element[$key]);
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getSettingsForm(): string {
    return self::EXPORT_SETTING_FORM;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigId(): string {
    return self::CONFIGURATION_ID;
  }

  /**
   * {@inheritDoc}
   */
  public function getFeaturedPluginId(): string {
    return self::EXPORT_PDF_PLUGIN_ID;
  }

  /**
   * {@inheritDoc}
   */
  public function getExportFileExtension(): string {
    return self::EXPORT_FILE_EXTENSION;
  }

}
