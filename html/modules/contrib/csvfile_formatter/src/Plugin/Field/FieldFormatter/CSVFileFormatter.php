<?php

namespace Drupal\csvfile_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\File\FileSystem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Plugin implementation of the 'csvfile_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "csvfile_formatter",
 *   module = "csvfile_formatter",
 *   label = @Translation("CSV file as table"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class CSVFileFormatter extends FileFormatterBase {

  /**
   * Inject file_system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Inject config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Inject HTTP request_stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Get configFactory value.
   */
  public function getConfigFactory() {
    return $this->configFactory;
  }

  /**
   * Set configFactory value.
   */
  public function setConfigFactory($config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Get fileSystem value.
   */
  public function getFileSystem() {
    return $this->fileSystem;
  }

  /**
   * Set fileSystem value.
   */
  public function setFileSystem($fs) {
    $this->fileSystem = $fs;
  }

  /**
   * Get requestStack value.
   */
  public function getRequestStack() {
    return $this->requestStack;
  }

  /**
   * Set requestStack value.
   */
  public function setRequestStack($rs) {
    $this->requestStack = $rs;
  }

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, $field_definition, array $settings, $label, $view_mode, array $third_party_settings, FileSystem $file_system, ConfigFactoryInterface $config_factory, RequestStack $request_stack) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->setFileSystem($file_system);
    $this->setConfigFactory($config_factory);
    $this->setRequestStack($request_stack);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('file_system'),
      $container->get('config.factory'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'csvfile_formatter_download_link' => TRUE,
      'csvfile_formatter_download_link_after_table' => FALSE,
      'csvfile_formatter_has_header' => FALSE,
      'csvfile_formatter_separator' => ',',
      'csvfile_formatter_enclosure' => '"',
      'csvfile_formatter_escape' => '\\',
      'csvfile_formatter_table_class' => '',
      'csvfile_formatter_header_class' => '',
      'csvfile_formatter_row_class' => '',
      'csvfile_formatter_utf8_process' => FALSE,
      'csvfile_formatter_sticky_headers' => FALSE,
      'csvfile_formatter_smart_urls' => FALSE,
      'csvfile_formatter_use_datatables' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $settings = $this->getSettings();

    $form['csvfile_formatter_download_link'] = [
      '#title' => $this->t('Display CSV file download link.'),
      '#type' => 'checkbox',
      '#description' => $this->t('Enable to display a download link for the CSV file.'),
      '#default_value' => $settings['csvfile_formatter_download_link'],
      '#required' => FALSE,
    ];

    $form['csvfile_formatter_download_link_after_table'] = [
      '#title' => $this->t('Show CSV file download links after the table.'),
      '#type' => 'checkbox',
      '#description' => $this->t('Enable to place the CSV download links after the table.'),
      '#default_value' => $settings['csvfile_formatter_download_link_after_table'],
      '#required' => FALSE,
    ];

    $form['csvfile_formatter_has_header'] = [
      '#title' => $this->t('CSV file has header row'),
      '#type' => 'checkbox',
      '#description' => $this->t('Enable if the first row of the CSV file is a header row.'),
      '#default_value' => $settings['csvfile_formatter_has_header'],
      '#required' => FALSE,
    ];

    $form['csvfile_formatter_separator'] = [
      '#title' => $this->t('CSV field separator'),
      '#description' => $this->t('Character separating fields in a CSV row.'),
      '#type' => 'textfield',
      '#size' => 1,
      '#default_value' => $settings['csvfile_formatter_separator'],
      '#required' => TRUE,
    ];

    $form['csvfile_formatter_enclosure'] = [
      '#title' => $this->t('CSV field enclosure character'),
      '#description' => $this->t('Character indicating an enclosed field in a CSV row.'),
      '#type' => 'textfield',
      '#size' => 1,
      '#default_value' => $settings['csvfile_formatter_enclosure'],
      '#required' => FALSE,
    ];

    $form['csvfile_formatter_escape'] = [
      '#title' => $this->t('CSV field escape character'),
      '#description' => $this->t('Character indicating an escape sequence in a CSV row.'),
      '#type' => 'textfield',
      '#size' => 1,
      '#default_value' => $settings['csvfile_formatter_escape'],
      '#required' => FALSE,
    ];

    $form['csvfile_formatter_table_class'] = [
      '#title' => $this->t('Table CSS class'),
      '#type' => 'textfield',
      '#size' => 255,
      '#description' => $this->t('CSS class to use for the table.'),
      '#default_value' => $settings['csvfile_formatter_table_class'],
      '#required' => FALSE,
    ];

    $form['csvfile_formatter_header_class'] = [
      '#title' => $this->t('Table headers CSS class'),
      '#type' => 'textfield',
      '#size' => 255,
      '#description' => $this->t('CSS class to use for the table headers.'),
      '#default_value' => $settings['csvfile_formatter_header_class'],
      '#required' => FALSE,
    ];

    $form['csvfile_formatter_row_class'] = [
      '#title' => $this->t('Table row CSS class'),
      '#type' => 'textfield',
      '#size' => 255,
      '#description' => $this->t('CSS class to use for the table rows.'),
      '#default_value' => $settings['csvfile_formatter_row_class'],
      '#required' => FALSE,
    ];

    $form['csvfile_formatter_utf8_process'] = [
      '#title' => $this->t('Process files as UTF-8 content'),
      '#type' => 'checkbox',
      '#description' => $this->t('Enable to process CSV files as UTF-8 content.'),
      '#default_value' => $settings['csvfile_formatter_utf8_process'],
      '#required' => FALSE,
    ];

    $form['csvfile_formatter_sticky_headers'] = [
      '#title' => $this->t('Sticky table headers'),
      '#type' => 'checkbox',
      '#description' => $this->t('Enable to make table column headers sticky.'),
      '#default_value' => $settings['csvfile_formatter_sticky_headers'],
      '#required' => FALSE,
    ];

    $form['csvfile_formatter_smart_urls'] = [
      '#title' => $this->t('Smart URL handling'),
      '#type' => 'checkbox',
      '#description' => $this->t('Enable to make links out of URLs in CSV files.'),
      '#default_value' => $settings['csvfile_formatter_smart_urls'],
      '#required' => FALSE,
    ];

    $form['csvfile_formatter_use_datatables'] = [
      '#title' => $this->t('Use DataTables'),
      '#type' => 'checkbox',
      '#description' => $this->t('Enable to use the DataTables javascript library. Please ensure "CSV file has header row" is checked before enabling.'),
      '#default_value' => $settings['csvfile_formatter_use_datatables'],
      '#required' => FALSE,
    ];

    $form['csvfile_formatter_datatables_settings_link'] = [
      '#type' => 'link',
      '#title' => $this->t('More DataTables settings'),
      '#url' => Url::fromRoute('csvfile_formatter.data_tables_settings'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $settings = $this->getSettings();

    if ($settings['csvfile_formatter_download_link']) {
      $summary[] = $this->t('Display CSV file download link.');
    }
    else {
      $summary[] = $this->t('Do not display CSV file download link.');
    }

    if ($settings['csvfile_formatter_download_link_after_table']) {
      $summary[] = $this->t('Show CSV file download links after the table.');
    }
    else {
      $summary[] = $this->t('Show CSV file download links before the table.');
    }

    if ($settings['csvfile_formatter_has_header']) {
      $summary[] = $this->t('CSV file has header row.');
    }
    else {
      $summary[] = $this->t('CSV file does not have header row.');
    }

    if ($settings['csvfile_formatter_separator']) {
      $summary[] = $this->t('CSV separator character: @sep', ['@sep' => $settings['csvfile_formatter_separator']]);
    }

    if ($settings['csvfile_formatter_enclosure']) {
      $summary[] = $this->t('CSV enclosure character: @enc', ['@enc' => $settings['csvfile_formatter_enclosure']]);
    }

    if ($settings['csvfile_formatter_escape']) {
      $summary[] = $this->t('CSV escape character: @esc', ['@esc' => $settings['csvfile_formatter_escape']]);
    }

    if ($settings['csvfile_formatter_table_class']) {
      $summary[] = $this->t('Table CSS class: @class', ['@class' => $settings['csvfile_formatter_table_class']]);
    }

    if ($settings['csvfile_formatter_header_class']) {
      $summary[] = $this->t('Table headers CSS class: @hdr', ['@hdr' => $settings['csvfile_formatter_header_class']]);
    }

    if ($settings['csvfile_formatter_row_class']) {
      $summary[] = $this->t('Table row CSS class: @row', ['@row' => $settings['csvfile_formatter_row_class']]);
    }

    if ($settings['csvfile_formatter_utf8_process']) {
      $summary[] = $this->t('Process CSV files as UTF-8 content.');
    }

    if ($settings['csvfile_formatter_sticky_headers']) {
      $summary[] = $this->t('Sticky table headers enabled.');
    }

    if ($settings['csvfile_formatter_smart_urls']) {
      $summary[] = $this->t('Smart URL handling enabled.');
    }

    if ($settings['csvfile_formatter_use_datatables']) {
      $summary[] = $this->t('Use DataTables.');
    }

    return $summary;
  }

  /**
   * Process column items.
   */
  private function processColumnData($column_data) {
    $result = $column_data;

    $settings = $this->getSettings();

    if ($settings['csvfile_formatter_smart_urls']) {
      // Make links out of URLs in the CSV files.
      $matched = filter_var($column_data, FILTER_VALIDATE_URL);
      if ($matched !== FALSE) {
        $url = Url::fromUri($matched);
        $result = Link::fromTextAndUrl($matched, $url)->toString();
      }
      // Make links out of email addresses in the CSV files.
      $matched = filter_var($column_data, FILTER_VALIDATE_EMAIL);
      if ($matched !== FALSE) {
        $url = Url::fromUri('mailto:' . $matched);
        $result = Link::fromTextAndUrl($matched, $url)->toString();
      }
      // Bonus: Make links out of simple Markdown link syntax ([text](link))
      // in the CSV files.
      $matched = preg_match('/\[(.*)\]\((.*)\)/', $column_data ?? '', $matches);
      if ($matched != 0) {
        if ($matches[2][0] == '#') {
          // Handle in-page anchor links.
          $parameters = $this->getRequestStack()->getCurrentRequest()->query->all();
          $url = Url::fromRoute('<current>', $parameters, ['fragment' => substr($matches[2], 1)]);
        }
        else {
          $url = Url::fromUri($matches[2]);
        }
        $result = Link::fromTextAndUrl($matches[1], $url)->toString();
      }
    }

    return $result;
  }

  /**
   * Read a CSV file and convert to an HTML table render array.
   */
  private function readcsv($filename, $fieldname, $entity_id, $description = '') {
    $render_array = [];

    $settings = $this->getSettings();

    $header = $settings['csvfile_formatter_has_header'];
    $separator = empty($settings['csvfile_formatter_separator']) ? ',' : $settings['csvfile_formatter_separator'];
    $enclosure = empty($settings['csvfile_formatter_enclosure']) ? '"' : $settings['csvfile_formatter_enclosure'];
    $escape = empty($settings['csvfile_formatter_escape']) ? '\\' : $settings['csvfile_formatter_escape'];
    $row_classes = '';
    if ($settings['csvfile_formatter_row_class']) {
      $row_classes = implode(' ', explode(' ', $settings['csvfile_formatter_row_class']));
    }

    $table_data = [];
    $table_data['header'] = [];
    $table_data['rows'] = [];

    if (!empty(ini_get('auto_detect_line_endings')) && !ini_get('auto_detect_line_endings')) {
      ini_set('auto_detect_line_endings', TRUE);
    }
    $handle = fopen($filename, "r");

    if (!is_bool($handle)) {

      $colcount = 0;
      if ($header) {
        // Read first row of CSV file as header row.
        $header_classes = '';
        if ($settings['csvfile_formatter_header_class']) {
          $header_classes = implode(' ', explode(' ', $settings['csvfile_formatter_header_class']));
        }
        $csvcontents = fgetcsv($handle, 0, $separator, $enclosure, $escape);
        // Handle lines as UTF-8 content.
        if ($settings['csvfile_formatter_utf8_process']) {
          $csvcontents = array_map("utf8_encode", $csvcontents);
        }
        foreach ($csvcontents as $headercolumn) {
          $table_data['header'][$colcount]['data'] = $this->processColumnData($headercolumn);
          if ($settings['csvfile_formatter_header_class']) {
            $table_data['header'][$colcount]['class'] = $header_classes;
          }
          $colcount++;
        }
      }

      // Create data rows.
      $rowcount = 0;
      while ($csvcontents = fgetcsv($handle, 0, $separator, $enclosure, $escape)) {
        // Handle lines as UTF-8 content.
        if ($settings['csvfile_formatter_utf8_process']) {
          $csvcontents = array_map("utf8_encode", $csvcontents);
        }
        $table_data['rows'][$rowcount]['data'] = [];
        $columnindex = 0;
        foreach ($csvcontents as $column) {
          $table_data['rows'][$rowcount]['data'][$columnindex] = $this->processColumnData($column);
          $columnindex++;
        }
        if ($settings['csvfile_formatter_row_class']) {
          $table_data['rows'][$rowcount]['class'] = $row_classes;
        }
        $rowcount++;
      }

      fclose($handle);

      // Build HTML Table render array.
      $render_array['#theme'] = 'table';
      if ($header) {
        $render_array['#header'] = $table_data['header'];
        $render_array['#header_columns'] = $colcount;
      }
      $render_array['#rows'] = $table_data['rows'];
      if ($settings['csvfile_formatter_table_class']) {
        $table_classes = explode(' ', $settings['csvfile_formatter_table_class']);
        $render_array['#attributes']['class'] = $table_classes;
      }
      if ($settings['csvfile_formatter_use_datatables']) {
        $render_array['#attributes']['class'][] = 'add-externaljs-csvfiletable';
      }
      $render_array['#sticky'] = FALSE;
      if ($settings['csvfile_formatter_sticky_headers']) {
        $render_array['#sticky'] = TRUE;
      }
      if (!empty($description)) {
        // Use field description as table caption.
        $render_array['#caption'] = $description;
      }
      $render_array['#attributes']['id'] = $fieldname . '-csvfiletable';
    }

    ini_set('auto_detect_line_endings', FALSE);

    return $render_array;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $settings = $this->getSettings();

    if (!$items->isEmpty()) {
      $item_count = 0;
      $field_name = $items->getName();
      foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
        /**
         * @var \Drupal\file\FileInterface $file
         * @var \Drupal\file\Plugin\Field\FieldType\FileItem $item
         */
        $item = $file->_referringItem;
        if ($item->isDisplayed() && $item->entity) {
          $elemcount = 0;

          $description = '';
          if ($item->getFieldDefinition()->getSetting('description_field')) {
            $description = $item->description;
          }
          if (empty($description)) {
            $description = $file->get('filename')->value;
          }
          $file_link_element = [
            '#theme' => 'file_link',
            '#file' => $file,
            '#description' => $description,
            '#cache' => [
              'tags' => $file->getCacheTags(),
            ],
          ];

          if ($settings['csvfile_formatter_download_link'] &&
              !($settings['csvfile_formatter_download_link_after_table'])) {
            // Display CSV file download link.
            $elements[$delta][$elemcount] = $file_link_element;
            $elemcount++;
          }

          // Render HTML table from CSV file.
          $file_name = $this->getFileSystem()->realpath($file->get('uri')->value);
          $table_data = $this->readcsv($file_name, $field_name . '-' . $item_count, $item->getEntity()->id(), $item->description);
          $elements[$delta][$elemcount] = $table_data;

          if ($settings['csvfile_formatter_download_link'] &&
              ($settings['csvfile_formatter_download_link_after_table'])) {
            $elemcount++;
            // Display CSV file download link.
            $elements[$delta][$elemcount] = $file_link_element;
          }

          // Pass field item attributes to the theme function.
          if (isset($item->_attributes)) {
            $elements[$delta] += ['#attributes' => []];
            $elements[$delta]['#attributes'] += $item->_attributes;
            // Unset field item attributes since they have been included in the
            // formatter output and shouldn't be rendered in the field template.
            unset($item->_attributes);
          }
        }
        $item_count++;
      }
    }

    if ($settings['csvfile_formatter_use_datatables']) {
      $config = $this->getConfigFactory()->get('csvfile_formatter.settings')->get('dataTableSettings');
      $elements['#attached']['library'][] = 'csvfile_formatter/csvfile_formatter';
      $elements['#attached']['drupalSettings']['csvFormatter']['dataTable'][] = $config;
    }

    return $elements;
  }

}
