<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Generator;

use Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface;
use Drupal\ckeditor5_premium_features\Utility\UserHelper;
use Drupal\Core\Session\AccountProxyInterface;
use Firebase\JWT\JWT;

/**
 * Provides the JWT Token generator service.
 */
class TokenGenerator implements TokenGeneratorInterface {

  public const ALGORITHM = 'HS512';

  /**
   * Constructs the token generator instance.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   * @param \Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface $settingsConfigHandler
   *   The settings config handler.
   * @param \Drupal\ckeditor5_premium_features\Utility\UserHelper $userHelper
   *   Helper for getting user data.
   *
   * @note The account will be used later in collaboration features.
   */
  public function __construct(
    protected AccountProxyInterface $account,
    protected SettingsConfigHandlerInterface $settingsConfigHandler,
    protected UserHelper $userHelper,
  ) {
  }

  /**
   * Generates the JWT token.
   *
   * @return string
   *   The token.
   */
  public function generate(): string {
    $payload = [
      'aud' => $this->settingsConfigHandler->getEnvironmentId(),
      'iat' => time(),
      'sub' => $this->userHelper->getUserUuid($this->account) ?? $this->userHelper->generateSiteUserId($this->account),
      'auth' => [
        'collaboration' => [
          '*' => [
            'role' => 'writer',
          ],
        ],
      ],
    ];
    $userData = $this->userHelper->getUserData($this->account);
    $payload['user'] = $userData;

    return JWT::encode($payload, $this->settingsConfigHandler->getAccessKey(), static::ALGORITHM);
  }

}
