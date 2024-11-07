<?php

namespace Drupal\footnotes\Upgrade;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;

/**
 * Provide a batch manager for upgrading Footnotes major versions.
 */
class FootnotesUpgradeBatchManager {

  /**
   * Process an individual entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity ID.
   * @param array $fields
   *   The machine names of fields that contain formatted text.
   * @param array $options
   *   The options array passed via the drush command.
   * @param array|DrushBatchContext $context
   *   The batch context.
   */
  public static function processItem(
    string $entity_type,
    int $entity_id,
    array $fields,
    array $options,
    mixed &$context,
  ): void {
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    $footnote_formats = self::getFilterFormats();
    $entity = $storage->load($entity_id);
    $context['message'] = t('Updating "@label" (@entity_id) of type @entity_type', [
      '@label' => $entity->label(),
      '@entity_id' => $entity->id(),
      '@entity_type' => $entity_type,
    ]);
    $context['results'][] = $entity_id;

    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }

    // Get translations if available.
    $entities = [$entity];
    if ($entity instanceof TranslatableInterface) {
      $languages = array_keys($entity->getTranslationLanguages());
      foreach ($languages as $language) {
        if ($entity->language()->getId() === $language) {
          continue;
        }

        if ($entity->hasTranslation($language)) {
          $entities[] = $entity->getTranslation($language);
        }
      }
    }

