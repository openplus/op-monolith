<?php

namespace Drupal\webform_migrate\Plugin\migrate\source\d7;

use Drupal\migrate\Event\RollbackAwareInterface;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Drupal\webform\Entity\Webform;
use Drupal\node\Entity\Node;
use Drupal\webform\Utility\WebformYaml;
use Drupal\Component\Utility\Bytes;

/**
 * Drupal 7 webform source from database.
 *
 * @MigrateSource(
 *   id = "d7_webform",
 *   core = {7},
 *   source_module = "webform",
 *   destination_module = "webform"
 * )
 */
class D7Webform extends DrupalSqlBase implements RollbackAwareInterface {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('webform', 'wf');
    $query->innerJoin('node', 'n', 'wf.nid=n.nid');
    $query->innerJoin('node_revision', 'nr', 'n.vid=nr.vid');

    $query->fields('wf', [
      'nid',
      'confirmation',
      'confirmation_format',
      'redirect_url',
      'status',
      'block',
      'allow_draft',
      'auto_save',
      'submit_notice',
      'submit_text',
      'submit_limit',
      'submit_interval',
      'total_submit_limit',
      'total_submit_interval',
      'progressbar_bar',
      'progressbar_page_number',
      'progressbar_percent',
      'progressbar_pagebreak_labels',
      'progressbar_include_confirmation',
      'progressbar_label_first',
      'progressbar_label_confirmation',
      'preview',
      'preview_next_button_label',
      'preview_prev_button_label',
      'preview_title',
      'preview_message',
      'preview_message_format',
      'preview_excluded_components',
      'next_serial',
      'confidential',
    ])
      ->fields('nr', [
        'title',
      ]
    );

    $query->addField('n', 'uid', 'node_uid');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    $this->filterDefaultFormat = $this->variableGet('filter_default_format', '1');
    return parent::initializeIterator();
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'nid' => $this->t('Node ID'),
      'title' => $this->t('Webform title'),
      'node_uid' => $this->t('Webform author'),
      'confirmation' => $this->t('Confirmation message'),
      'confirmation_type' => $this->t('Confirmation type'),
      'status' => $this->t('Status'),
      'submit_text' => $this->t('Submission text'),
      'submit_limit' => $this->t('Submission limit'),
      'submit_interval' => $this->t('Submission interval'),
      'submit_notice' => $this->t('Submission notice'),
      'allow_draft' => $this->t('Draft submission allowed'),
      'redirect_url' => $this->t('Redirect url'),
      'block' => $this->t('Block'),
      'auto_save' => $this->t('Automatic save'),
      'total_submit_limit' => $this->t('Total submission limit'),
      'total_submit_interval' => $this->t('Total submission interval'),
      'webform_id' => $this->t('Id to be used for  Webform'),
      'elements' => $this->t('Elements for the Webform'),
      'confirmation_format' => $this->t('The filter_format.format of the confirmation message.'),
      'auto_save' => $this->t('Boolean value for whether submissions to this form should be auto-saved between pages.'),
      'progressbar_bar' => $this->t('Boolean value indicating if the bar should be shown as part of the progress bar.'),
      'progressbar_page_number' => $this->t('Boolean value indicating if the page number should be shown as part of the progress bar.'),
      'progressbar_percent' => $this->t('Boolean value indicating if the percentage complete should be shown as part of the progress bar.'),
      'progressbar_pagebreak_labels' => $this->t('Boolean value indicating if the pagebreak labels should be included as part of the progress bar.'),
      'progressbar_include_confirmation' => $this->t('Boolean value indicating if the confirmation page should count as a page in the progress bar.'),
      'progressbar_label_first' => $this->t('Label for the first page of the progress bar.'),
      'progressbar_label_confirmation' => $this->t('Label for the last page of the progress bar.'),
      'preview' => $this->t('Boolean value indicating if this form includes a page for previewing the submission.'),
      'preview_next_button_label' => $this->t('The text for the button that will proceed to the preview page.'),
      'preview_prev_button_label' => $this->t('The text for the button to go backwards from the preview page.'),
      'preview_title' => $this->t('The title of the preview page, as used by the progress bar.'),
      'preview_message' => $this->t('Text shown on the preview page of the form.'),
      'preview_message_format' => $this->t('The filter_format.format of the preview page message.'),
      'preview_excluded_components' => $this->t('Comma-separated list of component IDs that should not be included in this form’s confirmation page.'),
      'next_serial' => $this->t('The serial number to give to the next submission to this webform.'),
      'confidential' => $this->t('Boolean value for whether to anonymize submissions.'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $elements = '';

    $nid = $row->getSourceProperty('nid');
    $webform = $this->buildFormElements($nid);
    $elements .= $webform['elements'];
    $handlers = $this->buildEmailHandlers($nid, $webform['xref']);
    $access = $this->buildAccessTable($nid);

    $confirm = $row->getSourceProperty('redirect_url');
    if ($confirm == '<confirmation>') {
      $confirm_type = 'page';
      $row->setSourceProperty('redirect_url', '');
    }
    elseif ($confirm == '<none>') {
      $confirm_type = 'inline';
      $row->setSourceProperty('redirect_url', '');
    }
    else {
      $confirm_type = 'url';
    }
    if ($row->getSourceProperty('submit_limit') < 0) {
      $row->setSourceProperty('submit_limit', '');
    }
    if ($row->getSourceProperty('total_submit_limit') < 0) {
      $row->setSourceProperty('total_submit_limit', '');
    }
    $row->setSourceProperty('confirmation_type', $confirm_type);
    $row->setSourceProperty('elements', $elements);
    $row->setSourceProperty('handlers', $handlers);
    $row->setSourceProperty('access', $access);
    $row->setSourceProperty('webform_id', 'webform_' . $nid);
    $row->setSourceProperty('status', $row->getSourceProperty('status') ? 'open' : 'closed');
    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['nid']['type'] = 'integer';
    $ids['nid']['alias'] = 'wf';
    return $ids;
  }

