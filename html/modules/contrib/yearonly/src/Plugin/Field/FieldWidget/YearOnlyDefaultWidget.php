<?php

namespace Drupal\yearonly\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\WidgetInterface;

/**
 * Plugin implementation of the 'yearonly_default' widget.
 *
 * @FieldWidget(
 *   id = "yearonly_default",
 *   label = @Translation("Select Year"),
 *   field_types = {
 *     "yearonly"
 *   }
 * )
 */
class YearOnlyDefaultWidget extends WidgetBase implements WidgetInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'sort_order' => 'asc',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['sort_order'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort order'),
      '#default_value' => $this->getSetting('sort_order'),
      '#required' => TRUE,
      '#options' => [
        'asc' => $this->t('Asc'),
        'desc' => $this->t('Desc'),
      ],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = $this->t('Sort order: @sort_order', [
      '@sort_order' => strtoupper($this->getSetting('sort_order')),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field_settings = $this->getFieldSettings();
    if ($field_settings['yearonly_to'] === 'now') {
      $field_settings['yearonly_to'] = date('Y');
    }
    else if ($field_settings['yearonly_to'] === 'now_extra') {
      $field_settings['yearonly_to'] = date('Y') + $field_settings['yearonly_extra'];
    }

    $options = array_combine(range($field_settings['yearonly_from'], $field_settings['yearonly_to']), range($field_settings['yearonly_from'], $field_settings['yearonly_to']));
    if ($this->getSetting('sort_order') == 'desc') {
      $options = array_reverse($options, TRUE);
    }
    $element['value'] = $element + [
      '#type' => 'select',
      '#options' => $options,
      '#empty_value' => '',
      '#default_value' => $items[$delta]->value ?? '',
      '#description' => $this->t('Select year'),
    ];
    return $element;
  }

}
