<?php

namespace Drupal\footnotes\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Footnotes Group' block.
 *
 * @Block(
 *   id = "footnotes_group",
 *   admin_label = @Translation("Footnotes Group"),
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity", required = FALSE, label = @Translation("Current entity context for caching")),
 *   },
 * )
 */
class FootnotesGroupBlock extends BlockBase {

  /**
   * {@inheritDoc}
   */
  public function build() {
    $build = [];

    // Use lazy building so that the output can be generated after the text
    // filters run. This calls the footnotes.group service.
    // @see \Drupal\footnotes\FootnotesGroup::buildFooter()
    $build['footnotes_group'] = [
      '#create_placeholder' => TRUE,
      '#lazy_builder' => ['footnotes.group:buildFooter', []],
    ];

    // This block will vary by the route match, so add the route context.
    $metadata = CacheableMetadata::createFromRenderArray($build);
    $metadata->setCacheContexts(['route']);

    if ($entity = $this->getContextValue('entity')) {
      assert($entity instanceof EntityInterface);
      // Using placeholder rendering requires providing the cache keys.
      $build['#cache']['keys'] = array_merge(['footnotes_group'], $entity->getCacheTagsToInvalidate());
      $metadata = $metadata->merge(CacheableMetadata::createFromObject($entity));
    }

    $metadata->applyTo($build);

    if ($this->configuration['group_via_js']) {
      $build['#attached']['library'][] = 'footnotes/footnotes.group_block_via_js';
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'group_via_js' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['group_via_js'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Group footnotes using JavaScript'),
      '#description' => $this->t('This option may be needed when there may be footnotes in lazy-loaded areas, as the default PHP only solution may not find them. For footnotes in typical Node or Paragraph fields, this is not necessary.'),
      '#default_value' => $this->configuration['group_via_js'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['group_via_js'] = $form_state->getValue('group_via_js');
  }

}