  /**
   * Build form elements from webform component table.
   */
  protected function buildFormElements($nid) {
    // Resulting array build that will be converted to YAML.
    $build = [];
    // Array with all elements keyed by form_key for a quick access.
    $references = [];

    $query = $this->select('webform_component', 'wc');
    $query->fields('wc', [
      'nid',
      'cid',
      'pid',
      'form_key',
      'name',
      'type',
      'value',
      'extra',
      'required',
      'weight',
    ]);
    $components = $query->condition('nid', $nid)->orderBy('pid')->orderBy('weight')->execute();
    $children = [];
    $parents = [];
    $elements = [];
    $xref = [];

    // Build an array of elements in the correct order for rendering based on
    // pid and weight and a cross reference array to match cid with form_key
    // used by email handler.
    $multiPage = FALSE;
    foreach ($components as $component) {
      $xref[$component['cid']] = $component['form_key'];
      if ($component['type'] == 'pagebreak') {
        // Pagebreak found so we have a multi-page form.
        $multiPage = TRUE;
      }
      $children[$component['pid']][] = $component['cid'];
      $parents[$component['cid']][] = $component['pid'];
      $elements[$component['cid']] = $component;
    }
    // Keeps track of the parents we have to process, the last entry is used
    // for the next processing step.
    $process_parents = [];
    $process_parents[] = 0;
    $elements_tree = [];
    // Loops over the parent components and adds its children to the tree array.
    // Uses a loop instead of a recursion, because it's more efficient.
    while (count($process_parents)) {
      $parent = array_pop($process_parents);
      // The number of parents determines the current depth.
      $depth = count($process_parents);
      if (!empty($children[$parent])) {
        $has_children = FALSE;
        $child = current($children[$parent]);
        do {
          if (empty($child)) {
            break;
          }
          $element = &$elements[$child];
          $element['depth'] = $depth;
          // We might get element with same form_key
          // d8 doesn't like that so rename it.
          if ($depth > 0) {
            $element['form_key'] = $element['form_key'] . '_' . $element['pid'];
          }

          // Rename fieldsets to it's own unique key.
          if ($element['type'] == 'fieldset' && strpos($element['form_key'], 'fieldset') === FALSE) {
            $element['form_key'] = 'fieldset_' . $element['form_key'];
          }
          $element['form_key'] = strtolower($element['form_key']);

          $elements_tree[] = $element;
          if (!empty($children[$element['cid']])) {
            $has_children = TRUE;
            // We have to continue with this parent later.
            $process_parents[] = $parent;
            // Use the current component as parent for the next iteration.
            $process_parents[] = $element['cid'];
            // Reset pointers for child lists because we step in there more
            // often with multi parents.
            reset($children[$element['cid']]);
            // Move pointer so that we get the correct term the next time.
            next($children[$parent]);
            break;
          }
        } while ($child = next($children[$parent]));

        if (!$has_children) {
          // We processed all components in this hierarchy-level.
          reset($children[$parent]);
        }
      }
    }

    $parent_element = &$build;
    // If form has multiple pages then start first page automatically.
    if ($multiPage) {
      $build['first_page'] = [
        '#type' => 'webform_wizard_page',
        '#title' => 'Start',
      ];
      $parent_element = &$build['first_page'];
    }

    foreach ($elements_tree as $element) {
      // If this is a multi-page form then indent all elements one level
      // to allow for page elements.
      if ($multiPage && $element['type'] != 'pagebreak') {
        $element['depth'] += 1;
      }
      $indent = str_repeat(' ', $element['depth'] * 2);
      $extra = unserialize($element['extra']);

      // Create an option list if there are items for this element.
      $options = [];
      $valid_options = [];
      if (!empty($extra['items'])) {
        $items = explode("\n", trim($extra['items']));
        foreach ($items as $item) {
          $item = trim($item);
          if (!empty($item)) {
            // Handle option groups.
            if (preg_match('/^<(.*)>$/', $item, $matches)) {
              if (empty(trim($matches[1]))) {
                continue;
              }
              $options[$matches[1]] = '';
            }
            else {
              $option = explode('|', $item);
              $valid_options[] = $option[0];
              if (count($option) == 2) {
                $options[$option[0]] = $option[1];
              }
              else {
                $options[$option[0]] = $option[0];
              }
            }
          }
        }
      }

      // Replace any tokens in the value.
      if (!empty($element['value'])) {
        $element['value'] = $this->replaceTokens($element['value']);
      }

      // Let's find out the parent for the given element.
      if (!empty($element['pid']) && !empty($elements[$element['pid']]['form_key'])) {
        $parent_key = $elements[$element['pid']]['form_key'];
        if (!empty($references[$parent_key])) {
          $parent_element = &$references[$parent_key];
        }
      }
      elseif ($multiPage && $element['type'] !== 'pagebreak') {
        // If previous item was a page, use it as parent element.
        // Otherwise, use previous parent.
        if (!empty($new_element['#type']) && $new_element['#type'] === 'webform_wizard_page') {
          $parent_element = &$new_element;
        }
      }
      else {
        $parent_element = &$build;
      }

      $form_key = $element['form_key'];
      $new_element = &$parent_element[$form_key];
      $references[$form_key] = &$new_element;
      switch ($element['type']) {
        case 'fieldset':
          $new_element = [
            '#type' => 'fieldset',
            '#open' => TRUE,
          ];
          if ($multiPage && $parent_element['#type'] === 'webform_wizard_page' && empty($parent_element['#title'])) {
            $parent_element['#title'] = $element['name'];
          }
          break;

        case 'textfield':
          $new_element['#type'] = 'textfield';
          if (!empty($extra['width'])) {
            $new_element['#size'] = (int) $extra['width'];
          }
          break;

        case 'textarea':
          $new_element['#type'] = 'textarea';
          break;

        case 'boolean':
          $new_element['#type'] = 'checkbox';
          break;

        case 'select':
          if (!empty($extra['aslist'])) {
            $select_type = 'select';
          }
          elseif (!empty($extra['multiple']) && count($valid_options) > 1) {
            $select_type = 'checkboxes';
          }
          elseif (!empty($extra['multiple']) && count($valid_options) == 1) {
            $select_type = 'checkbox';
          }
          else {
            $select_type = 'radios';
          }

          $new_element = [
            '#type' => $select_type,
            '#options' => $options,
          ];

          // If the component has the "other" option enabled via select_or_other
          // module, use the corresponding type with "other" support.
          if (!empty($extra['other_option'])) {
            // Type "checkboxes" but not "checkbox" has an "other" version.
            if ($new_element['#type'] === 'checkbox') {
              $new_element['#type'] = 'checkboxes';
            }
            // Rename type to corresponding type with "other" option.
            // Prefix 'webform_' is hidden in the UI.
            $new_element['#type'] = 'webform_' . $new_element['#type'] . '_other';
            // Add mandatory configuration.
            $new_element['#other__counter_minimum'] = 1;
            $new_element['#other__counter_maximum'] = 1;
            // Copy option label if one is configured.
            if (isset($extra['other_text']) && strlen($extra['other_text'])) {
              $new_element['#other__option_label'] = $extra['other_text'];
            }
          }

          if (!empty($extra['multiple'])) {
            $new_element['#multiple'] = TRUE;
          }
          break;

        case 'email':
          $new_element = [
            '#type' => 'email',
            '#size' => 20,
          ];
          break;

        case 'number':
          $extra['type'] ??= 'textfield';
          if ($extra['type'] == 'textfield') {
            $new_element = [
              '#type' => 'textfield',
              '#size' => 20,
            ];
          }
          elseif ($extra['type'] == 'select') {
            $min = $extra['min'];
            $max = $extra['max'];
            $step = !empty($extra['step']) ? $extra['step'] : 1;
            for ($value = $min; $value <= $max; $value += $step) {
              $select_options[] = $value;
            }
            $new_element = [
              '#type' => 'select',
              '#options' => $select_options,
            ];
          }
          foreach (['min', 'max', 'step'] as $property) {
            if (!empty($extra[$property])) {
              $new_element["#{$property}"] = $extra[$property];
            }
          }
          if (isset($extra['unique'])) {
            $new_element['#unique'] = (bool) $extra['unique'];
          }
          break;

        case 'markup':
          $new_element = [
            '#type' => 'processed_text',
            '#format' => 'full_html',
            '#text' => trim($element['value']),
          ];
          $element['value'] = '';
          break;

        case 'file':
        case 'multiple_file':
          $exts = '';
          if (!empty($extra['filtering']['types'])) {
            $types = $extra['filtering']['types'];
            if (!empty($extra['filtering']['addextensions'])) {
              $add_types = explode(',', $extra['filtering']['addextensions']);
              $types = array_unique(array_merge($types, array_map('trim', $add_types)));
            }
            $exts = implode(' ', $types);
          }

          $file_size = '';
          if (!empty($extra['filtering']['size'])) {
            // Get the string for the size. Will be something like "2 MB".
            $size = $extra['filtering']['size'];

            // Convert the string into an integer in bytes.
            $file_size_bytes = Bytes::toNumber($size);

            // Convert that to MB.
            $file_size = floor($file_size_bytes / 1024 / 1024);

            // Failsafe as Webform doesn't let you go less than 1MB.
            $file_size = ($file_size < 1) ? 1 : $file_size;
          }

          $new_element = [
            '#type' => 'managed_file',
            '#max_filesize' => $file_size,
            '#file_extensions' => $exts,
          ];

          if (!empty($extra['width'])) {
            $new_element['#size'] = $extra['width'];
          }
          if ($element['type'] == 'multiple_file') {
            $new_element['#multiple'] = TRUE;
          }
          break;

        case 'date':
          $new_element['#type'] = 'date';
          break;

        case 'time':
          $new_element['#type'] = 'webform_time';

          if (!empty($extra['hourformat'])) {
            if ($extra['hourformat'] == '12-hour') {
              $new_element['#time_format'] = 'g:i A';
            }
            elseif ($extra['hourformat'] == '24-hour') {
              $new_element['#time_format'] = 'H:i';
            }
          }

          if (!empty($extra['minuteincrements'])) {
            // Setting expects seconds not minutes.
            $step = (int) $extra['minuteincrements'] * 60;
            $new_element['#step'] = $step;
          }
          break;

        case 'hidden':
          $new_element['#type'] = 'hidden';
          break;

        case 'pagebreak':
          $new_element = [
            '#type' => 'webform_wizard_page',
            '#title' => $element['name'],
          ];
          break;

        case 'addressfield':
          $new_element['#type'] = 'webform_address';
          $new_element['#state_province__type'] = 'textfield';
          break;

        case 'grid':
          $questions = $this->getItemsArray($extra['questions']);
          $new_element['#type'] = 'webform_likert';
          $new_element['#questions'] = $questions;
          $new_element['#answers'] = $this->getItemsArray($extra['options']);
          break;

        default:
          // @todo We should make some notice if element type was not found.
          break;
      }

      // Continue passing element markup to make it less breaking change.
      if (!empty($element['type']) && is_string($element['type'])) {
        $element_markup = !empty($new_element) ? WebformYaml::encode($new_element) : '';
        $this->getModuleHandler()->alter('webform_migrate_d7_webform_element_' . $element['type'], $element_markup, $indent, $element);
        $new_element = WebformYaml::decode($element_markup);
      }

      // Add common fields.
      if (!empty(trim($element['value'])) && (empty($valid_options) || in_array($element['value'], $valid_options))) {
        $new_element['#default_value'] = trim($element['value']);
      }
      if (!empty($extra['field_prefix'])) {
        $new_element['#field_prefix'] = $extra['field_prefix'];
      }
      if (!empty($extra['field_suffix'])) {
        $new_element['#field_suffix'] = $extra['field_suffix'];
      }
      if (!empty($extra['title_display']) && $extra['title_display'] != 'before') {
        $title_display = $extra['title_display'];
        if ($title_display == 'none') {
          $title_display = 'invisible';
        }
        $new_element['#title_display'] = $title_display;
      }
      if (!in_array($element['type'], ['pagebreak', 'markup'])) {
        $new_element['#title'] = $element['name'];

        // The description key can be missing (since description is optional and
        // it isn't saved by Drupal 7 webform when it is left empty).
        if (!empty($extra['description'])) {
          $new_element['#description'] = $extra['description'];
        }
      }
      if (!empty($extra['private'])) {
        $new_element['#private'] = TRUE;
      }
      if (!empty($element['required'])) {
        $new_element['#required'] = TRUE;
      }
      if (!empty($extra['disabled'])) {
        $new_element['#disabled'] = TRUE;
      }

      // Attach conditionals as Drupal #states.
      if ($states = $this->buildConditionals($element, $elements)) {
        $new_element['#states'] = $states;
      }
    }

    $output = WebformYaml::encode($build);
    return ['elements' => $output, 'xref' => $xref];
  }

