<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types = 1);

namespace Drupal\ckeditor5_premium_features_ai_assistant\Plugin\CKEditor5AiProvider;

use Drupal\ckeditor5_premium_features_ai_assistant\AITextAdapter;
use Drupal\ckeditor5_premium_features_ai_assistant\CKEditor5AiProviderPluginBase;
use Drupal\ckeditor5_premium_features_ai_assistant\Form\SettingsForm;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use OpenAI\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plugin implementation of the ckeditor5_ai_provider.
 *
 * @CKEditor5AiProvider(
 *   id = "azureai_service",
 *   label = @Translation("AzureAI Service"),
 *   description = @Translation("AzureAI Service Provider."),
 *   form_fields = {
 *     "resource_name" = {
 *        "#type" = "textfield",
 *        "#title" = "Resource name",
 *        "#required" = TRUE
 *      },
 *     "deployment_name" = {
 *        "#type" = "textfield",
 *        "#title" = "Deployment name",
 *        "#required" = TRUE
 *      },
 *     "api_key" = {
 *        "#type" = "textfield",
 *        "#title" = "API key",
 *        "#required" = TRUE
 *      },
 *     "api_version" = {
 *        "#type" = "textfield",
 *        "#title" = "API version",
 *        "#required" = TRUE
 *      }
 *   }
 * )
 */
final class AzureAi extends CKEditor5AiProviderPluginBase {

  use StringTranslationTrait;

  /**
   * Config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $config;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $configFactory->get(SettingsForm::AI_ASSISTANT_SETTINGS_ID);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processRequest(Request $request): Response {
    if (!$this->getApiKey() || !$this->getDeploymentName() || !$this->getApiVersion() || !$this->getResourceName()) {
      return new Response('Missing AI service configuration.', 503);
    }
    $content = $request->getContent();
    $requestData = json_decode($content, TRUE);

    $client = $this->getClient();

    $stream = $client->chat()->createStreamed($requestData);
    return new StreamedResponse(function () use ($stream) {
      foreach ($stream as $data) {
        echo json_encode($data->toArray()), PHP_EOL;
        ob_flush();
        flush();
      }
    }, 200, [
      'Cache-Control' => 'no-cache, must-revalidate',
      'Content-Type' => 'text/event-stream',
      'X-Accel-Buffering' => 'no',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigFields(): array {
    $definition = $this->getPluginDefinition();
    return $definition['form_fields'];
  }

  /**
   * Returns OpenAI client.
   *
   * @return \OpenAI\Client
   *   Client object.
   */
  private function getClient(): Client {
    return \OpenAI::factory()
      ->withBaseUri($this->getApiEndpoint())
      ->withHttpHeader('api-key', $this->getApiKey())
      ->withQueryParam('api-version', $this->getApiVersion())
      ->make();
  }

  /**
   * {@inheritdoc}
   */
  public function getTextAdapter(): AITextAdapter {
    return AITextAdapter::OpenAI;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string|TranslatableMarkup {
    return $this->t('Check the <a href=@doc_url target="_blank">integration documentation</a> for detail information.',
    ['@doc_url' => 'https://ckeditor.com/docs/ckeditor5/latest/features/ai-assistant/ai-assistant-integration.html#set-up-the-service']);
  }

  /**
   * Returns API key for the client.
   *
   * @return string
   *   The API key.
   */
  private function getApiKey(): string {
    return $this->config->get($this->getPluginId() . '_' . 'api_key') ?? '';
  }

  /**
   * Returns API version.
   *
   * @return string
   *   The API version.
   */
  private function getApiVersion(): string {
    return $this->config->get($this->getPluginId() . '_' . 'api_version') ?? '';
  }

  /**
   * Returns API resource name.
   *
   * @return string
   *   The API resource name.
   */
  private function getResourceName(): string {
    return $this->config->get($this->getPluginId() . '_' . 'resource_name') ?? '';
  }

  /**
   * Returns API resource name.
   *
   * @return string
   *   The deployment name.
   */
  private function getDeploymentName(): string {
    return $this->config->get($this->getPluginId() . '_' . 'deployment_name') ?? '';
  }

  /**
   * Returns api endpoint.
   *
   * @return string
   *   The API endpoint.
   */
  private function getApiEndpoint(): string {
    return "https://{$this->getResourceName()}.openai.azure.com/openai/deployments/{$this->getDeploymentName()}";
  }

}