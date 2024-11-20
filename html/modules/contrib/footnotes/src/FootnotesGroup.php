<?php

namespace Drupal\footnotes;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Store grouping of all footnotes.
 */
class FootnotesGroup implements TrustedCallbackInterface {

  /**
   * The footer markup.
   *
   * @var array
   */
  protected array $matches = [];

  /**
   * Add additional footnotes to the group.
   *
   * @param array $check_new_matches
   *   The additional footnotes to add.
   */
  public function add(array $check_new_matches) {

    // If we have existing matches to check, we compare the new ones
    // with the existing ones and potentially add the ref_ids into the existing
    // ones.
    if (count($this->matches) > 0) {
      foreach ($check_new_matches as $check_new_match_key => $check_new_match_values) {
        foreach ($check_new_match_values as $check_new_match_value_key => $check_new_match) {
          foreach ($this->matches as $existing_potential_match_key => $existing_potential_match_values) {
            foreach ($existing_potential_match_values as $existing_potential_match_value_key => $existing_potential_match) {

              // If the text portion of the ID is an exact match with an
              // existing text portion of ID.
              if ($this->textOnlyMatchId($check_new_match_value_key) === $this->textOnlyMatchId($existing_potential_match_value_key)) {

                // Remove from the new match so that we do not add it to
                // existing as a separate item.
                unset($check_new_matches[$check_new_match_key][$check_new_match_value_key]);

                // No need to search further in the existing matches, move on to
                // the next new match to check for.
                break;
              }
            }
          }
        }
      }
    }

    // If we still have new matches that no existing was found for, add them
    // to the existing for any subsequent calls to this.
    $check_new_matches = array_filter($check_new_matches);
    if ($check_new_matches) {
      foreach ($check_new_matches as $key => $match) {
        $this->matches[$key] = $match;
      }
    }
  }

  /**
   * Get an ID that considers the text only.
   *
   * The ID contains the value and hashed text and random key separated by
   * underscore as created in FootnotesFilter::buildFootnoteInstance(). For
   * finding matches, we care only about the text, not the value.
   *
   * @param string $id
   *   The full ID.
   *
   * @return string
   *   The text hashed part of the id.
   */
  protected function textOnlyMatchId(string $id): string {
    if (str_contains($id, '_')) {
      $parts = explode('_', $id);
      return $parts[1];
    }
    return $id;
  }

  /**
   * Build the footnotes footer render array.
   *
   * @return array
   *   A render array for the footer containing all matches.
   */
  public function buildFooter() {
    return [
      '#theme' => 'footnote_list',
      '#footnotes' => $this->matches,
      '#is_block' => TRUE,
    ];
  }

  /**
   * Count the current number of footnotes.
   *
   * @return int
   *   The current number of footnotes.
   */
  public function count(): int {
    return count($this->matches);
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['buildFooter'];
  }

}
