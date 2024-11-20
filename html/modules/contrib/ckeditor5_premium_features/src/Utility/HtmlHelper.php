<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Utility;

/**
 * Class suited for helping handling collaboration HTML.
 */
class HtmlHelper {

  /**
   * Checks element parents and returns one that is suitable for a context.
   *
   * @param \DOMElement $element
   *   Element to search the best parent node.
   *
   * @return \DOMNode
   *   Returns element parent node or element itself if no parent node found.
   */
  public function selectElementParentNode(\DOMElement $element): \DOMNode {
    $acceptingParentNodeTypes = array_flip([
      'div',
      'p',
      'table',
    ]);
    $overParentNodesTypes = array_flip([
      'blockquote',
    ]);
    $topLevelTags = array_flip([
      'body',
      'html',
    ]);

    $parentNode = $element->parentNode;
    while ($parentNode) {
      $grandParentNode = $parentNode->parentNode;
      if (isset($overParentNodesTypes[$grandParentNode->nodeName])) {
        $parentNode = $grandParentNode;
        break;
      }
      if (isset($acceptingParentNodeTypes[$parentNode->nodeName]) || $grandParentNode == NULL) {
        break;
      }
      if (isset($topLevelTags[$grandParentNode->nodeName])) {
        break;
      }
      $value = $parentNode->nodeValue;
      if (mb_strlen(strip_tags($value)) > 255) {
        break;
      }
      $parentNode = $grandParentNode;
    }

    if (!$parentNode || ($parentNode && isset($topLevelTags[$parentNode->nodeName]))) {
      return $element;
    }

    return $parentNode;
  }

  /**
   * Removes collaboration entities not matching passed selector.
   *
   * @param \DOMDocument $document
   *   Document to be processed.
   * @param string $elementType
   *   Type of elements to search for.
   * @param string $selector
   *   Selector used for filtering not matching elements.
   */
  public function removeNotRequiredCollaborationElements(\DOMDocument $document, string $elementType, string $selector): void {
    $removeQueries = [];
    $postfix = [
      'start',
      'end',
    ];

    foreach ($postfix as $type) {
      $removeQueries[] = "//$elementType-$type" . "[not($selector)]";
    }

    $this->doRemoveElements($document, $removeQueries);
  }

  /**
   * Create suggestion markers in the document.
   *
   * Markers are created for not matching selectors.
   *
   * @param \DOMDocument $document
   *   Document to be processed.
   * @param string $selector
   *   Selector used for filtering elements.
   */
  public function createSuggestionsMarkers(\DOMDocument $document, string $selector): void {
    $startQueriesInsertion[] = "//suggestion-start" . "[(contains(@name, 'insertion')) and not($selector)]";
    $startQueriesDeletion[] = "//suggestion-start" . "[(contains(@name, 'deletion')) and not($selector)]";
    $startQueriesFormat[] = "//suggestion-start" . "[(contains(@name, 'attribute:')) and not($selector)]";
    $endQueries[] = "//suggestion-end [not($selector)]";

    $this->doReplaceElements($document, $startQueriesInsertion, 'suggestion-marker-start-insertion');
    $this->doReplaceElements($document, $startQueriesDeletion, 'suggestion-marker-start-deletion');
    $this->doReplaceElements($document, $startQueriesFormat, 'suggestion-marker-start-format');
    $this->doReplaceElements($document, $endQueries, 'suggestion-marker-end');
  }

  /**
   * Replace collaboration entities matching passed queries.
   *
   * @param \DOMDocument $document
   *   Document to be processed.
   * @param array $replaceQueries
   *   Array of queries defining entities to replace.
   * @param string $elementName
   *   Name of the element to be created.
   */
  private function doReplaceElements(\DOMDocument $document, array $replaceQueries, string $elementName): void {
    $xpath = new \DOMXPath($document);

    foreach ($replaceQueries as $queryR) {
      $elementsToReplace = $xpath->query($queryR);
      /** @var \DOMElement $elementToReplace */
      foreach ($elementsToReplace as $elementToReplace) {
        $nodeDiv = $document->createElement($elementName, $elementToReplace->nodeValue);
        $elementToReplace->parentNode->replaceChild($nodeDiv, $elementToReplace);
      }
    }
  }

  /**
   * Removes collaboration entities having data-suggestion- prefixed attributes
   * not matching passed selector.
   *
   * @param \DOMDocument $document
   *   Document to be processed.
   * @param string $selector
   *   Selector used for filtering not matching elements.
   */
  public function removeNotRequiredCollaborationElementsWithSuggestionAttributes(\DOMDocument $document, string $selector): void {
    $removeQueries = [];
    $attributes = [
      'data-suggestion-start-before',
      'data-suggestion-end-after',
    ];

    foreach ($attributes as $attribute) {
      $removeQueries[] = "//*[@$attribute][not($selector)]";
    }

    $this->doRemoveElements($document, $removeQueries);
  }

