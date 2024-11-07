<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Utility;

use Drupal\Component\Utility\NestedArray;

/**
 * Provides the library definition item.
 */
class LibraryDefinitionItem {

  /**
   * Constructs the library instance.
   *
   * @param string $id
   *   The id of the library (will be prefixed with the module name).
   * @param string $baseDirectory
   *   The base directory to use when registering the files.
   * @param array $jsData
   *   The JS data to be passed to the definition.
   * @param array $cssData
   *   The CSS data to be passed to the definition.
   * @param array $dependencies
   *   The dependencies to be added to the definition.
   */
  public function __construct(
    protected string $id,
    protected string $baseDirectory = '',
    protected array $jsData = [],
    protected array $cssData = [],
    protected array $dependencies = [],
  ) {
  }

  /**
   * The library ID.
   *
   * @return string
   *   The ID.
   */
  public function id(): string {
    return $this->id;
  }

  /**
   * Adds the remote JS to the library.
   *
   * @param string $name
   *   The name of the library file without extension.
   */
  public function addRemoteJs(string $name): void {
    $file_name = "{$this->baseDirectory}{$name}/{$name}.js";

    $this->jsData[$file_name] = [
      'type' => 'external',
      'minified' => 'true',
      'preprocess' => FALSE,
      'attributes' => [
        'crossorigin' => 'anonymous'
      ]
    ];
  }

  /**
   * Adds the dependency to the list of dependencies.
   *
   * @param string $name
   *   The dependency name.
   */
  public function addDependency(string $name): void {
    if (in_array($name, $this->dependencies, TRUE)) {
      return;
    }

    $this->dependencies[] = $name;
  }

  /**
   * Gets the full library definition data.
   *
   * @return array
   *   The definition.
   */
  public function getDefinition(): array {
    $definition = [
      'js' => $this->jsData,
      'css' => $this->cssData,
      'dependencies' => $this->dependencies,
    ];

    return NestedArray::mergeDeepArray([
      $this->getBaseDefinition(), array_filter($definition),
    ], TRUE);
  }

  /**
   * Gets base library definition.
   *
   * Provides some commonly used keys as remote,
   * license, and base dependencies.
   *
   * @return array
   *   The definition.
   */
  public function getBaseDefinition() {
    return [
      'remote' => 'https://ckeditor.com/',
      'license' => [],
      'dependencies' => [
        'ckeditor5/ckeditor5',
      ],
    ];
  }

}
