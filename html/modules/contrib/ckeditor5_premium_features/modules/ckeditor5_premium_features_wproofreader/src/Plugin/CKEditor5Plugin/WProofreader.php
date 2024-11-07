<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_wproofreader\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5_premium_features_wproofreader\Form\SettingsForm;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * WProofreader ckeditor5 plugin class.
 */
class WProofreader extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  /**
   * WProofreader config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $wProofReaderConfig;
  /**
   * Host name.
   *
   * @var string
   */
  private string $host;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              ConfigFactoryInterface $configFactory,
                              RequestStack $request,
                              protected UrlGeneratorInterface $urlGenerator,
                              protected AccountProxyInterface $currentUser) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->wProofReaderConfig = $configFactory->get(SettingsForm::WPROOFREADER_SETTINGS_ID);
    $this->host = $request->getCurrentRequest()->getHost();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('request_stack'),
      $container->get('url_generator'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config = parent::getDynamicPluginConfig($static_plugin_config, $editor);
    $static_plugin_config['wproofreader']['lang'] = $this->wProofReaderConfig->get('lang_code') ?? 'auto';

    $userPermission = $this->currentUser->hasPermission('ckeditor5 webspellchecker proxy access');

    $static_plugin_config['wproofreader']['cke5']['validPermission'] = $userPermission;

    $serviceType = $this->wProofReaderConfig->get('service_type') ?? SettingsForm::WSC_DEFAULT_SERVICE_TYPE;
    if ($serviceType === SettingsForm::WSC_ON_PREMISE_SERVICE_TYPE) {
      $static_plugin_config['wproofreader']['serviceProtocol'] = $this->wProofReaderConfig->get('service_protocol') ?? '';
      $static_plugin_config['wproofreader']['serviceHost'] = $this->wProofReaderConfig->get('service_host') ?? '';
      $static_plugin_config['wproofreader']['servicePort'] = $this->wProofReaderConfig->get('service_port') ?? '';
      $static_plugin_config['wproofreader']['servicePath'] = $this->wProofReaderConfig->get('service_path') ?? '';
      $static_plugin_config['wproofreader']['srcUrl'] = $this->wProofReaderConfig->get('src_url') ?? '';
      return $static_plugin_config;
    }
    $static_plugin_config['wproofreader']['srcUrl'] = SettingsForm::DEFAULT_WSCBUNDLE_URL;
    $static_plugin_config['wproofreader']['serviceHost'] = $this->host;
    $static_plugin_config['wproofreader']['servicePath'] = $this->urlGenerator->generateFromRoute('ckeditor5_premium_features_wproofreader.webspellchecker_proxy');

    return $static_plugin_config;
  }

}
