<?php

namespace Drupal\footnotes\Plugin\Filter;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Random;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\footnotes\FootnotesDialog;
use Drupal\footnotes\FootnotesGroup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter for Footnotes plugin.
 *
 * @Filter(
 *   id = "filter_footnotes",
 *   module = "footnotes",
 *   title = @Translation("Footnotes filter"),
 *   description = @Translation("Converts the footnotes inserted within the Footnotes window into footnotes."),
 *   type = \Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 *   cache = FALSE,
 *   settings = {
 *     "footnotes_collapse" = FALSE,
 *     "footnotes_css" = TRUE,
 *     "footnotes_dialog" = FALSE,
 *     "footnotes_footer_disable" = FALSE,
 *   },
 *   weight = 0
 * )
 */
class FootnotesFilter extends FilterBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The counter for auto-incrementing footnotes.
   *
   * @var int
   */
  protected int $counter = 0;

  /**
   * Variable to store total number of instances for each reference link.
   *
   * @var array
   */
  protected array $storedFootnotes = [];

  /**
   * Constructs a MediaEmbed object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\footnotes\FootnotesGroup $footnotesGroup
   *   The footnotes group service.
   * @param \Drupal\footnotes\FootnotesDialog $footnotesDialog
   *   The footnotes dialog service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected RendererInterface $renderer,
    protected FootnotesGroup $footnotesGroup,
    protected FootnotesDialog $footnotesDialog,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer'),
      $container->get('footnotes.group'),
      $container->get('footnotes.dialog')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    if ($this->settings['footnotes_footer_disable']) {
      $this->counter = $this->footnotesGroup->count();
    }
    $result = new FilterProcessResult($text);

    // Bail early if we have no instances of footnotes.
    if (stristr($text, '<footnotes') === FALSE) {
      return $result;
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);
    $nodes_to_remove = [];

    // Process the footnotes source placeholders.
    foreach ($xpath->query('//footnotes') as $node) {
      /** @var \DOMElement $node */
      $footnote_builds = [];
      $node_hash = $this->getHash($node);

      // Build the first footnote if this is not a sibling of
      // a preceding footnote. If it is a sibling it would have
      // been already handled in the while loop below and we
      // should therefore just remove it (leaving markup empty).
      if (!in_array($node_hash, $nodes_to_remove)) {
        $footnote_builds[] = $this->buildFootnoteInstance($node);

        // Determine if there are subsequent footnotes directly adjacent.
        // Keep going until there are no more direct next siblings that
        // are footnotes.
        /** @var \DOMElement $node */
        $next_footnote_sibling = $this->getNextFootnoteSibling($node);
        while ($next_footnote_sibling) {
          $footnote_builds[] = $this->buildFootnoteInstance($next_footnote_sibling);

          // Store the node to remove as a hash like Redirect does, used
          // for quick look up.
          $nodes_to_remove[] = $this->getHash($next_footnote_sibling);
          $next_footnote_sibling = $this->getNextFootnoteSibling($next_footnote_sibling);
        }
      }

      $build = [
        '#theme' => 'footnote_links',
        '#footnote_links' => $footnote_builds,
      ];
      $markup = $this->renderer->render($build);

      // Citations should not contain paragraph tags as they should be inline
      // content. This attempts to remove them; however, if the 'Convert line
      // breaks filter' runs after this filter, it will still be an issue.
      $markup = str_replace(['<p>', '</p>'], '', $markup);
      static::replaceNodeContent($node, $markup);
    }

    // Save the updated text.
    $processed_text = Html::serialize($dom);

    // Output or store the footnotes.
    if ($this->settings['footnotes_footer_disable']) {

      // Store the footnotes in the footnotes group so the site builder
      // can output them elsewhere.
      $this->footnotesGroup->add($this->storedFootnotes);
    }
    else {

      // Process the footnotes target output. If there is a placeholder
      // for the output, use that, otherwise append a new placeholder
      // at the end of the formatted text.
      $build = $this->buildFootnoteTexts();
      $markup = $this->renderer->render($build);
      if (stristr($text, '<footnotes-placeholder>') === FALSE) {
        $processed_text .= $markup;
      }
      else {
        $processed_text = str_replace('<footnotes-placeholder>', $markup, $processed_text);
      }
    }
    $result->setProcessedText($processed_text);

    // Only use CSS if option is selected.
    if (!isset($this->settings['footnotes_css']) || $this->settings['footnotes_css']) {
      $result->addAttachments([
        'library' => [
          'footnotes/footnotes',
        ],
      ]);
    }

    // Only use dialog if option is select.
    if (isset($this->settings['footnotes_dialog']) && $this->settings['footnotes_dialog']) {
      $result->addAttachments([
        'library' => [
          'footnotes/footnotes.dialog',
        ],
      ]);

      // Add the dialog wrapper twig (footnote-dialog.html.twig).
      $html = $result->getProcessedText();
      $build = [
        '#theme' => 'footnote_dialog',
      ];
      $html .= $this->renderer->render($build);
      $result->setProcessedText($html);

      // Mark as outputted so the dialog is not output more than once
      // in case there are multiple reference sections.
      $this->footnotesDialog->setOutputted();
    }

    return $result;
  }

  /**
   * Get a unique hash of a DOMNode.
   *
   * @param \DOMElement $node
   *   The dom element for each <footnote>.
   *
   * @return string
   *   The hash.
   */
  protected function getHash(\DOMElement $node): string {
    $citation_content = [
      'text' => $node->textContent,
      'data-text' => $node->attributes->getNamedItem('data-text')->textContent ?? NULL,
      'data-value' => $node->attributes->getNamedItem('data-value')->textContent ?? NULL,
    ];
    $string = Json::encode($citation_content);
    return Crypt::hashBase64($string);
  }

  /**
   * Find footnotes citations immediately following each other.
   *
   * Allow for spaces in between.
   *
   * @param \DOMElement $node
   *   The node to find a subsequent footnote sibling for.
   *
   * @return \DOMElement|null
   *   The sibling or null.
   */
  protected function getNextFootnoteSibling(\DOMElement $node): \DOMElement|NULL {
    if (is_null($node->nextSibling)) {
      return NULL;
    }

    // If the next sibling is footnotes, return that.
    if (
      $node->nextSibling instanceof \DOMElement
      && $node->nextSibling->nodeName == 'footnotes'
    ) {
      return $node->nextSibling;
    }

    // If the next sibling is an empty space, check
    // if the subsequent sibling is a footnote.
    if (

      // Allowing for different variations of spaces to be
      // ignored.
      $node->nextSibling->nodeName == '#text'
      && (
        trim($node->nextSibling->textContent) == "\u{A0}"
        || trim($node->nextSibling->textContent) == "\n"
        || trim($node->nextSibling->textContent) == ''
      )

      && !is_null($node->nextSibling->nextSibling)
      && $node->nextSibling->nextSibling instanceof \DOMElement
      && $node->nextSibling->nextSibling->nodeName == 'footnotes'
    ) {
      return $node->nextSibling->nextSibling;
    }

    return NULL;
  }

  /**
   * Build the footnote instance.
   *
   * This does two things:
   * - Builds the render array for the source link within the text.
   * - Adds the text for the target footnote into the instance array.
   *
   * @param \DOMElement $node
   *   The dom element for each <footnote>.
   *
   * @return array
   *   The render array for the source.
   */
  protected function buildFootnoteInstance(\DOMElement $node): array {
    $text = $node->getAttribute('data-text');

    // Support adding the footnote reference content within
    // the footnotes tag as well, for those not using CK Editor.
    // For example:
    // <footnotes data-value="">Test <b>reference</b></footnotes>.
    if (empty($text)) {
      $document = $node->ownerDocument;
      foreach ($node->childNodes as $childNode) {
        $text .= $document->saveHTML($childNode);
      }
    }
    $value = $node->getAttribute('data-value');

    // Delete the consumed attributes.
    $node->removeAttribute('data-value');
    $node->removeAttribute('data-text');

    // Clean the value if its not just a number.
    $key = $value;
    if (!empty($value) && !is_numeric($value)) {
      $value = Html::cleanCssIdentifier($value);
    }

    // Automatically increment values when empty.
    $is_auto = FALSE;
    if (empty($value)) {
      $is_auto = TRUE;

      // For automated values, automatically combine footnote targets together.
      // Check if we already have an exact match for this text if
      // we are meant to collapse.
      if ($this->settings['footnotes_collapse']) {
        $key = $this->getMatchingFootnoteKey($text);
      }

      // If no exact match, set an auto-incremented number.
      if ($key) {
        $value = $key;
      }
      else {
        $this->counter++;
        $key = $value = $this->counter;
      }
    }

    // Build the render array for the footnote.
    $random = new Random();
    $random_string = $random->name(12);
    $text_string = Crypt::hashBase64($text);
    $text_string = str_replace('_', '', $text_string);
    $footnote = [
      'value' => $value,
      'backlink_value' => $value,
      'text' => $text,
      'text_clean' => PlainTextOutput::renderFromHtml($text),
      'fn_id' => 'footnote' . $value . '_' . $text_string . '_' . $random_string,
      'ref_id' => 'footnoteref' . $value . '_' . $text_string . '_' . $random_string,
      'instance' => 1,
      'is_auto' => $is_auto,
      'is_same_text' => TRUE,
    ];

    // Record the target text.
    $this->storedFootnotes[$key][$footnote['fn_id']] = $footnote;

    // If there are multiple stored footnotes, set the backlink value.
    if (count($this->storedFootnotes[$key]) > 1) {
      $alphabet = range('a', 'z');
      $counter = 0;
      foreach ($this->storedFootnotes[$key] as &$stored_footnote) {
        $stored_footnote['backlink_value'] = $stored_footnote['value'] . $alphabet[$counter];
        $counter++;
      }

      // Check if all texts are the same, we render differently if not.
      $texts = array_column($this->storedFootnotes[$key], 'text');
      $texts = array_unique($texts);
      if (count($texts) > 1) {
        foreach ($this->storedFootnotes[$key] as &$stored_footnote) {
          $stored_footnote['is_same_text'] = FALSE;

          // Backlink value is no longer necessary to be unique.
          $stored_footnote['backlink_value'] = $stored_footnote['value'];
        }
      }
    }
    $footnote['instances'] = $this->storedFootnotes[$key];

    return [
      '#theme' => 'footnote_link',
      '#fn' => $footnote,
    ];
  }

  /**
   * Find matching footnote text and return the value for that.
   *
   * This is so that duplicate texts can have the same number
   * for example: my link[1] and same link again[1].
   *
   * @param string $text
   *   The text to search for.
   *
   * @return string
   *   The key.
   */
  protected function getMatchingFootnoteKey(string $text): string {
    foreach ($this->storedFootnotes as $key => $footnotes) {
      foreach ($footnotes as $footnote) {
        if ($text == $footnote['text']) {
          return $key;
        }
      }
    }
    return '';
  }

  /**
   * Build the footnote texts.
   *
   * This takes the target texts and builds an unordered list of them.
   *
   * @return array
   *   The render array for the source.
   */
  protected function buildFootnoteTexts(): array {

    // Build the render array for the footnote.
    return [
      '#theme' => 'footnote_list',
      '#footnotes' => $this->storedFootnotes,
      '#is_block' => FALSE,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $settings['footnotes_collapse'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Collapse footnotes with identical content'),
      '#default_value' => $this->settings['footnotes_collapse'] ?? FALSE,
      '#description' => $this->t('If two footnotes have the exact same content and the value is left to auto-increment, the duplicate content will use the first value. For example "Here is some content[1], a different bit of content[2], and finally repeat of the first content[1]".'),
    ];
    $settings['footnotes_css'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use footnotes CSS'),
      '#default_value' => $this->settings['footnotes_css'] ?? TRUE,
      '#description' => $this->t('Uncheck this option to remove footnotes CSS.'),
    ];
    $settings['footnotes_dialog'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open footnote references in a dialog'),
      '#default_value' => $this->settings['footnotes_dialog'] ?? FALSE,
      '#description' => $this->t('Instead of jumping the visitor to the references section on citation click, open the reference content in a dialog.'),
    ];
    $settings['footnotes_footer_disable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable output of the footnotes footer'),
      '#default_value' => $this->settings['footnotes_footer_disable'] ?? FALSE,
      '#description' => $this->t("If disabled, the footnotes will be grouped together. They can be output using the Footnotes Group block, or anywhere using Twig Tweak {{ drupal_block('footnotes_group') }}. Note that you must clear the cache in order for changes to this setting to take effect."),
    ];
    return $settings;
  }

  /**
   * Duplicate of MediaEmbed::replaceNodeContent().
   *
   * This is to avoid having Media as a dependency.
   *
   * @param \DOMNode $node
   *   A DOMNode object.
   * @param string $content
   *   The text or HTML that will replace the contents of $node.
   */
  protected static function replaceNodeContent(\DOMNode &$node, $content) {

    // Remove any extra space around the citation caused by Twig
    // theme debugging. Citations should be inline content (comparatively
    // references should allow block-level elements).
    $content = trim($content);
    if (strlen($content)) {
      // Load the content into a new DOMDocument and retrieve the DOM nodes.
      $replacement_nodes = Html::load($content)->getElementsByTagName('body')
        ->item(0)
        ->childNodes;
    }
    else {
      $replacement_nodes = [$node->ownerDocument->createTextNode('')];
    }

    foreach ($replacement_nodes as $replacement_node) {
      // Import the replacement node from the new DOMDocument into the original
      // one, importing also the child nodes of the replacement node.
      $replacement_node = $node->ownerDocument->importNode($replacement_node, TRUE);
      $node->parentNode->insertBefore($replacement_node, $node);
    }
    $node->parentNode->removeChild($node);
  }

}
