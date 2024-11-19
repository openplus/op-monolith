<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Plugin\Filter;

use Drupal\ckeditor5_premium_features\Utility\Html;
use Drupal\ckeditor5_premium_features\Utility\HtmlHelper;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter to cleanup the collaboration features markup data.
 *
 * Simply removes the markup and and it's content for the not yet approved
 * changes and comments.
 *
 * @Filter(
 *   id = "ckeditor5_premium_features_collaboration_filter",
 *   title = @Translation("Removes the collaboration (suggestions, comments) data from the markup so that the content displayed to your end users does not contain comments/suggestions for content editors."),
 *   description = @Translation("This filter should be executed as soon as possible. If you encounter missing whitespaces near words that contain suggestions please move it up in the filter processing order."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 *   weight = -100
 * )
 */
class FilterCollaboration extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The HTML helper.
   *
   * @var \Drupal\ckeditor5_premium_features\Utility\HtmlHelper
   */
  protected $htmlHelper;

  /**
   * Constructs a new FilterCollaboration.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Definition.
   * @param \Drupal\ckeditor5_premium_features\Utility\HtmlHelper $html_helper
   *   HTML helper.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, HtmlHelper $html_helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->htmlHelper = $html_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ckeditor5_premium_features.html_helper')
    );
  }

  /**
   * Get HTML helper utility service.
   *
   * @return \Drupal\ckeditor5_premium_features\Utility\HtmlHelper
   */
  public function getHtmlHelper(): HtmlHelper {
    return $this->htmlHelper;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    $this->filterComments($xpath);
    $this->htmlHelper->convertSuggestionsAttributes($dom, $xpath);

    $dom->saveHTML();
    $text = Html::serialize($dom);

    $this->filterSuggestionsTags($text);

    return new FilterProcessResult($text);
  }

  /**
   * Filter out the suggestion styling tags from the document.
   *
   * @param \DOMXPath $xpath
   *   The DOM XPath.
   * @param \DOMDocument $dom
   *   The DOM Document.
   */
  public function filterStyleSuggestion(\DOMXPath $xpath, \DOMDocument $dom): void {
    $query = '//suggestion-start[contains(@name, "attribute:")]/following-sibling::node()[following-sibling::suggestion-end[contains(@name, "attribute:")]]';
    $suggestions = $xpath->query($query);
    foreach ($suggestions as $suggestion) {
      if ($suggestion instanceof \DOMElement) {
        $textNode = $dom->createTextNode($suggestion->textContent);
        $suggestion->parentNode->replaceChild($textNode, $suggestion);
      }
    }
  }

  /**
   * Filter out the comment tags and attributes.
   *
   * @param \DOMXPath $xpath
   *   The DOM XPath.
   */
  public function filterComments(\DOMXPath $xpath): void {
    $comment_tags = [
      'comment-start',
      'comment-end',
    ];

    foreach ($comment_tags as $comment_tag) {
      $comments = $xpath->query('//' . $comment_tag);

      if (!$comments) {
        continue;
      }

      /** @var \DOMElement $comment */
      foreach ($comments as $comment) {
        $comment->remove();
      }
    }

    $comments_attributes = [
      'data-comment-start-before',
      'data-comment-end-after',
    ];

    foreach ($comments_attributes as $attribute) {
      $elements = $xpath->query("//*[@$attribute]");
      if (!$elements) {
        continue;
      }

      /** @var \DOMElement $element */
      foreach ($elements as $element) {
        $element->removeAttribute($attribute);
      }
    }
  }

  /**
   * Filter out the suggestion tags.
   *
   * @param \DOMXPath $xpath
   *   The DOM XPath.
   */
  public function filterSuggestionsTags(string &$text): void {
    $text = preg_replace('#<suggestion-start[^<>]*insertion[^<>]*></suggestion-start>#si', '<ins>', $text);
    $text = preg_replace('#<suggestion-end[^<>]*insertion[^<>]*></suggestion-end>#si', '</ins>', $text);
    $text = preg_replace('%(<ins.*?>)(.*?)(<\/ins.*?>)%is', '', $text);

    $text = preg_replace('#<suggestion-start[^<>]*></suggestion-start>#si', '', $text);
    $text = preg_replace('#<suggestion-end[^<>]*></suggestion-end>#si', '', $text);
  }

}
