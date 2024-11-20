<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Utility;

use Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigException;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Error;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the CKEditor API connection.
 */
class ApiAdapter {

  use LoggerChannelTrait;
  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * Creates the Track Changes plugin instance.
   *
   * @param \Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface $settingsConfigHandler
   *   The settings configuration handler.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(protected SettingsConfigHandlerInterface $settingsConfigHandler,
                              protected ClientInterface $http_client,
                              protected AccountProxyInterface $account) {
  }

  /**
   * Call flush all collaborative sessions endpoint.
   */
  public function flushAllCollaborativeSessions(): void {
    $this->sendRequest('DELETE', 'collaborations');
  }

  /**
   * Call to get document details of collaborative session.
   *
   * @param string $documentId
   *   The document id.
   *
   * @return array
   *   Response of the request.
   */
  public function getCollaborativeSessionDetails(string $documentId): array {
    return $this->sendRequest('GET', 'collaborations/' . $documentId . '/details');
  }

  /**
   * Gets document suggestions.
   *
   * @param string $documentId
   *   The document id.
   * @param array $parameters
   *   Optional parameters.
   *
   * @return array
   *   Array of suggestions.
   */
  public function getDocumentSuggestions(string $documentId, array $parameters = []): array {
    $path = 'suggestions?document_id=' . $documentId;
    foreach ($parameters as $key => $parameter) {
      $path .= '&' . $key . '=' . $parameter;
    }
    $response = $this->sendRequest('GET', $path);
    return $response['data'] ?? [];
  }

  /**
   * Gets document suggestions.
   *
   * @param string $suggestionId
   *   The suggestion id.
   * @param string $documentId
   *   The document id.
   * @param array $parameters
   *   Optional request parameters.
   *
   * @return array
   *   Array of suggestions.
   */
  public function getSingleSuggestion(string $suggestionId, string $documentId, array $parameters = []): array {
    $path = 'suggestions/' . $suggestionId . '?document_id=' . $documentId;
    foreach ($parameters as $key => $parameter) {
      $path .= '&' . $key . '=' . $parameter;
    }
    $response = $this->sendRequest('GET', $path);
    return $response ?? [];
  }

  /**
   * Gets document comments.
   *
   * @param string $documentId
   *   The document id.
   * @param array $parameters
   *   Optional request parameters.
   *
   * @return array
   *   Array of comments.
   */
  public function getDocumentComments(string $documentId, array $parameters = []): array {
    $path = 'comments?document_id=' . $documentId;
    foreach ($parameters as $key => $parameter) {
      $path .= '&' . $key . '=' . $parameter;
    }
    $response = $this->sendRequest('GET', $path);
    return $response['data'] ?? [];
  }

  /**
   * Get single comment.
   *
   * @param string $commentId
   *   The comment id.
   * @param string $documentId
   *   The document id.
   * @param array $parameters
   *   Optional request parameters.
   *
   * @return array
   *   Array of comments.
   */
  public function getSingleComment(string $commentId, string $documentId, array $parameters = []): array {
    $path = 'comments/' . $commentId . '?document_id=' . $documentId;
    foreach ($parameters as $key => $parameter) {
      $path .= '&' . $key . '=' . $parameter;
    }
    $response = $this->sendRequest('GET', $path);
    return $response ?? [];
  }

  /**
   * Check the library version used in last session.
   *
   * @param string $documentId
   *   The document id.
   *
   * @return string|null
   *   Library version
   */
  public function getLibraryVersion(string $documentId): ?string {
    $details = $this->getCollaborativeSessionDetails($documentId);
    if (!empty($details['current_session'])) {
      return $details['current_session']['bundle_version'];
    }
    return NULL;
  }

  /**
   * Validate session library version with used in Drupal.
   *
   * @param string $documentId
   *   The document id.
   */
  public function validateLibraryVersion(string $documentId): void {
    $sessionVersion = $this->getLibraryVersion($documentId);
    $libraryVersion = $this->settingsConfigHandler->getDllVersion();
    if (is_null($sessionVersion) || $sessionVersion === $libraryVersion) {
      return;
    }
    else {
      $this->flushAllCollaborativeSessions();
    }
  }

  /**
   * Base URL of API.
   *
   * @return string
   *   Base URL.
   */
  private function getBaseUrl(): String {
    return $this->settingsConfigHandler->getApiUrl();
  }

  /**
   * Generate signature for request.
   *
   * @param string $method
   *   Request method.
   * @param string $url
   *   Request url.
   * @param int $timestamp
   *   Timestamp.
   * @param array $body
   *   Request body.
   *
   * @return string
   *   Generated signature.
   */
  private function generateSignature(string $method, string $url, int $timestamp, array $body): String {
    $parsedUrl = parse_url($url);
    $uri = $parsedUrl['path'] ?? '';

    if (isset($parsedUrl['query'])) {
      $uri .= '?' . $parsedUrl['query'];
    }

    $data = $method . $uri . $timestamp;

    if ($body) {
      $data .= JSON::encode($body);
    }
    $key = $this->settingsConfigHandler->getApiKey();

    if (!$key) {
      throw new ConfigException('Missing API Key');
    }
    return hash_hmac('sha256', $data, $key);
  }

  /**
   * Send request to API.
   *
   * @param string $method
   *   Request method.
   * @param string $path
   *   Request path.
   *
   * @return array
   *   Result of sent request.
   */
  private function sendRequest(string $method, string $path): array {
    $url = $this->getBaseUrl() . $path;
    $timestamp = hrtime(TRUE);
    try {
      $signature = $this->generateSignature($method, $url, $timestamp, []);
    }
    catch (ConfigException $e) {
      if ($this->account->hasPermission('use ckeditor5 access token')) {
        Error::logException($this->getLogger('ckeditor5_premium_features'), $e, $e->getMessage());
      }
      return [];
    }

    $options = [
      'headers' => [
        'X-CS-Signature' => $signature,
        'X-CS-Timestamp' => $timestamp,
      ],
    ];

    try {
      $request = $this->http_client->request($method, $url, $options);
    }
    catch (GuzzleException $e) {
      // Log the error.
      Error::logException($this->getLogger('ckeditor5_premium_features'), $e, $e->getMessage());
      return [];
    }

    $response = $request->getBody()->getContents();

    return empty($response) ? [] : (array) Json::decode($response);
  }

}
