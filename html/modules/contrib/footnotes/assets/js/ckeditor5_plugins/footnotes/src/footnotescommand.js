// eslint-disable-next-line import/no-unresolved
import { Command } from 'ckeditor5/src/core';

/**
 * Creates a <footnotes> element using the provided writer and attributes.
 *
 * @param {Object} writer - The CKEditor writer instance.
 * @param {Object} attributes - Attributes to be applied to the <footnotes> element.
 * @return {Object} The created <footnotes> element.
 */
function createFootnotes(writer, attributes) {
  return writer.createElement('footnotes', attributes);
}

/**
 * Command for inserting <footnotes> tag into ckeditor.
 */
export default class Footnotescommand extends Command {
  execute(attributes) {
    const footnotesEditing = this.editor.plugins.get('footnotesEditing');

    // Create object that contains supported data-attributes in view data by
    // flipping `DrupalMediaEditing.attrs` object (i.e. keys from object become
    // values and values from object become keys).
    const dataAttributeMapping = Object.entries(footnotesEditing.attrs).reduce(
      (result, [key, value]) => {
        result[value] = key;
        return result;
      },
      {},
    );

    // \Drupal\media\Form\EditorMediaDialog returns data in keyed by
    // data-attributes used in view data. This converts data-attribute keys to
    // keys used in model.
    const modelAttributes = Object.keys(attributes).reduce(
      (result, attribute) => {
        if (dataAttributeMapping[attribute]) {
          if (typeof attributes[attribute].value !== 'undefined') {
            result[dataAttributeMapping[attribute]] =
              attributes[attribute].value;
          } else {
            result[dataAttributeMapping[attribute]] = attributes[attribute];
          }
        }
        return result;
      },
      {},
    );

    this.editor.model.change((writer) => {
      this.editor.model.insertContent(createFootnotes(writer, modelAttributes));
    });
  }

  refresh() {
    const { model } = this.editor;
    const { selection } = model.document;
    const allowedIn = model.schema.findAllowedParent(
      selection.getFirstPosition(),
      'footnotes',
    );
    this.isEnabled = allowedIn !== null;
  }
}
