<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Generator;

use Drupal\ckeditor5_premium_features\Utility\Html;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides file name generator based on current node alias.
 */
class FileNameGenerator implements FileNameGeneratorInterface {

  public const DEFAULT_FILENAME = 'filename';

  /**
   * Constructs a new BookNavigationCacheContext service.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(
    protected RouteMatchInterface $routeMatch
  ) {
  }

  /**
   * Generate file name based entity alias.
   */
  public function generateFromRequest(): string {
    $route_name = $this->routeMatch->getRouteName();
    $route_param = explode('.', $route_name);
    $entity = $this?->routeMatch->getParameter($route_param[1]);
    try {
      if ($entity) {
        $alias = $entity->toUrl()->toString();
        return $this->convertUrlToFileName($alias);
      }
    }
    catch (\Exception $e) {
    }

    return self::DEFAULT_FILENAME;
  }

  /**
   * Add extension to filename.
   *
   * @param string $filename
   *   Filename.
   * @param string $extension
   *   Extension file.
   */
  public function addExtensionFile(string &$filename, string $extension): void {
    $extension = str_starts_with($extension, '.') ? $extension : '.' . $extension;
    $filename .= $extension;
  }

  /**
   * Cleanup and convert alias to friendly filename.
   *
   * @param string $alias
   *   Entity alias/url.
   *
   * @return string
   *   Converted filename.
   */
  public function convertUrlToFileName(string $alias): string {
    $alias = ltrim($alias, '/');

    return Html::cleanCssIdentifier($alias);
  }

}
