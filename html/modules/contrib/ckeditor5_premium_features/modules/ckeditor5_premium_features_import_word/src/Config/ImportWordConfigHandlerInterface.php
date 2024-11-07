<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_import_word\Config;

/**
 * Interface for ImportWordConfigHandler.
 */
interface ImportWordConfigHandlerInterface {

  /**
   * Check if Word styles should be preserved on import.
   *
   * @return bool
   */
  public function isWordStylesEnabled(): bool;

}