  /**
   * Removes collaboration entities matching passed queries.
   *
   * @param \DOMDocument $document
   *   Document to be processed.
   * @param array $removeQueries
   *   Array of queries defining entities ro remove.
   */
  private function doRemoveElements(\DOMDocument $document, array $removeQueries): void {
    $xpath = new \DOMXPath($document);

    foreach ($removeQueries as $queryR) {
      $commentsToRemove = $xpath->query($queryR);
      /** @var \DOMElement $elementToRemove */
      foreach ($commentsToRemove as $elementToRemove) {
        $elementToRemove->parentNode->removeChild($elementToRemove);
      }
    }
  }

  /**
   * Converts collaboration tags to HTML tags wrapping collaboration content.
   *
   * @param \DOMDocument $document
   *   Document to be processed.
   * @param string $elementType
   *   Type of elements to search for.
   * @param string $selector
   *   Selector used for filtering matching elements.
   */
  public function convertCollaborationTagsWrappings(\DOMDocument $document, string $elementType, string $selector): void {
    $queryStart = "//$elementType-start[$selector]";
    $queryEnd = "//$elementType-end[$selector]";

    $xpath = new \DOMXPath($document);

    $startingElement = $xpath->query($queryStart)->item(0);
    $endingElement = $xpath->query($queryEnd)->item(0);

    if (!isset($startingElement->parentNode) || !isset($endingElement->parentNode) ||
      $startingElement->parentNode->getNodePath() === $endingElement->parentNode->getNodePath()) {
      return;
    }

    $startingElementPath = explode('/', $startingElement->getNodePath());
    $endingElementPath = explode('/', $endingElement->getNodePath());
    $intersectedPart = [];
    foreach ($startingElementPath as $pathPos => $pathItem) {
      if (!isset($endingElementPath[$pathPos]) || $endingElementPath[$pathPos] != $pathItem) {
        break;
      }
      $intersectedPart[] = $endingElementPath[$pathPos];
    }
    $commonParentPath = implode('/', $intersectedPart);

    while ($startingElement->parentNode && $startingElement->parentNode->getNodePath() !== $commonParentPath) {
      $startingElement->parentNode->appendChild($endingElement->cloneNode(TRUE));

      if ($startingElement->parentNode->nextSibling) {
        $startingElement->parentNode->parentNode->insertBefore(
          $startingElement->cloneNode(TRUE),
          $startingElement->parentNode->nextSibling
        );
      }
      elseif ($startingElement->parentNode->parentNode) {
        $startingElement->parentNode->parentNode->appendChild($startingElement->cloneNode(TRUE));
      }

      if ($startingElement->parentNode->parentNode == NULL || $startingElement->parentNode->parentNode->getNodePath() == $commonParentPath) {
        break;
      }
      $startingElement = $startingElement->parentNode;
    }

    while ($endingElement->parentNode && $endingElement->parentNode->getNodePath() !== $commonParentPath) {
      $endingElement->parentNode->insertBefore(
        $startingElement->cloneNode(TRUE),
        $endingElement->parentNode->firstChild
      );

      $endingElement->parentNode->parentNode->insertBefore($endingElement->cloneNode(TRUE), $endingElement->parentNode);

      if ($endingElement->parentNode->parentNode == NULL || $endingElement->parentNode->parentNode->getNodePath() == $commonParentPath) {
        break;
      }
      $endingElement = $endingElement->parentNode;
    }
  }

  /**
   * Returns inner HTML for an element.
   *
   * @param \DOMDocument $document
   *   Document to be processed.
   * @param string $query
   *   Query to search for an element from which we should grab inner HTML.
   */
  public function getInnerHtml(\DOMDocument $document, string $query = '//body'): string {
    $xpath = new \DOMXPath($document);

    $bodyElement = $xpath->query($query)->item(0);

    $fixedMarkup = '';

    foreach ($bodyElement->childNodes as $node) {
      $fixedMarkup .= $document->saveHTML($node);
    }

    return $fixedMarkup;
  }

  /**
   * Replaces the suggestion attributes with suggestion tags.
   *
   * @param \DOMDocument $dom
   *   The DOM Document.
   * @param \DOMXPath $xpath
   *   The DOM XPath.
   */
  public function convertSuggestionsAttributes(\DOMDocument $dom, \DOMXPath $xpath): void {
    $attributes = [
      'end-before' => 'data-suggestion-end-before',
      'start-before' => 'data-suggestion-start-before',
      'start-after' => 'data-suggestion-start-after',
      'end-after' => 'data-suggestion-end-after',
    ];

    $this->convertAttributes($dom, $xpath, $attributes, 'suggestion');
  }

  /**
   * Replaces the collaboration attributes with tags in the html string.
   *
   * @param string $input
   *   The HTML string.
   * @return string
   *   The HTML string with collaboration attributes replaced by collaboration tags.
   */
  public function convertCollaborationAttributesInString(string $input): string {
    $document = Html::load($input);
    $xpath = new \DOMXPath($document);

    $this->convertSuggestionsAttributes($document, $xpath);
    $this->convertCommentAttributes($document, $xpath);

    return $this->getInnerHtml($document);
  }

