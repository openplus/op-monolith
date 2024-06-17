import { Command } from 'ckeditor5/src/core';
export default class InsertFootnotesCommand extends Command {
  execute(attributes) {
    const { model } = this.editor;
    model.change((writer) => {
      model.insertObject(
        createFootnotes(writer, attributes),
        null,
        null,
        { setSelection: 'on' }
      );
    });
  }
}
function createFootnotes(writer, attributes) {
  const footnotes = writer.createElement('Footnotes', {
    'value': attributes.footnotes_value,
    'text': attributes.footnotes_text
  });
  return footnotes;
}
