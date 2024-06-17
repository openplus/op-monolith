<?php

namespace Drupal\insert_view_adv\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\ckeditor\CKEditorPluginConfigurableInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\editor\Entity\Editor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The plugin for insert_view_adv .
 *
 * @CKEditorPlugin(
 *   id = "insert_view_adv",
 *   label = @Translation("Advanced Insert View WYSIWYG")
 * )
 */
class InsertView extends CKEditorPluginBase implements CKEditorPluginConfigurableInterface, ContainerFactoryPluginInterface {

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Constructs a new DrupalMediaLibrary plugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module
   *   The module extension list.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ModuleExtensionList $extension_list_module) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moduleExtensionList = $extension_list_module;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.list.module')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return $this->moduleExtensionList->getPath('insert_view_adv') . '/js/plugin/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'insert_view_adv' => [
        'label' => $this->t('Advanced insert View'),
        'image' => $this->moduleExtensionList->getPath('insert_view_adv') . '/js/plugin/icons/insert_view_adv.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    $settings = $editor->getSettings();
    if (isset($settings['plugins']['insert_view_adv'])) {
      $plugin_specific_settings = $settings['plugins']['insert_view_adv'];
    }
    else {
      $plugin_specific_settings = ['enable_live_preview' => TRUE];
    }
    $plugin_specific_settings['InsertViewDialog_url'] = Url::fromRoute('insert_view_adv.editor_dialog', ['filter_format' => $editor->getFilterFormat()->id()])
      ->toString(TRUE)
      ->getGeneratedUrl();
    $plugin_specific_settings['InsertViewDialog_options'] = [
      'dialogClass' => 'insert-view-dialog',
      'title' => $this->t('Advanced Insert View'),
    ];
    $plugin_specific_settings['InsertViewPreview_url'] = Url::fromRoute('insert_view_adv.editor_preview', ['filter_format' => $editor->getFilterFormat()->id()])
      ->toString(TRUE)
      ->getGeneratedUrl();
    return $plugin_specific_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, Editor $editor) {
    $settings = $editor->getSettings();
    if (!empty($settings['plugins']['insert_view_adv'])) {
      $plugin_specific_settings = $settings['plugins']['insert_view_adv'];
    }
    else {
      $plugin_specific_settings = ['enable_live_preview' => TRUE];
    }
    $form['enable_live_preview'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable live preview of the view token'),
      '#description' => $this->t('By default CKEditor displays only token with view details, if you want to see the exact results of the view leave this checkbox checked'),
      '#default_value' => (isset($plugin_specific_settings['enable_live_preview'])) ? $plugin_specific_settings['enable_live_preview'] : TRUE,
      '#return_value' => TRUE,
    ];
    return $form;
  }

}
