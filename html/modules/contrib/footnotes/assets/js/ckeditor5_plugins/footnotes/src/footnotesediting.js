import { Plugin } from 'ckeditor5/src/core';
import { toWidget, toWidgetEditable } from 'ckeditor5/src/widget';
import { Widget } from 'ckeditor5/src/widget';
import InsertFootnotesCommand from './footnotescommand';
import '../theme/footnotes.css';

export default class FootnotesEditing extends Plugin {
  static get requires() {
    return [Widget];
  }
  constructor(editor) {
    super(editor);
  }
  init() {
    this._defineSchema();
    this._defineConverters();
    this.editor.commands.add(
      'insertFootnotes',
      new InsertFootnotesCommand(this.editor),
    );
  }
  _defineSchema() {
    const schema = this.editor.model.schema;
    schema.register('Footnotes', {
      isInline: true,
      isObject: true,
      allowWhere: '$text',
      allowAttributes: ['value', 'text']
    });
  }
  _defineConverters() {
    const conversion = this.editor.conversion;
    // Conversion from a model attribute to a view element.
    conversion.for('upcast').elementToElement({
      view: {
        name: 'fn',
        attributes: ['value']
      },
      model: (viewElement, { writer }) => {
        const rawNodeContent = this.editor.editing.view.domConverter.viewToDom(viewElement).innerHTML;
        const textAttribute = viewElement.getAttribute('text');
        const modelElement = writer.createElement('Footnotes', {
          'value': viewElement.getAttribute('value'),
          'text': textAttribute ? textAttribute : rawNodeContent
        });
        return modelElement;
      }
    });
    conversion.for('dataDowncast').elementToElement({
      model: 'Footnotes',
      view: (modelElement, { writer }) => {
        return writer.createContainerElement('fn', {
          'value': modelElement.getAttribute('value'),
          'text': modelElement.getAttribute('text')
        });
      }
    });
    conversion.for('editingDowncast').elementToElement({
      model: 'Footnotes',
      view: (modelElement, { writer }) => {
        const footnotesView = writer.createContainerElement('fn', {
          value: modelElement.getAttribute('value'),
          text: modelElement.getAttribute('text')
        });

        // Insert [fn] as text.
        writer.insert(
          writer.createPositionAt(footnotesView, 0),
          writer.createText(
            '[fn]'
          )
        );
        return toWidget(footnotesView, writer);
      }
    });
  }
}
