<?php

namespace Drupal\footnotes\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\editor\Ajax\EditorDialogSave;

/**
 * Ckeditor dialog form to insert webform submission results in text.
 */
class FootnotesDialogForm extends FormBase {

  /**
   * Access callback for viewing the dialog.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   *
   * @return \Drupal\Core\Access\AccessResult|\Drupal\Core\Access\AccessResultReasonInterface
   *   The access result.
   */
  public function checkAccess(AccountProxyInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'use text format footnote');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'footnotes_dialog_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $uuid = NULL) {
    $form['text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Footnote content'),
      '#default_value' => $this->getRequest()->query->get('text') ?? '',
      '#required' => TRUE,
      '#format' => 'footnote',
      '#allowed_formats' => ['footnote'],
      '#description' => $this->t('This is the content shown when the footnote is opened. The clickable text to open the footnote is by default automatically number 1, 2, 3, etc.'),
    ];
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Footnote value'),
      '#default_value' => $this->getRequest()->query->get('value') ?? '',
      '#required' => FALSE,
      '#description' => $this->t('Leave this blank to automatically number the footnotes.'),
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
        '#ajax' => [
          'callback' => [$this, 'ajaxSubmitForm'],
          'disable-refocus' => TRUE,
        ],
      ],
    ];

    return $form;
  }

  /**
   * Ajax submit callback to insert or replace the html in ckeditor.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|array
   *   Ajax response for injecting html in ckeditor.
   */
  public static function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getErrors()) {
      return $form;
    }
    $response = new AjaxResponse();

    $response->addCommand(new EditorDialogSave([
      'attributes' => [
        'data-value' => $form_state->getValue('value'),
        'data-text' => $form_state->getValue('text'),
      ],
    ]));

    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Required but not used.
  }

}
