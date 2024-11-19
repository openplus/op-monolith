/**
 * @file
 * CKEditor CodeMirror plugin admin behavior.
 */

(($, Drupal) => {
  /**
   * Provides the summary for the CodeMirror plugin settings vertical tab.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches summary behavior to the CodeMirror settings vertical tab.
   */

  Drupal.behaviors.ckeditorCodeMirrorSettingsSummary = {
    attach(context) {
      const $context = $(context);
      $context
        .find('[data-ckeditor5-plugin-id="ckeditor_codemirror_source_editing"]')
        .drupalSetSummary((summaryContext) => {
          const $summaryContext = $(summaryContext);
          const mode = $summaryContext.find(
            'select[name="editor[settings][plugins][ckeditor_codemirror_source_editing][mode]"]',
          );

          if (
            $summaryContext.find(
              'input[name="editor[settings][plugins][ckeditor_codemirror_source_editing][enable]"]:checked',
            ).length === 0
          ) {
            return Drupal.t('Syntax highlighting <strong>disabled</strong>.');
          }

          let output = '';
          output += Drupal.t('Syntax highlighting <strong>enabled</strong>.');

          if (mode.length) {
            const { selectedIndex } = mode[0];
            const modeName = mode[0].options[selectedIndex].label ?? 'Unknown';
            output += `<br />${Drupal.t('Mode: ')}${modeName}`;
          }

          return output;
        });
    },
  };
})(jQuery, Drupal);
