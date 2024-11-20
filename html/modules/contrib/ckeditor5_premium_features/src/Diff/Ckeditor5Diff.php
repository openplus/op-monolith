<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features\Diff;

use Drupal\ckeditor5_premium_features\Utility\ContextHelper;

/**
 * Ckeditor5 helper class for detecting document changes.
 */
class Ckeditor5Diff implements Ckeditor5DiffInterface {

  /**
   * String representing recently processed document with all changes marked.
   *
   * @var string
   */
  protected string $context;

  /**
   * Constructor.
   *
   * @param \Drupal\ckeditor5_premium_features\Utility\ContextHelper $contextHelper
   *   Context detecting helper service.
   */
  public function __construct(
    protected ContextHelper $contextHelper,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getDiff(string $oldDocument, string $newDocument): ?string {
    // It will prevent a wall of warnings about invalid html tags.
    $originalLibxmlErrorState = libxml_use_internal_errors(TRUE);

    $htmlDiff = new Ckeditor5HtmlDiff($oldDocument, $newDocument);
    $htmlDiff->getConfig()
      ->setPurifierEnabled(FALSE);

    $this->context = $htmlDiff->build();

    // Clear the buffer and set the original state of libxml errors.
    libxml_clear_errors();
    libxml_use_internal_errors($originalLibxmlErrorState);

    return $htmlDiff->getAddedContent();
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentChanges(string $oldDocument, string $newDocument): array {
    // It will prevent a wall of warnings about invalid html tags.
    $originalLibxmlErrorState = libxml_use_internal_errors(TRUE);

    $htmlDiff = new Ckeditor5HtmlDiff($oldDocument, $newDocument);
    $htmlDiff->getConfig()
      ->setPurifierEnabled(FALSE);

    $this->context = $htmlDiff->build();

    // Clear the buffer and set the original state of libxml errors.
    libxml_clear_errors();
    libxml_use_internal_errors($originalLibxmlErrorState);

    return $htmlDiff->getChanges();
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffAddedContext(): ?string {
    $highlights = $this->contextHelper->getDocumentChangesContext($this->context, TRUE);

    return implode('<div class="spacer">...</div>', $highlights);
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffContext(): ?string {
    $highlights = $this->contextHelper->getDocumentChangesContext($this->context);

    return implode('<div class="spacer">...</div>', $highlights);
  }

}
