// eslint-disable-next-line import/no-unresolved
import { Plugin } from 'ckeditor5/src/core';
import Footnotesediting from './footnotesediting';
import Footnotesui from './footnotesui';

/**
 * Main entry point to the footnotes.
 */
export default class Footnotes extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return [Footnotesediting, Footnotesui];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'footnotes';
  }
}
