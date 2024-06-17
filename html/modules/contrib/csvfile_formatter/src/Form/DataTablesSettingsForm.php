<?php

namespace Drupal\csvfile_formatter\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure CSV File Formatter settings for this site.
 */
class DataTablesSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'csvfile_formatter_data_tables_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['csvfile_formatter.settings'];
  }

  /**
   * Default settings for form.
   */
  public function defaultConfiguration() {
    $result = [
      'autoWidth' => FALSE,
      'deferRender' => FALSE,
      'info' => FALSE,
      'lengthChange' => FALSE,
      'ordering' => FALSE,
      'paging' => FALSE,
      'pageLength' => 10,
      'processing' => FALSE,
      'searching' => FALSE,
      'stateSave' => FALSE,
      'scrollX' => -1,
      'scrollY' => -1,
    ];
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config('csvfile_formatter.settings')->get('dataTableSettings');

    if (empty($settings)) {
      $settings = $this->defaultConfiguration();
    }

    $form['dataTable']['autoWidth'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use DataTables smart column width handling'),
      '#default_value' => $settings['autoWidth'],
    ];
    $form['dataTable']['deferRender'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use deferred rendering for additional speed of initialisation'),
      '#default_value' => $settings['deferRender'],
    ];
    $form['dataTable']['info'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Feature control table information display field'),
      '#default_value' => $settings['info'],
    ];
    $form['dataTable']['lengthChange'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow user to change the paging display length of the table'),
      '#default_value' => $settings['lengthChange'],
    ];
    $form['dataTable']['ordering'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow user to sort table'),
      '#default_value' => $settings['ordering'],
    ];
    $form['dataTable']['paging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable pagination'),
      '#default_value' => $settings['paging'],
    ];
    $form['dataTable']['pageLength'] = [
      '#type' => 'number',
      '#title' => $this->t('Page length'),
      '#min' => 0,
      '#step' => 1,
      '#description' => $this->t('Number of rows to display on a single page when using pagination.'),
      '#default_value' => $settings['pageLength'],
    ];
    $form['dataTable']['processing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable processing indicator'),
      '#default_value' => $settings['processing'],
    ];
    $form['dataTable']['searching'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable searching'),
      '#default_value' => $settings['searching'],
    ];
    $form['dataTable']['stateSave'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable saving table state on reload'),
      '#default_value' => $settings['stateSave'],
    ];
    $form['dataTable']['scrollX'] = [
      '#type' => 'number',
      '#title' => $this->t('Scroll X'),
      '#min' => -1,
      '#step' => 1,
      '#description' => $this->t('Minimum number of rows required to enable horizontal scrolling. Set to -1 to disable horizontal scrolling.'),
      '#default_value' => $settings['scrollX'],
    ];
    $form['dataTable']['scrollY'] = [
      '#type' => 'number',
      '#title' => $this->t('Scroll Y'),
      '#min' => -1,
      '#step' => 1,
      '#description' => $this->t('Constrain to given height before scrolling. Set to -1 to disable vertical scrolling.'),
      '#default_value' => $settings['scrollY'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('csvfile_formatter.settings')
      ->set('dataTableSettings', [
        'autoWidth' => $form_state->getValue('autoWidth'),
        'deferRender' => $form_state->getValue('deferRender'),
        'info' => $form_state->getValue('info'),
        'lengthChange' => $form_state->getValue('lengthChange'),
        'ordering' => $form_state->getValue('ordering'),
        'paging' => $form_state->getValue('paging'),
        'processing' => $form_state->getValue('processing'),
        'searching' => $form_state->getValue('searching'),
        'stateSave' => $form_state->getValue('stateSave'),
        'scrollX' => $form_state->getValue('scrollX'),
        'scrollY' => $form_state->getValue('scrollY'),
        'pageLength' => (int) $form_state->getValue('pageLength'),
      ])->save();

    parent::submitForm($form, $form_state);
  }

}
