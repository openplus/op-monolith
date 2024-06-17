<?php

namespace Drupal\footnotes\Plugin\Filter;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a base filter for Footnotes filter.
 *
 * @Filter(
 *   id = "filter_footnotes",
 *   module = "footnotes",
 *   title = @Translation("Footnotes filter"),
 *   description = @Translation("You can insert footnotes directly into texts."),
 *   type = \Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 *   cache = FALSE,
 *   settings = {
 *     "footnotes_collapse" = FALSE,
 *     "footnotes_html" = FALSE,
 *     "footnotes_css" = TRUE
 *   },
 *   weight = 0
 * )
 */
class FootnotesFilter extends FilterBase {

  use StringTranslationTrait;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Variable to store total number of instances for each reference link.
   *
   * @var array
   */
  protected $instanceArray;

  /**
   * Constructs a FootnotesFilter object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = \Drupal::service('renderer');
  }

  /**
   * Get the tips for the filter.
   *
   * @param bool $long
   *   If get the long or short tip.
   *
   * @return string
   *   The tip to show for the user.
   */
  public function tips($long = FALSE) {
    if ($long) {
      return $this->t('You can insert footnotes directly into texts with <code>[fn]This text becomes a footnote.[/fn]</code>. This will be replaced with a running number (the footnote reference) and the text within the [fn] tags will be moved to the bottom of the page (the footnote). See <a href=":link">Footnotes Readme page</a> for additional usage options.', [':link' => 'http://drupal.org/project/footnotes">']);
    }
    else {
      return $this->t('Use [fn]...[/fn] (or &lt;fn&gt;...&lt;/fn&gt;) to insert automatically numbered footnotes.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    // Supporting both [fn] and <fn> now. Thanks to fletchgqc
    // http://drupal.org/node/268026.
    // Convert all square brackets to angle brackets. This way all further code
    // just manipulates angle brackets. (Angle brackets are preferred here for
    // the simple reason that square brackets are more tedious to use in
    // regexps).
    if (is_array($text)) {
      implode($text);
    }
    $text = preg_replace('|\[fn([^\]]*)\]|', '<fn$1>', $text);
    $text = preg_replace('|\[/fn\]|', '</fn>', $text);
    $text = preg_replace('|\[footnotes([^\]]*)\]|', '<footnotes$1>', $text);

    // Check that there are an even number of open and closing tags.
    // If there is one closing tag missing, append this to the end.
    // If there is more disparity, throw a warning and continue.
    // A closing tag may sometimes be missing when we are processing a teaser
    // and it has been cut in the middle of the footnote.
    // See http://drupal.org/node/253326
    $foo = [];
    $open_tags = preg_match_all("|<fn([^>]*)>|", $text, $foo);
    $close_tags = preg_match_all("|</fn>|", $text, $foo);

    if ($open_tags == $close_tags + 1) {
      $text = $text . '</fn>';
    }
    elseif ($open_tags > $close_tags + 1) {
      trigger_error($this->t("You have unclosed fn tags. This is invalid and will
        produce unpredictable results."));
    }

    // Set instanceArray so links can be aware of the number of instances.
    $this->instanceArray = $this->getLinkInstances($text);

    // Before doing the replacement, the callback function needs to know which
    // options to use.
    $this->replaceCallback($this->settings, 'prepare');

    $pattern = '|<fn([^>]*)>(.*?)</fn>|s';
    $text = preg_replace_callback($pattern, [
      $this,
      'replaceCallback',
    ], $text);

    // Replace tag <footnotes> with the list of footnotes.
    // If tag is not present, by default add the footnotes at the end.
    // Thanks to acp on drupal.org for this idea. see
    // http://drupal.org/node/87226.
    $footer = $this->replaceCallback(NULL, 'output footer');
    $pattern = '|(<footnotes([\ \/]*)>)|';
    if (preg_match($pattern, $text) > 0) {
      $text = preg_replace($pattern, $footer, $text, 1);
    }
    elseif (!empty($footer)) {
      $text .= "\n\n" . $footer;
    }
    $result = new FilterProcessResult($text);

    // Only use CSS if option is selected.
    if (!isset($this->settings['footnotes_css']) || $this->settings['footnotes_css']) {
      $result->setAttachments([
        'library' => [
          'footnotes/footnotes',
        ],
      ]);
    }

    return $result;
  }

  /**
   * Helper function called from preg_replace_callback() above.
   *
   * Uses static vars to temporarily store footnotes found.
   * This is not threadsafe, but PHP isn't.
   *
   * @param mixed $matches
   *   Elements from array:
   *   - 0: complete matched string.
   *   - 1: tag name.
   *   - 2: tag attributes.
   *   - 3: tag content.
   * @param string $op
   *   Operation.
   *
   * @return string
   *   Return the string processed by geshi library.
   */
  protected function replaceCallback($matches, $op = '') {
    static $opt_collapse = 0;
    static $opt_html = 0;
    static $n = 0;
    static $store_matches = [];
    static $used_values = [];
    $str = '';

    if ($op == 'prepare') {
      // In the 'prepare' case, the first argument contains the options to use.
      // The name 'matches' is incorrect, we just use the variable anyway.
      $opt_collapse = $matches['footnotes_collapse'];
      $opt_html = $matches['footnotes_html'];
      return 0;
    }

    if ($op == 'output footer') {
      if (count($store_matches) > 0) {
        // Only if there are stored fn matches, pass the array of fns to be
        // themed as a list Drupal 7 requires we use "render element" which
        // just introduces a wrapper around the old array.
        // @FIXME
        // theme() has been renamed to _theme() and should NEVER be called
        // directly. Calling _theme() directly can alter the expected output and
        // potentially introduce  security issues
        // (see https://www.drupal.org/node/2195739). You should use renderable
        // arrays instead. @see https://www.drupal.org/node/2195739
        $markup = [
          '#theme' => 'footnote_list',
          '#footnotes' => $store_matches,
        ];
        $str = $this->renderer->render($markup);
      }
      // Reset the static variables so they can be used again next time.
      $n = 0;
      $store_matches = [];
      $used_values = [];

      return $str;
    }

    // Default op: act as called by preg_replace_callback()
    // Random string used to ensure footnote id's are unique, even
    // when contents of multiple nodes reside on same page.
    // (fixes http://drupal.org/node/194558).
    $randstr = $this->randstr();

    $children_text = '';
    // Did the pattern match anything in the <fn> tag?
    if ($matches[1]) {
      // See if value attribute can parsed, either well-formed in quotes eg
      // <fn value="3">.
      if (preg_match('|value=["\'](.*?)["\']|', $matches[1], $value_match)) {
        $value = $value_match[1];
        // Or without quotes eg <fn value=8>.
      }
      elseif (preg_match('|value=(\S*)|', $matches[1], $value_match)) {
        $value = $value_match[1];
      }

      // Try to match the text attribute as well, in case children are stored
      // in the attribute.
      if (preg_match('|text=["\'](.*?)["\']|', $matches[1], $text_match)) {
        $children_text = $text_match[1];
      }
    }

    if (isset($value)) {
      // A value label was found. If it is numeric, record it in $n so further
      // notes can increment from there.
      // After adding support for multiple references to same footnote in the
      // body (http://drupal.org/node/636808) also must check that $n is
      // monotonously increasing.
      if (is_numeric($value) && $n < $value) {
        $n = $value;
      }
    }
    elseif ($opt_collapse and $value_existing = $this->findFootnote($matches[2], $store_matches)) {
      // An identical footnote already exists. Set value to the previously
      // existing value.
      $value = $value_existing;
    }
    else {
      // No value label, either a plain <fn> or unparsable attributes. Increment
      // the footnote counter, set label equal to it.
      $n++;
      $value = $n;
    }

    // Remove illegal characters from $value so it can be used as an HTML id
    // attribute.
    $value_id = preg_replace('|[^\w\-]|', '', $value);

    // Decide whether to use the attribute or the child content for text.
    $text = $children_text ? $children_text : $matches[2];

    // Create a sanitized version of $text that is suitable for using as HTML
    // attribute value. (In particular, as the title attribute to the footnote
    // link).
    $allowed_tags = [];
    $text_clean = Xss::filter($text, $allowed_tags);
    // HTML attribute cannot contain quotes.
    $text_clean = str_replace('"', "&quot;", $text_clean);
    // Remove newlines. Browsers don't support them anyway and they'll confuse
    // line break converter in filter.module.
    $text_clean = str_replace("\n", " ", $text_clean);
    $text_clean = str_replace("\r", "", $text_clean);

    $instance = 1;

    // If a link has more then 1 instance.
    if ($this->instanceArray[$value] > 1) {
      // If we have stored matches, use these to calculate the current instance.
      foreach ($store_matches as $match) {
        if ($match['value'] == $value) {
          if (is_array($match['ref_id'])) {
            // Add one because current ref_id is not in $store_matches yet.
            $instance = count($match['ref_id']) + 1;
          }
          else {
            // The ref_id is not an array at this point so set to 2.
            $instance = 2;
          }
        }
      }
    }

    // Create a footnote item as an array.
    $fn = [
      'value' => $value,
      'text' => $opt_html ? html_entity_decode($text) : $text,
      'text_clean' => $text_clean,
      'fn_id' => 'footnote' . $value_id . '_' . $randstr,
      'ref_id' => 'footnoteref' . $value_id . '_' . $randstr,
      'instances' => $this->instanceArray[$value],
      'instance' => $instance,
    ];

    // We now allow to repeat the footnote value label, in which case the link
    // to the previously existing footnote is returned. Content of the current
    // footnote is ignored. See http://drupal.org/node/636808 .
    if (!in_array($value, $used_values)) {
      // This is the normal case, add the footnote to $store_matches.
      // Store the footnote item.
      array_push($store_matches, $fn);
      array_push($used_values, $value);
    }
    else {
      // A footnote with the same label already exists.
      // Use the text and id from the first footnote with this value.
      // Any text in this footnote is discarded.
      $i = array_search($value, $used_values);
      $fn['text'] = $store_matches[$i]['text'];
      $fn['text_clean'] = $store_matches[$i]['text_clean'];
      $fn['fn_id'] = $store_matches[$i]['fn_id'];
      // Push the new ref_id into the first occurrence of this footnote label
      // The stored footnote thus holds a list of ref_id's rather than just one
      // id.
      $ref_array = is_array($store_matches[$i]['ref_id']) ? $store_matches[$i]['ref_id'] : [$store_matches[$i]['ref_id']];
      array_push($ref_array, $fn['ref_id']);
      $store_matches[$i]['ref_id'] = $ref_array;
    }

    // Return the item themed into a footnote link.
    // Drupal 7 requires we use "render element" which just introduces a wrapper
    // around the old array.
    $fn = [
      '#theme' => 'footnote_link',
      '#fn' => $fn,
    ];
    $result = $this->renderer->render($fn);

    return $result;
  }

  /**
   * Helper function to return a random text string.
   *
   * @return string
   *   Random (lowercase) alphanumeric string.
   */
  public function randstr() {
    $chars = "abcdefghijklmnopqrstuwxyz1234567890";
    $str = "";

    // Seeding with srand() not necessary in modern PHP versions.
    for ($i = 0; $i < 7; $i++) {
      $n = rand(0, strlen($chars) - 1);
      $str .= substr($chars, $n, 1);
    }
    return $str;
  }

  /**
   * Helper function to set $instanceArray variable.
   *
   * @param string $text
   *   The text being processed.
   *
   * @return array
   *   Array containing number of link value instances.
   */
  public function getLinkInstances($text) {
    $instances = [];
    $pattern = '|<fn([^>]*)>(.*?)</fn>|s';

    preg_match_all(
      $pattern,
      $text,
      $matches,
      PREG_PATTERN_ORDER
    );

    foreach ($matches[1] as $value_string) {
      preg_match('/(?<=\")(.*?)(?=\")/', $value_string, $value);

      if (empty($instances[$value[0]])) {
        $instances[$value[0]] = 1;
      }
      else {
        $instances[$value[0]]++;
      }
    }

    return $instances;
  }

  /**
   * Create the settings form for the filter.
   *
   * @param array $form
   *   A minimally prepopulated form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the (entire) configuration form.
   *
   * @return array
   *   The $form array with additional form elements for the settings of
   *   this filter. The submitted form values should match $this->settings.
   *
   * @todo Add validation of submited form values, it already exists for
   *       drupal 7, must update it only.
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $settings['footnotes_collapse'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Collapse footnotes with identical content'),
      '#default_value' => $this->settings['footnotes_collapse'],
      '#description' => $this->t('If two footnotes have the exact same content, they will be collapsed into one as if using the same value="" attribute.'),
    ];
    $settings['footnotes_html'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Handle footnote text as HTML'),
      '#default_value' => $this->settings['footnotes_html'],
      '#description' => $this->t('If not checked, a HTML tag in the footnote text will be shown as-is to the user.'),
    ];
    $settings['footnotes_css'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use footnotes CSS'),
      '#default_value' => isset($this->settings['footnotes_css']) ? $this->settings['footnotes_css'] : TRUE,
      '#description' => $this->t('Uncheck this option to remove footnotes CSS.'),
    ];
    return $settings;
  }

  /**
   * Search the $store_matches array for footnote text that matches.
   *
   * Note: This does a linear search on the $store_matches array. For a large
   * list of footnotes it would be more efficient to maintain a separate array
   * with the footnote content as key, in order to do a hash lookup at this
   * stage. Since you typically only have a handful of footnotes, this simple
   * search is assumed to be more efficient, but was not tested.
   *
   * @param string $text
   *   The footnote text.
   * @param array $store_matches
   *   The matches array.
   *
   * @return string|false
   *   The value of the existing footnote, FALSE otherwise.
   */
  private function findFootnote($text, array &$store_matches) {
    if (!empty($store_matches)) {
      foreach ($store_matches as &$fn) {
        if ($fn['text'] == $text) {
          return $fn['value'];
        }
      }
    }
    return FALSE;
  }

}
