import { InputView } from 'ckeditor5/src/ui';

export default class FootnotesTextAreaView extends InputView {
  /**
   * Creates an instance of the footnotes text area view.
   *
   * Though this is not technically an input element, it behaves more or less the same aside from the tag.
   *
   * @param {module:utils/locale~Locale} locale The {@link module:core/editor/editor~Editor#locale} instance.
   */
  constructor( locale) {
    super( locale );

    this.template.tag = 'textarea';
  }
}
