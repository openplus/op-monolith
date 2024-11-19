<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_realtime_collaboration\Form;

use Drupal\ckeditor5_premium_features\Form\SharedBuildConfigFormBase;
use Drupal\Core\Config\Config;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the configuration form of the "Realtime collaboration" feature.
 */
class SettingsForm extends SharedBuildConfigFormBase {

  const COLLABORATION_SETTINGS_ID = 'ckeditor5_premium_features_realtime_collaboration.settings';

  /**
   * {@inheritdoc}
   */
  final public function getFormId(): string {
    return 'ckeditor5_premium_features_realtime_collaboration_settings';
  }

  /**
   * {@inheritdoc}
   */
  public static function getSettingsRouteName(): string {
    return 'ckeditor5_premium_features_realtime_collaboration.form.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigId(): string {
    return self::COLLABORATION_SETTINGS_ID;
  }

  /**
   * {@inheritdoc}
   */
  public static function form(array $form, FormStateInterface $form_state, Config $config): array {
    $form['sidebar'] = [
      '#type' => 'select',
      '#title' => t('Annotation sidebar'),
      '#options' => [
        'auto' => t('Automatic'),
        'inline' => t('Use inline balloons'),
        'narrowSidebar' => t('Use narrow sidebar'),
        'wideSidebar' => t('Use wide sidebar'),
      ],
      '#default_value' => $config->get('sidebar') ?? 'auto',
    ];

    $form['prevent_scroll_out_of_view'] = [
        '#type' => 'checkbox',
        '#title' => t('Prevent scrolling sidebar items out of view.'),
        '#default_value' => $config->get('prevent_scroll_out_of_view') ?? FALSE,
        '#description' => t('If selected, the top annotation in the sidebar will never be scrolled above the top edge of the sidebar (which would make it hidden).'),
    ];

    $form['presence_list'] = [
      '#type' => 'checkbox',
      '#title' => t('Presence list'),
      '#default_value' => $config->get('presence_list') ?? TRUE,
    ];

    $form['presence_list_collapse_at'] = [
      '#type' => 'number',
      '#min' => 1,
      '#title' => t('Presence list collapse items'),
      '#default_value' => $config->get('presence_list_collapse_at') ?? 8,
    ];

    return $form;
  }

}
