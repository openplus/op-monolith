/* eslint-disable import/no-unresolved */
import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import DoubleClickObserver from './footnotesdoubleclick';
import svgIcon from '../icons/footnotes.svg';

/**
 * Provides the embedded content button and editing.
 */
export default class Footnotesui extends Plugin {
  init() {
    const { editor } = this;
    const options = this.editor.config.get('footnotes');
    if (!options) {
      return;
    }

    const { dialogURL, openDialog, dialogSettings = {} } = options;
    if (!dialogURL || typeof openDialog !== 'function') {
      return;
    }
    editor.ui.componentFactory.add('footnotes', (locale) => {
      const command = editor.commands.get('footnotes');
      const buttonView = new ButtonView(locale);
      buttonView.set({
        label: Drupal.t('Footnotes'),
        icon: svgIcon,
        tooltip: true,
      });

      // Bind the state of the button to the command.
      buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');

      this.listenTo(buttonView, 'execute', () => {
        const modelElement =
          editor.model.document.selection.getSelectedElement();
        const url = new URL(dialogURL, document.baseURI);
        if (
          modelElement &&
          typeof modelElement.name !== 'undefined' &&
          modelElement.name === 'footnotes'
        ) {
          url.searchParams.append(
            'text',
            modelElement.getAttribute('footnotesText'),
          );
          url.searchParams.append(
            'value',
            modelElement.getAttribute('footnotesValue'),
          );
        }
        openDialog(
          url.toString(),
          ({ attributes }) => {
            editor.execute('footnotes', attributes);
          },
          dialogSettings,
        );
      });

      return buttonView;
    });

    const { view } = editor.editing;
    const viewDocument = view.document;

    view.addObserver(DoubleClickObserver);

    editor.listenTo(viewDocument, 'dblclick', (evt, data) => {
      const modelElement = editor.editing.mapper.toModelElement(
        data.target.parent,
      );
      if (
        modelElement &&
        typeof modelElement.name !== 'undefined' &&
        modelElement.name === 'footnotes'
      ) {
        const query = {
          text: modelElement.getAttribute('footnotesText'),
          value: modelElement.getAttribute('footnotesValue'),
        };
        openDialog(
          `${dialogURL}?${new URLSearchParams(query)}`,
          ({ attributes }) => {
            editor.execute('footnotes', attributes);
          },
          dialogSettings,
        );
      }
    });
  }
}
