<?php

/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_wproofreader\Form;

use Drupal\ckeditor5_premium_features_wproofreader\Utility\WebSpellCheckerHandler;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the configuration form of the "WProofreader" feature.
 */
class SettingsForm extends ConfigFormBase {

  const WPROOFREADER_SETTINGS_ID = 'ckeditor5_premium_features_wproofreader.settings';
  const DEFAULT_WSCBUNDLE_URL = 'https://svc.webspellchecker.net/spellcheck31/wscbundle/wscbundle.js';
  const WSC_DEFAULT_SERVICE_TYPE = 'default';
  const WSC_ON_PREMISE_SERVICE_TYPE = 'on_premise';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ckeditor5_premium_features_wproofreader_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      self::WPROOFREADER_SETTINGS_ID,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function __construct(protected WebSpellCheckerHandler $webSpellCheckerHandler, ConfigFactoryInterface $config_factory, $typedConfigManager = NULL) {
    parent::__construct($config_factory);
    $this->typedConfigManager = $typedConfigManager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ckeditor5_premium_features_wproofreader.wsc_handler'),
      $container->get('config.factory'),
      $container->get('config.typed'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state):array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config(self::WPROOFREADER_SETTINGS_ID);

    $langOptions = [];

    if ($form_state->isRebuilding()) {
      $form_state->clearErrors();
      $serviceId = $form_state->getValue('service_id');
      if ($serviceId) {
        $availableLanguages = $this->webSpellCheckerHandler->getAvailableLanguages($serviceId);
        if (!empty($availableLanguages)) {
          $langOptions = $availableLanguages;
        }
      }
    }
    else {
      $serviceId = $config->get('service_id');
      if ($serviceId) {
        $langOptions = $this->getLangOptions($serviceId);
      }
    }

    $form['service_id_error_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'service-id-error-container',
      ],
    ];