    // Loop through the translations.
    foreach ($entities as $entity) {
      // Get the field value and format, checking that the format
      // used is one that uses footnotes.
      foreach ($fields as $field_name) {
        $field = $entity->get($field_name);
        $format = $field->format;
        if (!in_array($format, $footnote_formats)) {
          continue;
        }
        $text = $field->value;
        if (empty($text) || !is_string($text)) {
          continue;
        }

        // Check that there is at least one footnote present.
        if (
          stristr($text, '<fn') === FALSE
          && stristr($text, '[fn') === FALSE
          && stristr($text, '[footnotes') === FALSE
        ) {
          continue;
        }

        // Do the replacement.
        $original_text = $text;
        $text = self::replace3xFootnotesWith4x($text, $options);

        // If a change has been made, save.
        if ($text != $original_text) {
          $new_value = [
            'value' => $text,
            'format' => $format,
          ];

          // In case the field is a text format with summary, keep the summary
          // in place.
          if (isset($field->summary)) {
            $new_value['summary'] = $field->summary;

            // The summary may also contain footnotes. If it does, also do the
            // replacements in the summary text.
            if (
              stristr($new_value['summary'], '<fn') !== FALSE
              || stristr($new_value['summary'], '[fn') !== FALSE
              || stristr($new_value['summary'], '[footnotes') !== FALSE
            ) {

              // Do the replacement in the summary as well.
              $new_value['summary'] = self::replace3xFootnotesWith4x($new_value['summary'], $options);
            }
          }
          $entity->set($field_name, $new_value);
          try {
            $entity->save();
          }
          catch (EntityStorageException $exception) {
            $context['message'] = t('Failed to save "@label" (@id): @message', [
              '@label' => $entity->label(),
              '@id' => $entity->id(),
              '@message' => $exception->getMessage(),
            ]);
            continue;
          }

          // Paragraphs are nested, try to get to a parent.
          // If found, save the parent non-paragraph to
          // trigger any relevant actions related to the change.
          // Rather than checking interface, use method exists
          // to avoid requiring the Paragraphs module.
          if (method_exists($entity, 'getParentEntity')) {
            $parent_entity = $entity->getParentEntity();
            while ($parent_entity && method_exists($parent_entity, 'getParentEntity')) {
              $parent_entity = $parent_entity->getParentEntity();
            }
            if ($parent_entity instanceof EntityInterface) {
              try {
                $parent_entity->save();
              }
              catch (EntityStorageException $exception) {
                $context['message'] = t('Failed to save parent entity "@label" (@id): @message', [
                  '@label' => $entity->label(),
                  '@id' => $entity->id(),
                  '@message' => $exception->getMessage(),
                ]);
                continue;
              }
            }
          }
        }
      }
    }
  }

  /**
   * Replace original text 3x styles with 4x.
   *
   * @param string $text
   *   The text.
   * @param array $options
   *   The options.
   *
   * @return string
   *   The updated text.
   */
  public static function replace3xFootnotesWith4x(string $text, array $options): string {

    // Mimic the 3x branch replace callback to function as close as
    // possible. Normalise first so we only deal with <fn> (also as per 3x
    // branch). Essentially here we go on the assumption that if it is not
    // working in the front-end theme of the 3x branch, we do not need to
    // support upgrading it, as it is already broken and therefore fine to
    // remain broken.
    // @see https://git.drupalcode.org/project/footnotes/-/blob/3.0.x/src/Plugin/Filter/FootnotesFilter.php?ref_type=heads#L156.
    $text = preg_replace('|\[fn([^\]]*)\]|', '<fn$1>', $text);
    $text = preg_replace('|\[/fn\]|', '</fn>', $text);
    $text = preg_replace('|\[footnotes([^\]]*)\]|', '<footnotes$1>', $text);

    // Deal with <fn> conversion to <footnote>.
    $pattern = '|<fn([^>]*)>(.*?)</fn>|s';
    return preg_replace_callback($pattern, function ($matches) use ($options) {
      return self::replaceCallback($matches, $options);
    }, $text);
  }

  /**
   * Helper function called from preg_replace_callback() above.
   *
   * @param mixed $matches
   *   Elements from array:
   *   - 0: complete matched string.
   *   - 1: tag name.
   *   - 2: tag attributes.
   *   - 3: tag content.
   * @param array $options
   *   The options passed to the drush command.
   *
   * @return string
   *   In the new format: <footnote data-text="" data-value="">.
   */
  public static function replaceCallback(mixed $matches, array $options): string {
    $value = $text = '';
    // Did the pattern match anything in the <fn> tag?
    if ($matches[1]) {
      // See if value attribute can parsed, either well-formed in quotes eg
      // <fn value="3">.
      if (preg_match('|value=["\'](.*?)["\']|', $matches[1], $value_match)) {
        $value = $value_match[1];
        // Or without quotes eg <fn value=8>.
      }
      elseif (preg_match('|value=(\S*)|', $matches[1], $value_match)) {
        $value = $value_match[1];
      }

      // See if text attribute can parsed, either well-formed in quotes eg
      // <fn text="3">.
      if (preg_match('|text=["\'](.*?)["\']|', $matches[1], $text_match)) {
        $text = $text_match[1];
        // Or without quotes eg <fn text=8>.
      }
      elseif (preg_match('|text=(\S*)|', $matches[1], $text_match)) {
        $text = $text_match[1];
      }
    }

    // Text may also be within the element rather than
    // set as an attribute. Within the element is the 2nd match in the original
    // preg_replace_callback.
    $text = trim($text);
    if (empty($text) && !empty($matches[2])) {
      $text = $matches[2];
    }

    $footnote_build = [
      '#type' => 'html_tag',
      '#tag' => 'footnotes',
      '#attributes' => [
        'data-value' => $value,
      ],
    ];
    if ($options['use-data-text']) {

      // The default option, for when the user uses CK Editor 5.
      $footnote_build['#attributes']['data-text'] = $text;
    }
    else {

      // Optionally store the text content within the tag instead
      // of as a data attribute. This is useful when manually
      // writing the html.
      $footnote_build['#value'] = $text;
    }
    $build = (string) \Drupal::service('renderer')->renderRoot($footnote_build);

    // Remove any possibility of trailing whitespace after the <footnotes>
    // element in case there are any trailing spaces added by the render call.
    if (str_contains($build, '</footnotes>')) {
      $parts = explode('</footnotes>', $build);
      array_pop($parts);
      return implode('', $parts) . '</footnotes>';
    }
    return $build;
  }

  /**
   * Get the formats that support footnotes.
   *
   * @return array
   *   Machine names of formats.
   */
  public static function getFilterFormats(): array {
    $format_configs = \Drupal::configFactory()->listAll('filter.format.');
    $footnote_formats = [];
    foreach ($format_configs as $format_config) {
      $config = \Drupal::configFactory()->get($format_config)->get();
      if (isset($config['filters']['filter_footnotes'])) {
        $footnote_formats[] = $config['format'];
      }
    }
    return $footnote_formats;
  }

  /**
   * Batch Finished callback.
   *
   * @param bool $success
   *   Success of the operation.
   * @param array $results
   *   Array of results for post processing.
   */
  public static function processFinished(
    bool $success,
    array $results,
  ): void {
    if ($success) {
      $message = t('@count results processed.', ['@count' => count($results)]);
    }
    else {
      $message = t('Finished with an error.');
    }
    \Drupal::messenger()->addMessage($message);
  }

}
