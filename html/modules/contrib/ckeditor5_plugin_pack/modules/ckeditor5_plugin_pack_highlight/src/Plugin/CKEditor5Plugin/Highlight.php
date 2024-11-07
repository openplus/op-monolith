<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack_highlight\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 Highlight Plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class Highlight extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'options' => [],
      'use_default_markers' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {

    $form['use_default_markers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use CKEditor5 default highlights'),
      '#description' => $this->t('Default CKEditor5 markers will be available with added custom markers.'),
      '#default_value' => $this->configuration['use_default_markers'] ?? TRUE,
      '#attributes' => [
        'data-editor-highlight-use-default-markers' => 'status',
      ],
    ];

    $form['classes_list'] = [
      '#type' => 'details',
      '#title' => $this->t('Default classes'),
      '#open' => FALSE,
      '#markup' => '<pre><code>
.marker-yellow { background-color: #fdfd77; }<br />
.marker-green { background-color: #62f962; }<br />
.marker-pink { background-color: #fc7899; }<br />
.marker-blue { background-color: #72ccfd; }<br />
.pen-red { background-color: transparent; color: #e71313; }<br />
.pen-green { background-color: transparent; color: #128a00; }<br />
</code></pre>',
      '#states' => [
        'visible' => [
          ':input[data-editor-highlight-use-default-markers="status"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['custom_classes_list'] = [
      '#type' => 'details',
      '#title' => $this->t('Custom classes'),
      '#open' => FALSE,
      '#description' => $this->t('Click the button below to display CSS classes for the custom highlights.'),
    ];

    $form['custom_classes_list']['preview_button'] = [
      '#type' => 'button',
      '#executes_submit_callback' => FALSE,
      '#ajax' => [
        'callback' => [$this, 'classesPreview'],
      ],
      '#value' => $this->t('Refresh custom classes'),
    ];
    $form['custom_classes_list']['classes_preview'] = [
      '#type' => 'container',
      '#id' => 'cke5-content-custom-classes-container',
    ];

    $form['custom_marker_wrapper'] = [
      '#type' => 'fieldset',
      '#id' => 'custom-marker-wrapper',
    ];

    $options = $this->configuration['options'];
    if ($form_state->isRebuilding()) {
      $userInput = $form_state->getUserInput();
      $options = $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_highlight__highlight']['custom_marker_wrapper'] ?? [];
    }

    foreach ($options as $markerId => $option) {
      $form['custom_marker_wrapper'][$markerId] = [
        '#type' => 'fieldset',
        '#id' => 'marker-container',
      ];
      $form['custom_marker_wrapper'][$markerId]['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Marker title'),
        '#maxlength' => 255,
        '#default_value' => $option['title'] ?? '',
      ];
      $form['custom_marker_wrapper'][$markerId]['color'] = [
        '#type' => 'color',
        '#title' => $this->t('Color'),
        '#default_value' => $option['color'] ?? '',
      ];
      $form['custom_marker_wrapper'][$markerId]['class_suffix'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Marker class suffix'),
        '#description' => $this->t('Depending on the marker type, the following classes will be created: <br />
         Marker: custom-highlight-marker-&lt;<b>SUFFIX</b>&gt;<br />
         PEN: custom-highlight-pen-&lt;<b>SUFFIX</b>&gt; <br /><br />
         If no suffix provided: custom-highlight-<code>&lt;<b>TYPE</b>&gt;</code>-&lt;<b>MARKER-TITLE</b>&gt;-&lt;<b>CURRENT_TEXT_FORMAT</b>&gt;
         '),
        '#maxlength' => 255,
        '#default_value' => $option['class_suffix'] ?? '',
      ];
      $form['custom_marker_wrapper'][$markerId]['type'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Type'),
        '#options' => [
          'marker' => $this->t('Marker'),
          'pen' => $this->t('Pen'),
        ],
        '#default_value' => $option['type'],
        '#ajax' => FALSE,
      ];
      $form['custom_marker_wrapper'][$markerId]['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'marker-' . $markerId . '-delete',
        '#button_type' => 'danger',
        '#submit' => [[$this, 'removeMarker']],
        '#ajax' => [
          'callback' => [$this, 'refreshMarkersCallback'],
          'wrapper' => 'custom-marker-wrapper',
        ],
        '#attributes' => [
          'data-marker-id' => $markerId,
        ],
      ];
    }
    $form['custom_marker_wrapper']['add_custom_marker'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Marker'),
      '#submit' => [[$this, 'addCustomMarker']],
      '#ajax' => [
        'callback' => [$this, 'refreshMarkersCallback'],
        'wrapper' => 'custom-marker-wrapper',
      ],
    ];
    return $form;
  }

  /**
   * Refresh preview for the custom highlights CSS classes.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function classesPreview(array $form, FormStateInterface $form_state): AjaxResponse {
    $userInput = $form_state->getUserInput();
    $input = $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_highlight__highlight']['custom_marker_wrapper'];
    $data = '';
    foreach ($input as $marker) {
      $type = array_filter($marker['type'], fn($x) => !empty($x));
      if (empty($type) || empty($marker['title'])) {
        continue;
      }
      foreach ($type as $typeValue) {
        if (!$typeValue) {
          continue;
        }
        $className = $this->getHighlightClass($typeValue, $form['format']['#value'], $marker['title'], $marker['class_suffix']);

        if ($typeValue === 'marker') {
          $className .= ' { ' . 'background-color: ' . $marker['color'] . '; }';
        }
        else {
          $className .= ' { ' . 'background-color: transparent; color: ' . $marker['color'] . '; }';
        }
        $data .= '.' . $className . '<br /><br />';
      }

    }
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#cke5-content-custom-classes-container', '<pre><code>' . $data . '</code></pre>'));
    return $response;
  }

  /**
   * Add new marker handler.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function addCustomMarker(array &$form, FormStateInterface $form_state): void {
    $userInput = $form_state->getUserInput();
    $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_highlight__highlight']['custom_marker_wrapper'][] = [];
    $form_state->setUserInput($userInput);
    $form_state->setRebuild();
  }

  /**
   * Remove handler.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function removeMarker(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $id = $trigger['#attributes']['data-marker-id'];
    $userInput = $form_state->getUserInput();
    $plugin = $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_highlight__highlight']['custom_marker_wrapper'];
    if (isset($plugin[$id])) {
      unset($plugin[$id]);
    }
    $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_highlight__highlight']['custom_marker_wrapper'] = $plugin;
    $form_state->setUserInput($userInput);

    $form_state->setRebuild();
  }

  /**
   * Refresh markers wrapper callback.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function refreshMarkersCallback(array &$form, FormStateInterface $form_state): array {
    $settings_element = $form['editor']['settings']['subform']['plugins']['ckeditor5_plugin_pack_highlight__highlight'] ?? $form;
    return $settings_element['custom_marker_wrapper'] ?? $settings_element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    if (str_contains($trigger['#id'], 'plugins-ckeditor5-plugin-pack-highlight-highlight')) {
      return;
    }
    $values = $form_state->getValues();
    $customMarkers = $values['custom_marker_wrapper'];
    // Remove add button from array.
    unset($customMarkers['add_custom_marker']);
    foreach ($customMarkers as $key => $marker) {
      $element = $form['custom_marker_wrapper'][$key];
      if (empty($marker['title'])) {
        $form_state->setError($element['title'], $this->t('Highlight: Marker title is required.'));
      }
      $type = array_filter($marker['type'], fn($x) => !empty($x));
      if (empty($type)) {
        $form_state->setError($element['type'], $this->t('Highlight: Marker type is required.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->cleanValues()->getValues();
    $this->configuration['options'] = $values['custom_marker_wrapper'] ?? [];
    $this->configuration['use_default_markers'] = $values['use_default_markers'] ?? TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $markers = $this->configuration['options'];
    $customMarkers = [];
    foreach ($markers as $key => $marker) {
      foreach ($marker['type'] as $typeKey => $type) {
        if (!$type) {
          continue;
        }
        $marker['type'] = $typeKey;
        $marker['model'] = 'custom' . ucfirst($type) . '-' . $key . '-' . $editor->get('format');
        $class = $this->getHighlightClass($type, $editor->get('format'), $marker['title'], $marker['class_suffix']);
        $marker['class'] = $class;
        $customMarkers[] = $marker;
      }

    }

    $useDefaultMarkers = $this->configuration['use_default_markers'];
    if (!empty($customMarkers) && $useDefaultMarkers) {
      $defaultMarkers = $this->getDefaultMarkers();
      $customMarkers = array_merge($customMarkers, $defaultMarkers);
    }
    if (!empty($customMarkers)) {
      // array_values() to make sure that we pass indexed array.
      $static_plugin_config['highlight']['options'] = array_values($customMarkers);
    }

    return $static_plugin_config;
  }

  /**
   * Returns default values for the Highlight plugin.
   *
   * @return array
   *   Array of markers.
   */
  private function getDefaultMarkers(): array {
    return [
      [
        'model' => 'yellowMarker',
        'class' => 'marker-yellow',
        'title' => 'Yellow marker',
        'color' => 'var(--ck-highlight-marker-yellow)',
        'type' => 'marker',
      ],
      [
        'model' => 'greenMarker',
        'class' => 'marker-green',
        'title' => 'Green marker',
        'color' => 'var(--ck-highlight-marker-green)',
        'type' => 'marker',
      ],
      [
        'model' => 'pinkMarker',
        'class' => 'marker-pink',
        'title' => 'Pink marker',
        'color' => 'var(--ck-highlight-marker-pink)',
        'type' => 'marker',
      ],
      [
        'model' => 'blueMarker',
        'class' => 'marker-blue',
        'title' => 'Blue marker',
        'color' => 'var(--ck-highlight-marker-blue)',
        'type' => 'marker',
      ],
      [
        'model' => 'redPen',
        'class' => 'pen-red',
        'title' => 'Red pen',
        'color' => 'var(--ck-highlight-pen-red)',
        'type' => 'pen',
      ],
      [
        'model' => 'greenPen',
        'class' => 'pen-green',
        'title' => 'Green pen',
        'color' => 'var(--ck-highlight-pen-green)',
        'type' => 'pen',
      ],
    ];
  }

  /**
   * Returns CSS class for the marker.
   *
   * @param string $type
   * @param string $textFormat
   * @param string $markerTitle
   * @param string|null $suffix
   *
   * @return string
   */
  private function getHighlightClass(string $type, string $textFormat, string $markerTitle, ?string $suffix): string {
    if ($suffix) {
      $class = 'custom-highlight' . '-' . $type . '-' . $suffix;
    }
    else {
      $class = 'custom-highlight' . '-' . $type . '-' . str_replace(' ', '-', trim($markerTitle)) . '-' . $textFormat;
    }
    return $class;
  }

}