    $form['service_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service ID'),
      '#description' => $this->t('Activation key received upon subscription, required for WProofreader service use.'),
      '#default_value' => $serviceId ?? '',
      '#required' => TRUE,
      '#ajax' => [
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Validating Service ID...'),
        ],
        'callback' => '::handleServiceIdField',
        'wrapper' => 'language-container',
        'method' => 'replaceWith',
        'disable-refocus' => TRUE,
      ],
    ];

    $form['language_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'language-container',
        'style' => empty($langOptions) ? 'display: none;' : '',
      ],
    ];
    if (!empty($langOptions)) {
      $form['language_container']['lang_code'] = [
        '#type' => 'select',
        '#title' => $this->t('Language'),
        '#options' => $langOptions,
        '#default_value' => $config->get('lang_code') ?? 'auto',
        '#attributes' => ['id' => 'lang-code'],
      ];
    }

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['service_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('WProofreader deployment options'),
      '#options' => [
        self::WSC_DEFAULT_SERVICE_TYPE => $this->t('Use default endpoint (Cloud service) </br>
            <div class="form-item__description">Uses WebSpellChecker\'s cloud service by default. No additional configuration needed. Access and use are governed by <a href="@terms_url" target="_blank">Terms of Service.</a></div>', ['@terms_url' => 'https://webspellchecker.com/legal/terms-of-service/']),
        self::WSC_ON_PREMISE_SERVICE_TYPE => $this->t('Use self-hosted version endpoint </br>
            <div class="form-item__description">For deployment in your own environment. Requires custom endpoint setup. Ensures local text processing, keeping data internal.</div>'),
      ],
      '#default_value' => $config->get('service_type') ?? self::WSC_DEFAULT_SERVICE_TYPE,
    ];

    $form['advanced']['on_premise_container'] = [
      '#type' => 'container',
      '#markup' => $this->t('Please specify the custom endpoint values for the self-hosted version.'),
      '#states' => [
        'enabled' => [
          ':input[name="service_type"]' => ['value' => self::WSC_ON_PREMISE_SERVICE_TYPE],
        ],
        'visible' => [
          ':input[name="service_type"]' => ['value' => self::WSC_ON_PREMISE_SERVICE_TYPE],
        ],
      ],
    ];
    $onPremisesStates = [
      'required' => [
        ':input[name="service_type"]' => ['value' => self::WSC_ON_PREMISE_SERVICE_TYPE],
      ],
    ];

    $form['advanced']['on_premise_container']['service_protocol'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Protocol'),
      '#default_value' => $config->get('service_protocol') ?? '',
      '#attributes' => [
        'placeholder' => 'https',
      ],
      '#states' => $onPremisesStates,
    ];
    $form['advanced']['on_premise_container']['service_host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Hostname'),
      '#default_value' => $config->get('service_host') ?? '',
      '#attributes' => [
        'placeholder' => 'localhost',
      ],
      '#states' => $onPremisesStates,
    ];
    $form['advanced']['on_premise_container']['service_port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Port'),
      '#default_value' => $config->get('service_port') ?? '',
      '#attributes' => [
        'placeholder' => '443',
      ],
      '#states' => $onPremisesStates,
    ];

    $form['advanced']['on_premise_container']['service_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service path'),
      '#default_value' => $config->get('service_path') ?? '',
      '#attributes' => [
        'placeholder' => 'virtual_directory/api',
      ],
      '#states' => $onPremisesStates,
    ];

    $form['advanced']['on_premise_container']['src_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('WProofreader script URL'),
      '#default_value' => $config->get('src_url') ?? '',
      '#attributes' => [
        'placeholder' => 'https://host_name/virtual_directory/wscbundle/wscbundle.js'
      ],
      '#states' => $onPremisesStates,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
      '#disabled' => empty($langOptions),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(self::WPROOFREADER_SETTINGS_ID)
      ->setData($form_state->cleanValues()->getValues())
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $serviceId = $form_state->getUserInput()['service_id'] ?? NULL;
    $form_state->clearErrors();
    if (!$serviceId) {
      $form_state->setErrorByName('service_id', $this->t('Invalid Service ID'));
    }
    if ($serviceId && !$this->webSpellCheckerHandler->isServiceIdValid($serviceId)) {
      $form_state->setErrorByName('service_id', $this->t('Invalid Service ID'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * Display or hide lang_code field.
   *
   * @param array $form
   *   The Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function handleServiceIdField(array &$form, FormStateInterface $form_state): AjaxResponse {
    $serviceId = $form_state->getValue('service_id');
    $response = new AjaxResponse();
    $submit = $form['actions']['submit'];

    if (!$this->webSpellCheckerHandler->isServiceIdValid($serviceId)) {
      if (!isset($submit)) {
        $submit['disabled'] = TRUE;
      }
      $response->addCommand(new RemoveCommand('.messages--error'));
      $response->addCommand(new CssCommand('#service-id-error-container', ['display' => 'initial']));
      $response->addCommand(new MessageCommand($this->t('Invalid Service ID'), '.messages-list__wrapper', ['type' => 'error'], TRUE));
      $response->addCommand(new CssCommand('#language-container', ['display' => 'none']));
      $response->addCommand(new ReplaceCommand('input[type="submit"]', $submit));
      return $response;
    }
    if (isset($submit)) {
      unset($submit['disabled']);
    }
    $response->addCommand(new CssCommand('#service-id-error-container', ['display' => 'none']));
    $response->addCommand(new ReplaceCommand('input[type="submit"]', $submit));
    $response->addCommand(new InsertCommand('#language-container', $form['language_container']));
    $response->addCommand(new RemoveCommand('.messages--error'));
    return $response;
  }

  /**
   * Get available languages.
   *
   * @param string $serviceId
   *   The WSC Service ID.
   *
   * @return array
   *   Array with available languages
   */
  protected function getLangOptions(string $serviceId): array {
    $availableLanguages = $this->webSpellCheckerHandler->getAvailableLanguages($serviceId);
    if (empty($availableLanguages)) {
      return [];
    }
    return $availableLanguages;
  }

}
