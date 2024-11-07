import { Plugin } from 'ckeditor5/src/core';
import { toWidget, toWidgetEditable } from 'ckeditor5/src/widget';
import { Widget } from 'ckeditor5/src/widget';
import InsertDetailCommand from './insertdetailcommand';

// cSpell:ignore detail insertdetailcommand

/**
 * CKEditor 5 plugins do not work directly with the DOM. They are defined as
 * plugin-specific data models that are then converted to markup that
 * is inserted in the DOM.
 *
 * CKEditor 5 internally interacts with detail as this model:
 * <detail>
 *    <detailSummary></detailSummary>
 *    <detailWrapper></detailWrapper>
 * </detail>
 *
 * Which is converted for the browser/user as this markup
 * <details>
 *   <summary></summary>
 *   <div class="details-wrapper"></div>
 * </section>
 *
 * This file has the logic for defining the detail model, and for how it is
 * converted to standard DOM markup.
 */
export default class DetailEditing extends Plugin {
  static get requires() {
    return [Widget];
  }

  init() {
    this._defineSchema();
    this._defineConverters();
    this.editor.commands.add(
      'insertDetail',
      new InsertDetailCommand(this.editor),
    );
    this._addEventListeners();
  }

  /*
   * This registers the structure that will be seen by CKEditor 5 as
   * <detail>
   *    <detailSummary></detailSummary>
   *    <detailWrapper></detailWrapper>
   * </detail>
   *
   * The logic in _defineConverters() will determine how this is converted to
   * markup.
   */
  _defineSchema() {
    // Schemas are registered via the central `editor` object.
    const schema = this.editor.model.schema;

    schema.register('detail', {
      // Behaves like a self-contained object (e.g. an image).
      isObject: true,
      // Allow in places where other blocks are allowed (e.g. directly in the root).
      allowWhere: '$block',
    });

    schema.register('detailSummary', {
      // This creates a boundary for external actions such as clicking and
      // and keypress. For example, when the cursor is inside this box, the
      // keyboard shortcut for "select all" will be limited to the contents of
      // the box.
      isLimit: true,
      // This is only to be used within detail.
      allowIn: 'detail',
      // Allow content that is allowed in blocks (e.g. text with attributes).
      allowContentOf: '$block',
    });

    schema.register('detailWrapper', {
      isLimit: true,
      allowIn: 'detail',
      allowContentOf: '$root',
    });
  }

  /**
   * Converters determine how CKEditor 5 models are converted into markup and
   * vice-versa.
   */
  _defineConverters() {
    // Converters are registered via the central editor object.
    const { conversion } = this.editor;

    // Upcast Converters: determine how existing HTML is interpreted by the
    // editor. These trigger when an editor instance loads.
    //
    // If <details> is present in the existing markup
    // processed by CKEditor, then CKEditor recognizes and loads it as a
    // <detail> model.
    conversion.for('upcast').elementToElement({
      model: 'detail',
      view: {
        name: 'details',
      },
    });

    // If <summary> is present in the existing markup
    // processed by CKEditor, then CKEditor recognizes and loads it as a
    // <detailSummary> model, provided it is a child element of <detail>,
    // as required by the schema.
    conversion.for('upcast').elementToElement({
      model: 'detailSummary',
      view: {
        name: 'summary',
      },
    });

    // If <div class="details-wrapper"> is present in the existing markup
    // processed by CKEditor, then CKEditor recognizes and loads it as a
    // <detailWrapper> model, provided it is a child element of
    // <detail>, as required by the schema.
    conversion.for('upcast').elementToElement({
      model: 'detailWrapper',
      view: {
        name: 'div',
        classes: 'details-wrapper',
      },
    });

    // Data Downcast Converters: converts stored model data into HTML.
    // These trigger when content is saved.
    //
    // Instances of <detail> are saved as
    // <details>{{inner content}}</details>.
    conversion.for('dataDowncast').elementToElement({
      model: 'detail',
      view: {
        name: 'details',
      },
    });

    // Instances of <detailSummary> are saved as
    // <summary>{{inner content}}</summary>.
    conversion.for('dataDowncast').elementToElement({
      model: 'detailSummary',
      view: {
        name: 'summary',
      },
    });

    // Instances of <detailWrapper> are saved as
    // <div class="details-wrapper">{{inner content}}</div>.
    conversion.for('dataDowncast').elementToElement({
      model: 'detailWrapper',
      view: {
        name: 'div',
        classes: 'details-wrapper',
      },
    });

    // Editing Downcast Converters. These render the content to the user for
    // editing, i.e. this determines what gets seen in the editor. These trigger
    // after the Data Upcast Converters, and are re-triggered any time there
    // are changes to any of the models' properties.
    //
    // Convert the <detail> model into a container widget in the editor UI.
    conversion.for('editingDowncast').elementToElement({
      model: 'detail',
      view: (modelElement, { writer: viewWriter }) => {
        const details = viewWriter.createContainerElement('details');

        return toWidget(details, viewWriter, { label: 'detail widget' });
      },
    });

    // Convert the <detailSummary> model into an editable <h2> widget.
    conversion.for('editingDowncast').elementToElement({
      model: 'detailSummary',
      view: (modelElement, { writer: viewWriter }) => {
        const summary = viewWriter.createEditableElement('summary');
        return toWidgetEditable(summary, viewWriter);
      },
    });

    // Convert the <detailWrapper> model into an editable <div> widget.
    conversion.for('editingDowncast').elementToElement({
      model: 'detailWrapper',
      view: (modelElement, { writer: viewWriter }) => {
        const div = viewWriter.createEditableElement('div', {
          class: 'details-wrapper',
        });
        return toWidgetEditable(div, viewWriter);
      },
    });
  }

  /**
   * Add event listeners to control the behavior of the details/summary elements.
   */
  _addEventListeners() {
    const editor = this.editor;
    const viewDocument = editor.editing.view.document;

    this.listenTo(viewDocument, 'blur', (evt, data) => {
      const target = data.domTarget;
      const isSummary = target.tagName.toLowerCase() === 'summary';

      if (isSummary) {
        if (target.innerHTML == '<br data-cke-filler="true">') {
          return;
        }
        // Delete any zero-width spaces.
        const markup = target.innerHTML.replace(/\u200B/gi, '');
        const viewFragment = editor.data.processor.toView(markup);
        const modelFragment = editor.data.toModel(viewFragment);

        const currentSelection = editor.model.document.selection;
        const scopeElement = editor.model.schema.getLimitElement(currentSelection);
        const selection = editor.model.createSelection(scopeElement, 'in');

        editor.model.insertContent(modelFragment, selection);
      }
    });

    this.listenTo(viewDocument, 'keydown', (evt, data) => {
      const isSummary = data.domTarget.tagName.toLowerCase() === 'summary';
      const isSpace = data.domEvent.key === ' ' || data.domEvent.code === 'Space' || data.domEvent.keyCode === 32;

      if (isSummary && isSpace) {
        // Direct insertion of a space as HTML will be ignored at the end of the summary.
        editor.execute('insertText', {
          text: ' ',
        });

        evt.stop();
        data.preventDefault();
      }
    });
  }
}
