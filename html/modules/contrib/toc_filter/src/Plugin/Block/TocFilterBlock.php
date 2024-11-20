<?php

namespace Drupal\toc_filter\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Core\Session\AccountInterface;
use Drupal\toc_api\Plugin\Block\TocBlockBase;

/**
 * Provides a 'TOC filter' block.
 *
 * @Block(
 *   id = "toc_filter",
 *   admin_label = @Translation("Table of contents"),
 *   category = @Translation("TOC filter")
 * )
 */
class TocFilterBlock extends TocBlockBase {

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    $node = $this->getCurrentNode();

    // If current page is not a node or does not contain a [toc] token return
    // forbidden access result.
    if (!$node || !$node->hasField('body')) {
      return AccessResult::forbidden();
    }

    // Node should have [toc] token or filter should have auto enabled.
    $format_id = $node->body->format;
    if ($format_id !== NULL){
    $format = FilterFormat::load($format_id);
      if ( $format && $format->filters('toc_filter')) {
        $toc_filter_config = $format->filters('toc_filter')->getConfiguration();
        // If auto is disabled, and there is no [toc token, don't display block.
        if (!$toc_filter_config['settings']['auto'] && stripos($node->body->value, '[toc') === FALSE) {
          return AccessResult::forbidden();
        }
      }
    } else {
      return AccessResult::forbidden();
    }
    
    // Since entities (ie node) are cached we need to pass the current node's
    // body through it's filters and see if a TOC is being generated and
    // displayed in this block.
    /** @var \Drupal\toc_api\TocManagerInterface $toc_manager */
    $toc_manager = \Drupal::service('toc_api.manager');

    // Reset removes any stored references to a current toc.
    $toc_manager->reset($this->getCurrentTocId());

    // Reprocess the node's body since the processed result is typically
    // cached via entity render caching.
    // This will create an identical TOC instance stored in the TocManager.
    check_markup($node->body->value, $node->body->format, $node->body->getLangCode());

    return parent::blockAccess($account);
  }

}
