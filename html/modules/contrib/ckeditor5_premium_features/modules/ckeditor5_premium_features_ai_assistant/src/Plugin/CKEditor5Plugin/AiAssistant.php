<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_ai_assistant\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5_premium_features_ai_assistant\AITextAdapter;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 "AI Assistant" plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class AiAssistant extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface, CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * Creates the plugin instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param mixed ...$parent_arguments
   *   The parent plugin arguments.
   */
  public function __construct(
                              protected ConfigFactoryInterface $configFactory,
                              ...$parent_arguments
  ) {
    parent::__construct(...$parent_arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get('config.factory'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config = parent::getDynamicPluginConfig($static_plugin_config, $editor);
    $config = $this->configFactory->get('ckeditor5_premium_features_ai_assistant.settings');
    $removeCommands = $this->configuration['remove_commands'] ?? [];

    $textAdapter = $config->get('textAdapter') ?? AITextAdapter::OpenAI->value;
    $static_plugin_config['ai']['textAdapter'] = $textAdapter;

    if ($config->get('use_custom_endpoint') && $apiUrl = $config->get('api_url')) {
      $static_plugin_config['ai'][$textAdapter]['apiUrl'] = $apiUrl;
      if ($authKey = $config->get('auth_key')) {
        $static_plugin_config['ai'][$textAdapter]['requestHeaders']['Authorization'] = "Bearer: {$authKey}";
      }
    }
    else {
      $static_plugin_config['ai'][$textAdapter]['apiUrl'] = Url::fromRoute('ckeditor5_premium_features_ai_assistant.ai_assistant_proxy_provider')
        ->toString();
    }

    if ($textAdapter === AITextAdapter::AWS->value) {
      $providerName = $config->get('ai_provider');
      $model = $config->get("{$providerName}_model");
      $static_plugin_config['ai'][$textAdapter]['requestParameters'] = [
        'model' => $model,
        'stream' => FALSE,
      ];
    }

    if ($config->get('disable_default_styles')) {
      $static_plugin_config['ai']['useTheme'] = FALSE;
    }

    if (!empty($removeCommands)) {
      $static_plugin_config['ai']['aiAssistant']['removeCommands'] = $removeCommands;
    }
    $extraCommandsGroups = $this->getAvailableCommandsGroups($editor);
    if (!empty($extraCommandsGroups)) {
      $static_plugin_config['ai']['aiAssistant']['extraCommandGroups'] = [];
      foreach ($extraCommandsGroups as $group) {
        $static_plugin_config['ai']['aiAssistant']['extraCommandGroups'][] = [
          'groupId' => $group['id'],
          'groupLabel' => $group['label'],
          'commands' => $group['commands'],
        ];
      }
    }
    return $static_plugin_config;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'remove_commands' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['remove_commands'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Remove provided commands'),
      '#default_value' => implode("\n", $this->configuration['remove_commands']),
      '#description' => $this->t(
        'A list of command IDs to be removed from the "AI commands" plugin. Enter one or more ids in each line </br>
           You can find the list of default plugins <a href=":documentation_url">here</a>.',
        [':documentation_url' => 'https://ckeditor.com/docs/ckeditor5/latest/api/module_ai_aiassistant-AIAssistantConfig.html#member-commands']),
      '#ajax' => FALSE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $form_value = $form_state->getValue('remove_commands');
    $lines = explode("\n", $form_value);
    $val = array_map(fn($item) => rtrim($item), $lines);
    $form_state->setValue('remove_commands', $val);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['remove_commands'] = $form_state->getValue('remove_commands');
  }

  /**
   * Returns array of CKEditor5 AI Assistant Commands Group.
   *
   * @param \Drupal\editor\EditorInterface $editor
   *   Editor.
   *
   * @return array
   *   An Array of CKEditor5 AI Assistant Commands Group.
   */
  protected function getAvailableCommandsGroups(EditorInterface $editor): array {
    $format = $editor->getFilterFormat()->id();

    $entityStorage = \Drupal::service('entity_type.manager')
      ->getStorage('ckeditor5_ai_command_group');
    $query = $entityStorage->getQuery();
    $query->condition('status', TRUE);
    $query->condition('textFormats.*', $format, '=');
    $query->sort('weight');
    $results = $query->execute();

    $commandsGroups = $entityStorage->loadMultiple($results);
    $definitions = [];
    foreach ($commandsGroups as $commandGroup) {
      if (!empty($commandGroup->get('commands'))) {
        $definitions[] = $commandGroup->getDefinition();
      }
    }

    return $definitions;
  }

}