  /**
   * Build conditionals and translate them to states api in D8.
   */
  protected function buildConditionals($element, $elements) {
    $nid = $element['nid'];
    $cid = $element['cid'];
    $extra = unserialize($element['extra']);
    // Checkboxes : ':input[name="add_more_locations_24[yes]"]':
    $query = $this->select('webform_conditional', 'wc');
    $query->innerJoin('webform_conditional_actions', 'wca', 'wca.nid=wc.nid AND wca.rgid=wc.rgid');
    $query->innerJoin('webform_conditional_rules', 'wcr', 'wcr.nid=wca.nid AND wcr.rgid=wca.rgid');
    $query->fields('wc', [
      'nid',
      'rgid',
      'andor',
      'weight',
    ])
      ->fields('wca', [
        'aid',
        'target_type',
        'target',
        'invert',
        'action',
        'argument',
      ])
      ->fields('wcr', [
        'rid',
        'source_type',
        'source',
        'operator',
        'value',
      ]);
    $conditions = $query->condition('wc.nid', $nid)->condition('wca.target', $cid)->execute();
    $states = [];

    if (!empty($conditions)) {
      foreach ($conditions as $condition) {
        $unsupported_condition = FALSE;
        // Element states.
        switch ($condition['action']) {
          case 'show':
            $element_state = $condition['invert'] ? 'invisible' : 'visible';
            break;

          case 'require':
            $element_state = $condition['invert'] ? 'optional' : 'required';
            break;

          case 'set':
            // Nothing found in D8 :(.
            $unsupported_condition = TRUE;
            break;
        }
        // Condition states.
        $operator_value = $condition['value'];
        $depedent = $elements[$condition['source']];
        $depedent_extra = unserialize($depedent['extra']);
        $depedent_extra['items'] = !empty($depedent_extra['items']) ? explode("\n", $depedent_extra['items']) : [];
        $depedent_extra += [
          'aslist' => NULL,
          'multiple' => NULL,
        ];
        // Element condition must be an array in Drupal 8|9 Webform.
        $element_condition = [];

        switch ($condition['operator']) {
          case 'contains':
            $element_trigger = $condition['invert'] ? '!pattern' : 'pattern';
            $element_condition = ['value' => [$element_trigger => $operator_value]];
            // Specially handle the checkboxes.
            if ($depedent['type'] == 'select' && !$depedent_extra['aslist'] && $depedent_extra['multiple']) {
              $element_condition = ['checked' => !empty($condition['invert'])];
            }
            break;

          case 'equal':
            $element_condition = ['value' => $operator_value];
            if ($depedent['type'] == 'select' && !$depedent_extra['aslist'] && $depedent_extra['multiple']) {
              $element_condition = ['checked' => TRUE];
            }
            break;

          case 'not_equal':
            // There is no handler for this in D8 so we do the reverse.
            $element_state = $condition['invert'] ? 'visible' : 'invisible';
            $element_condition = ['value' => $operator_value];
            // Specially handle the checkboxes.
            if ($depedent['type'] == 'select' && !$depedent_extra['aslist'] && $depedent_extra['multiple']) {
              $element_condition = ['checked' => TRUE];
            }

            break;

          case 'less_than':
            $element_condition = ['value' => ['less' => $operator_value]];
            break;

          case 'less_than_equal':
            $element_condition = ['value' => ['less_equal' => $operator_value]];
            break;

          case 'greater_than':
            $element_condition = ['value' => ['greater' => $operator_value]];
            break;

          case 'greater_than_equal':
            $element_condition = ['value' => ['greater_equal' => $operator_value]];
            break;

          case 'empty':
            if ($operator_value == 'checked') {
              $element_condition = ['unchecked' => TRUE];
            }
            else {
              $element_condition = ['empty' => TRUE];
            }
            break;

          case 'not_empty':
            if ($operator_value == 'checked') {
              $element_condition = ['checked' => TRUE];
            }
            else {
              $element_condition = ['filled' => FALSE];
            }
            break;
        }

        if (!$depedent_extra['aslist'] && $depedent_extra['multiple'] && is_array($depedent_extra['items']) && count($depedent_extra['items']) > 1) {
          $depedent['form_key'] = $depedent['form_key'] . "[$operator_value]";
        }
        elseif (!$depedent_extra['aslist'] && !$depedent_extra['multiple'] && is_array($depedent_extra['items']) && count($depedent_extra['items']) == 1) {
          $depedent['form_key'] = $depedent['form_key'] . "[$operator_value]";
        }

        if (!$unsupported_condition) {
          $states[$element_state][] = [':input[name="' . strtolower($depedent['form_key']) . '"]' => $element_condition];
        }

      }
      if (empty($states)) {
        return FALSE;
      }
      return $states;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Build email handlers from webform emails table.
   */
  protected function buildEmailHandlers($nid, $xref) {

    $query = $this->select('webform_emails', 'we');
    $query->fields('we', [
      'nid',
      'eid',
      'status',
      'email',
      'subject',
      'from_name',
      'from_address',
      'template',
      'excluded_components',
      'html',
      'attachments',
    ]);
    $emails = $query->condition('nid', $nid)->execute();

    $handlers = [];
    foreach ($emails as $email) {
      $id = 'email_' . $email['eid'];
      foreach (['email', 'subject', 'from_name', 'from_address'] as $field) {
        if (!empty($email[$field]) && is_numeric($email[$field]) && !empty($xref[$email[$field]])) {
          $email[$field] = "[webform_submission:values:{$xref[$email[$field]]}:raw]";
        }
      }
      $excluded = [];
      if (!empty($email['excluded_components'])) {
        $excludes = explode(',', $email['excluded_components']);
        foreach ($excludes as $exclude) {
          if (!empty($xref[$exclude])) {
            $excluded[$xref[$exclude]] = $xref[$exclude];
          }
        }
      }
      // Default handler settings
      $handler_settings = [
        'to_mail' => str_replace('[submission:', '[webform_submission:', $email['email']),
        'html' => $email['html'],
        'attachments' => $email['attachments'],
        'excluded_elements' => $excluded,
      ];

      if ($email['from_address'] != 'default') {
        $handler_settings['from_mail'] = str_replace('[submission:', '[webform_submission:', $email['from_address']);
      }

      if ($email['from_name'] != 'default') {
        $handler_settings['from_name'] = str_replace('[submission:', '[webform_submission:', $email['from_name']);
      }

      if ($email['subject'] != 'default') {
        $handler_settings['subject'] = str_replace('[submission:', '[webform_submission:', $email['subject']);
      }

      if ($email['template'] != 'default') {
        $handler_settings['body'] = str_replace('[submission:', '[webform_submission:', $email['template']);
      }

      $handlers[$id] = [
        'id' => 'email',
        'label' => 'Email ' . $email['eid'],
        'handler_id' => $id,
        'status' => $email['status'],
        'weight' => $email['eid'],
        'settings' => $handler_settings,
      ];
    }
    return $handlers;
  }

  /**
   * Build access table from webform roles table.
   */
  protected function buildAccessTable($nid) {

    $query = $this->select('webform_roles', 'wr');
    $query->innerJoin('role', 'r', 'wr.rid=r.rid');
    $query->fields('wr', [
      'nid',
      'rid',
    ])
      ->fields('r', [
        'name',
      ]
    );
    $wf_roles = $query->condition('nid', $nid)->execute();

    $roles = [];
    // Handle rids 1 and 2 as per user_update_8002.
    $map = [
      1 => 'anonymous',
      2 => 'authenticated',
    ];
    foreach ($wf_roles as $role) {
      if (isset($map[$role['rid']])) {
        $roles[] = $map[$role['rid']];
      }
      else {
        $roles[] = str_replace(' ', '_', strtolower($role['name']));
      }
    }

    $access = [
      'create' => [
        'roles' => $roles,
        'users' => [],
        'permissions' => [],
      ],
    ];

    return $access;
  }

  /**
   * Translate webform tokens into regular tokens.
   *
   * %uid - The user id (unsafe)
   * %username - The name of the user if logged in.
   *                       Blank for anonymous users. (unsafe)
   * %useremail - The e-mail address of the user if logged in.
   *                       Blank for anonymous users. (unsafe)
   * %ip_address - The IP address of the user. (unsafe)
   * %site - The name of the site
   *             (i.e. Northland Pioneer College, Arizona) (safe)
   * %date - The current date, formatted according
   *              to the site settings.(safe)
   * %nid - The node ID. (safe)
   * %title - The node title. (safe)
   * %sid - The Submission id (unsafe)
   * %submission_url - The Submission url (unsafe)
   * %profile[key] - Any user profile field or value, such as %profile[name]
   *                         or %profile[profile_first_name] (unsafe)
   * %get[key] - Tokens may be populated from the URL by creating URLs of
   *                    the form http://example.com/my-form?foo=bar.
   *                    Using the token %get[foo] would print "bar". (safe)
   * %post[key] - Tokens may also be populated from POST values
   *                      that are submitted by forms. (safe)
   * %email[key] (unsafe)
   * %value[key] (unsafe)
   * %email_values (unsafe)
   * %cookie[key] (unsafe)
   * %session[key] (unsafe)
   * %request[key] (unsafe)
   * %server[key] (unsafe)
   *
   * Safe values are available to all users and unsafe values
   * should only be shown to authenticated users.
   */
  protected function replaceTokens($str) {
    return $str;
  }

  /**
   * {@inheritdoc}
   */
  protected function cleanString($str) {
    return str_replace(['"', "\n", "\r"], ["'", '\n', ''], $str);
  }

  /**
   * {@inheritdoc}
   */
  public function preRollback(MigrateRollbackEvent $event) {}

  /**
   * {@inheritdoc}
   */
  public function postRollback(MigrateRollbackEvent $event) {
    // Remove any Webform from webform if webform no longer exists.
    $webforms = $this->query()->execute();
    foreach ($webforms as $webform) {
      $webform_nid = $webform['nid'];
      $webform_id = 'webform_' . $webform_nid;
      $webform = Webform::load($webform_id);
      if (empty($webform)) {
        /** @var \Drupal\node\NodeInterface $node */
        $node = Node::load($webform_nid);
        if (!empty($node) && $node->getType() == 'webform') {
          if (!empty($node->webform->target_id) && $node->webform->target_id == $webform_id) {
            $node->webform->target_id = NULL;
            $node->save();
          }
        }
      }
    }
  }

  /**
   * @todo Add documentation.
   *
   * @param string $rawString
   *   @todo Add documentation.
   *
   * @return array
   *   @todo Add documentation.
   */
  protected function getItemsArray($rawString) {
    $items = explode("\n", $rawString);
    $items = array_map('trim', $items);
    return array_map(function ($item) {
      return explode('|', $item);
    }, $items);
  }

  /**
   * @todo Add documentation.
   *
   * @param array $itemsArray
   *   @todo Add documentation.
   * @param string $baseIndent
   *   @todo Add documentation.
   *
   * @return string
   *   @todo Add documentation.
   */
  protected function buildItemsString(array $itemsArray, $baseIndent = '') {
    $preparedItems = array_map(function ($item) use ($baseIndent) {
      return $baseIndent . '  ' . $this->encapsulateString($item[0]) . ': ' . $this->encapsulateString($item[1]);
    }, $itemsArray);

    return implode("\n", $preparedItems);
  }

  /**
   * @todo Add documentation.
   *
   * @param string $string
   *   @todo Add documentation.
   *
   * @return string
   *   @todo Add documentation.
   */
  protected function encapsulateString($string) {
    return sprintf("'%s'", addslashes($string));
  }

}
