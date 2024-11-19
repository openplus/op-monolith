<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 Track changes & comments plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class CollaborationBase extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  /**
   * Creates the Track Changes plugin instance.
   *
   * @param \Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface $settingsConfigHandler
   *   The settings configuration handler.
   * @param mixed ...$parent_arguments
   *   The parent plugin arguments.
   */
  public function __construct(
    protected SettingsConfigHandlerInterface $settingsConfigHandler,
    ...$parent_arguments
  ) {
    parent::__construct(...$parent_arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$parent_arguments): static {
    return new static(
      $container->get('ckeditor5_premium_features.config_handler.settings'),
      ...$parent_arguments
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    if ($licenseKey = $this->settingsConfigHandler->getLicenseKey()) {
      $static_plugin_config['licenseKey'] = $licenseKey;
    }

    $settings = $editor->getSettings();
    if (!isset($settings['plugins']['media_media'])) {
      $static_plugin_config['removePlugins'] = ['DrupalMediaTrackChangesIntegration'];
    }

    return $static_plugin_config;
  }

  /**
   * Gets the list of all toolbars related to the collaboration features.
   *
   * @return string[]
   *   The toolbar names.
   */
  public static function getToolbars(): array {
    return [
      'trackChanges',
      'comment',
      'revisionHistory',
    ];
  }

}
