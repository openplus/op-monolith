<?php

namespace Drupal\footnotes;

/**
 * Store whether dialog has been outputted.
 */
class FootnotesDialog {

  /**
   * Whether the dialog has been outputted.
   *
   * @var bool
   */
  protected bool $outputted = FALSE;

  /**
   * Setter for outputted.
   */
  public function setOutputted(): void {
    $this->outputted = TRUE;
  }

  /**
   * Getter for outputted.
   *
   * @return bool
   *   Whether the footnotes dialog wrapper has been outputted.
   */
  public function getOutputted(): bool {
    return $this->outputted;
  }

}
