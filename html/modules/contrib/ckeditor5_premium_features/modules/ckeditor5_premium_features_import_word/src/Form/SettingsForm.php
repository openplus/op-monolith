<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_import_word\Form;

use Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure CKEditor 5 Import from Word settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              TypedConfigManagerInterface $typedConfigManager,
                              protected LibraryVersionChecker $libraryVersionChecker) {
    parent::__construct($config_factory);
    $this->typedConfigManager = $typedConfigManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('ckeditor5_premium_features.core_library_version_checker')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ckeditor5_premium_features_import_word_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ckeditor5_premium_features_import_word.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ckeditor5_premium_features_import_word.settings');
    $form['info'] = [
      '#markup' => $this->t('You can learn more about configuration options in the <a target="_blank" href="@guides-url">Styles</a> guide for Import from Word.', ['@guides-url' => 'https://ckeditor.com/docs/cs/latest/guides/import-from-word/styles.html#default-styles']),
    ];
    $form['word_styles'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Word's default styles"),
      '#description' => $this->t('If checked, Word’s default styles will be preserved in the imported content.'),
      '#default_value' => $config->get('word_styles'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $cleanValues = $form_state->cleanValues()->getValues();
    $this->configFactory->getEditable('ckeditor5_premium_features_import_word.settings')
      ->setData($cleanValues)
      ->save();
    parent::submitForm($form, $form_state);
  }

}
