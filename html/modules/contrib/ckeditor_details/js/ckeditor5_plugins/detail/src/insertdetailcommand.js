/**
 * @file defines InsertDetailCommand, which is executed when the detail
 * toolbar button is pressed.
 */
// cSpell:ignore detailediting

import { Command } from 'ckeditor5/src/core';

export default class InsertDetailCommand extends Command {
  execute() {
    const { model } = this.editor;

    model.change((writer) => {
      // Insert <detail>*</detail> at the current selection position
      // in a way that will result in creating a valid model structure.
      model.insertContent(createDetail(writer, this.editor));
    });
  }

  refresh() {
    const { model } = this.editor;
    const { selection } = model.document;

    // Determine if the cursor (selection) is in a position where adding a
    // detail is permitted. This is based on the schema of the model(s)
    // currently containing the cursor.
    const allowedIn = model.schema.findAllowedParent(
      selection.getFirstPosition(),
      'detail',
    );

    // If the cursor is not in a location where a detail can be added, return
    // null so the addition doesn't happen.
    this.isEnabled = allowedIn !== null;
  }
}

function createDetail(writer, editor) {
  // Create instances of the three elements registered with the editor in
  // detailediting.js.
  const detail = writer.createElement('detail');
  const detailSummary = writer.createElement('detailSummary');
  const detailWrapper = writer.createElement('detailWrapper');

  // Append the title and description elements to the detail, which matches
  // the parent/child relationship as defined in their schemas.
  writer.append(detailSummary, detail);
  writer.append(detailWrapper, detail);

  // The detailWrapper text content will automatically be wrapped in a
  // `<p>`.
  writer.appendElement('paragraph', detailWrapper);

  // Add a default title in the summary.
  writer.appendText(Drupal.t('Details'), detailSummary);

  // Return the element to be added to the editor.
  return detail;
}
