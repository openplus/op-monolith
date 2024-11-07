/**
 * @file registers the simpleBox toolbar button and binds functionality to it.
 */

import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import icon from '../theme/icons/ftinsertcell.svg';

export default class InsertViewAdvUI extends Plugin {
  init() {
    const editor = this.editor;
    const options = this.editor.config.get('insertViewAdv');
    if (!options) {
      return;
    }

    const { libraryURL, openDialog, dialogSettings = {} } = options;
    if (!libraryURL || typeof openDialog !== 'function') {
      return;
    }

    // This will register the simpleBox toolbar button.
    editor.ui.componentFactory.add('insertViewAdv', (locale) => {
      const command = editor.commands.get('insertViewAdv');
      const buttonView = new ButtonView(locale);

      // Create the toolbar button.
      buttonView.set({
        label: editor.t('Insert View'),
        icon: icon,
        tooltip: true,
      });

      // Bind the state of the button to the command.
      buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');

      // Execute the command when the button is clicked (executed).
      this.listenTo(buttonView, 'execute', () => {
        openDialog(
          libraryURL,
          ({ attributes }) => {
            editor.execute('insertViewAdv', attributes);
          },
          dialogSettings,
        );
      });

      return buttonView;
    });

  }
}
