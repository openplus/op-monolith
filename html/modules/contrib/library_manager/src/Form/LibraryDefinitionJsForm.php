<?php

namespace Drupal\library_manager\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Library definition js form.
 *
 * @property \Drupal\library_manager\LibraryDefinitionInterface $entity
 */
class LibraryDefinitionJsForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $defaults = [
      'file_name' => '',
      'code' => '',
      'preprocess' => TRUE,
      'minified' => FALSE,
      'attributes' => '',
      'nomodulecheck' =>  FALSE,
      'typemodulecheck' =>  FALSE,
      'weight' => 0,
      'external' => FALSE,
      'code_type' => 'code',
      'file_upload' => NULL,
      'url' => '',
      'header' => FALSE,
    ];

    $route_match = $this->getRouteMatch();
    $file_id = $route_match->getParameter('file_id');
    if (!$route_match->getParameter('is_new')) {
      // This JS file should exist in the entity.
      $data = $this->entity->getJsFile($file_id);
      if (!$data) {
        throw new NotFoundHttpException();
      }
      $defaults = $data + $defaults;
    }

    $form['file_id'] = [
      '#type' => 'value',
      '#value' => $file_id,
    ];

    $form['header'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load the script in the header of the page'),
      '#default_value' => $defaults['header'],
    ];

    $form['preprocess'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preprocess'),
      '#default_value' => $defaults['preprocess'],
    ];

    $form['minified'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Minified'),
      '#default_value' => $defaults['minified'],
    ];

    $form['typemodulecheck'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Type Module'),
      '#default_value' => $defaults['typemodulecheck'],
    ];

    $form['nomodulecheck'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('No Module'),
      '#default_value' => $defaults['nomodulecheck'],
    ];


    $weights = range(-10, 0);
    $form['weight'] = [
      // Use 'select' because 'weight' element does not support '#min' property.
      '#type' => 'select',
      '#title' => $this->t('Weight'),
      '#default_value' => $defaults['weight'],
      '#options' => array_combine($weights, $weights),
    ];

    $form['external'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('External'),
      '#default_value' => $defaults['external'],
    ];

    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Url'),
      '#default_value' => $defaults['url'],
      '#states' => ['visible' => [':input[name="external"]' => ['checked' => TRUE]]],
    ];

    $form['code_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="external"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['code_wrapper']['code_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Code Type'),
      '#options' => [
        'file_upload' => $this->t('File Upload'),
        'code' => $this->t('Code'),
      ],
      '#required' => TRUE,
      '#default_value' => $defaults['code_type'],
    ];

    // Add a wrapper, because managed_file didn't support states api until https://www.drupal.org/node/2847425 be
    // solved.
    $form['code_wrapper']['file_upload_wrapper'] = [
      '#type' => 'container',
      '#weight' => 100,
      '#states' => [
        'visible' => [
          ':input[name="code_type"]' => ['value' => 'file_upload'],
        ],
      ],
    ];

    $libraries_path = \Drupal::config('library_manager.settings')->get('libraries_path');
    // To use this feature of uploading js file, you must add $config['system.file']['allow_insecure_uploads'] = true;
    // to your settings.php
    $form['code_wrapper']['file_upload_wrapper']['file_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Css File Upload'),
      '#description' => $this->t('Upload a file, allowed extensions: js.
      Js file is dangerous for Drupal, Use with caution. Add $config[\'system.file\'][\'allow_insecure_uploads\'] = true; in your settings.php to bypass restrictions.'),
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'js'],
      ],
      '#upload_location' => 'public://libraries/file_upload',
    ];

    if (!empty($defaults['file_upload'])) {
      $form['code_wrapper']['file_upload_wrapper']['file_upload']['#default_value'] = [$defaults['file_upload']];
    }

    $form['code_wrapper']['file_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File name'),
      '#placeholder' => 'example.js',
      '#default_value' => $defaults['file_name'],
      '#states' => [
        'visible' => [
          ':input[name="code_type"]' => ['value' => 'code'],
        ],
        'required' => [
          ':input[name="code_type"]' => ['value' => 'code'],
        ],
      ],
    ];

    $form['code_wrapper']['code'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Code'),
      '#default_value' => $defaults['code'],
      '#attributes' => [
        'class' => ['library-definition-edit-code'],
      ],
      '#rows' => 15,
      '#codemirror' => [
        'mode' => 'javascript',
        'lineNumbers' => TRUE,
        'buttons' => [
          'undo',
          'redo',
          'enlarge',
          'shrink',
        ],
      ],
      '#states' => [
        'visible' => [
          ':input[name="code_type"]' => ['value' => 'code'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $file_name = $form_state->getValue('file_name');
    $code_type = $form_state->getValue('code_type');
    if ($code_type != 'file_upload') {
      if (!preg_match('#^\w[\w\-\.\/]*\.js$#i', $file_name) || strpos($file_name, '..') !== FALSE) {
        $form_state->setError($form['file_name'], $this->t('The file name is not correct.'));
      }
    }
  }

  /**
   * Returns the action form element for the current entity form.
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {

    $element = parent::actionsElement($form, $form_state);

    $file_id = $form['file_id']['#value'];

    if (isset($file_id)) {
      // Change link url to point on JS delete form instead of entity delete
      // form.
      $route_parameters = [
        'library_definition' => $this->entity->id(),
        'file_id' => $form['file_id']['#value'],
      ];
      $element['delete']['#url'] = Url::fromRoute('entity.library_definition.delete_js_form', $route_parameters);
    }
    else {
      $element['delete']['#access'] = FALSE;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $values = $form_state->getValues();
    $file_id = $values['file_id'];

    $js = $this->entity->get('js');
    if (!$file_id) {
      $ids = array_keys($js);
      $file_id = count($ids) > 0 ? max($ids) + 1 : 1;
    }

    if ($values['code_type'] == 'file_upload' && isset($values['file_upload'][0])) {
      $uploaded_file_id = $values['file_upload'][0];
      if (!empty($uploaded_file_id)) {
        $uploaded_file = \Drupal::entityTypeManager()->getStorage('file')->load($uploaded_file_id);
        if (!empty($uploaded_file)) {
          if (!$uploaded_file->isPermanent()) {
            $uploaded_file->setPermanent();
            $uploaded_file->save();
          }
        }
      }
    }

    $attributes = [];
    if ($values['typemodulecheck']) {
      $attributes['type'] = 'module';
    }
    if ($values['nomodulecheck']) {
      $attributes['nomodule'] = TRUE;
    }

    $js[$file_id] = [
      'file_name' => $values['file_name'],
      'preprocess' => $values['preprocess'],
      'minified' => $values['minified'],
      'typemodulecheck' => $values['typemodulecheck'],
      'nomodulecheck' => $values['nomodulecheck'],
      'attributes' => $attributes,
      'weight' => $values['weight'],
      'external' => $values['external'],
      'code' => $values['code'],
      'code_type' => $values['code_type'],
      'file_upload' => $values['file_upload'][0] ?? NULL,
      'url' => $values['url'],
      'header' => $values['header'],
    ];

    $this
      ->entity
      ->set('js', $js)
      ->save();

    $this->messenger()->addStatus($this->t('The JS file has been saved.'));

    $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
  }

}
