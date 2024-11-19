<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features\Generator;

/**
 * Defines the interface for the token generators.
 */
interface TokenGeneratorInterface {

  /**
   * Generates the token.
   *
   * @return string
   *   The token.
   */
  public function generate(): string;

}