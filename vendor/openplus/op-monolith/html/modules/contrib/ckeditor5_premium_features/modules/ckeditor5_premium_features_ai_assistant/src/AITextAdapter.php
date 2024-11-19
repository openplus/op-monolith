<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types = 1);

namespace Drupal\ckeditor5_premium_features_ai_assistant;

/**
 * Enumeration of the types of AITextAdapter.
 */
enum AITextAdapter: string {

  case OpenAI = 'openAI';
  case AWS = 'aws';

}