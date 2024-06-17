/**
 * @file This is what CKEditor refers to as a master (glue) plugin. Its role is
 * just to load the “editing” and “UI” components of this Plugin. Those
 * components could be included in this file.
 */

import FootnotesEditing from './footnotesediting';
import FootnotesUI from './footnotesui';
import { Plugin } from 'ckeditor5/src/core';
export default class Footnotes extends Plugin {
  static get requires() {
    return [FootnotesEditing, FootnotesUI];
  }
}