  /**
   * Replaces the comment attributes with comment tags.
   *
   * @param \DOMDocument $dom
   *   The DOM Document.
   * @param \DOMXPath $xpath
   *   The DOM XPath.
   */
  public function convertCommentAttributes(\DOMDocument $dom, \DOMXPath $xpath): void {
    $attributes = [
      'end-before' => 'data-comment-end-before',
      'start-before' => 'data-comment-start-before',
      'start-after' => 'data-comment-start-after',
      'end-after' => 'data-comment-end-after',
    ];

    $this->convertAttributes($dom, $xpath, $attributes, 'comment');
  }

  /**
   * Replaces the collaboration attributes with collaboration tags.
   *
   * @param \DOMDocument $dom
   *   The DOM Document.
   * @param \DOMXPath $xpath
   *   The DOM XPath.
   * @param string[] $attributes
   *   An array mapping collaboration attributes to replace.
   * @param string $type
   *   A type of collaboration tags to process - 'suggestion' or 'comment'.
   */
  private function convertAttributes(\DOMDocument $dom, \DOMXPath $xpath, array $attributes, string $type): void {
    foreach ($attributes as $key => $attribute) {
      $queryExpression = "//*[@{$attribute}]";
      $suggestions = $xpath->query($queryExpression);

      if (!$suggestions) {
        return;
      }

      /** @var \DOMElement $suggestion */
      foreach ($suggestions as $suggestion) {
        switch ($key) {
          case 'start-before':
            $this->replaceSuggestionAttribute($dom, $suggestion, $attribute, "$type-start", 'before');
            break;

          case 'start-after':
            $this->replaceSuggestionAttribute($dom, $suggestion, $attribute, "$type-start", 'after');
            break;

          case 'end-before':
            $this->replaceSuggestionAttribute($dom, $suggestion, $attribute, "$type-end", 'before');
            break;

          case 'end-after':
            $this->replaceSuggestionAttribute($dom, $suggestion, $attribute, "$type-end", 'after');
            break;

        }
      }
    }
  }

  /**
   * Replace data-suggestion attributes with suggestion tags.
   *
   * This allows for easier and less prone for errors filtering of suggestions.
   *
   * @param \DOMDocument $dom
   *   The Dom document.
   * @param \DOMElement $suggestion
   *   An element to process.
   * @param string $attribute
   *   An attribute name to process.
   * @param string $name
   *   The tag name to create in place of attribute.
   * @param string $function
   *   Function to apply on element to place new tag in correct place.
   *   Most times it'll be 'before' or 'after'.
   *
   * @return void
   *
   * @throws \DOMException
   */
  private function replaceSuggestionAttribute(\DOMDocument $dom, \DOMElement $suggestion, string $attribute, string $qualifiedName, string $function): void {
    $value = $suggestion->getAttribute($attribute);
    $elem = new \DOMElement($qualifiedName);
    $elemNode = $dom->importNode($elem);
    $elemNode->setAttribute('name', $value);
    $suggestion->$function($elemNode);
    $suggestion->removeAttribute($attribute);
  }

  /**
   * Add extra span before the br tag in suggestion.
   *
   * @param string $context
   *   Context.
   *
   * @return string
   *   Updated context.
   */
  public function detectLineBreaks(string $context):string {
    $document = Html::load($context);
    $xpath = new \DOMXPath($document);
    $queryExpressions = [
      "//ins//br",
      "//del//br",
      "//span[contains(@class, 'marker-insertion')]//br",
      "//span[contains(@class, 'marker-deletion')]//br",
    ];

    foreach ($queryExpressions as $query) {
      $suggestions = $xpath->query($query);
      foreach ($suggestions as $suggestion) {
        $domElement = new \DOMElement('span');
        $nodeElement = $document->importNode($domElement);
        $nodeElement->setAttribute('class', 'new-line-sign');
        $suggestion->parentNode->insertBefore($nodeElement, $suggestion);
      }
    }

    return $this->getInnerHtml($document);
  }

  /**
   * Find and replace paragraphs split in suggestions. Used to display paragraph split in the suggestion notification.
   *
   * @param string $context
   *   Document context.
   *
   * @return array|string
   *   Context.
   */
  public function prepareParagraphsSplitSuggestions(string $context): array|string {
    $matchesInsertion = [];
    $matchesDeletion = [];

    preg_match_all(
      '#<suggestion-start[^<>]*insertion[^<>]*></suggestion-start>.*?<suggestion-end[^<>]*insertion[^<>]*></suggestion-end>#',
      $context,
      $matchesInsertion,
      PREG_SET_ORDER);
    preg_match_all(
      '#<suggestion-start[^<>]*deletion[^<>]*></suggestion-start>.*?<suggestion-end[^<>]*deletion[^<>]*></suggestion-end>#',
      $context,
      $matchesDeletion,
      PREG_SET_ORDER);

    $matches = array_merge($matchesInsertion, $matchesDeletion);
    foreach ($matches as $match) {
      $suggestion = reset($match);
      $fixedSuggestion = preg_replace('#</p><p[^<>]*>#', '<span class="paragraph-split-sign"></span>', $suggestion);
      $fixedSuggestion = str_replace('&nbsp;', '', $fixedSuggestion);
      $context = str_replace($suggestion, $fixedSuggestion, $context);
    }
    return $context;
  }

}
