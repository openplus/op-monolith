import { Plugin } from 'ckeditor5/src/core';
import { ButtonView, ContextualBalloon, clickOutsideHandler } from 'ckeditor5/src/ui';
import icon from '../icons/footnotes.svg';
import FormView from './footnotesview';
import { ClickObserver } from 'ckeditor5/src/engine';

export default class FootnotesUI extends Plugin {
  static get requires() {
    return [ ContextualBalloon ];
  }
  init() {
    const editor = this.editor;
    this._balloon = this.editor.plugins.get( ContextualBalloon );
    this.formView = this._createFormView();
    editor.ui.componentFactory.add('Footnotes', (locale) => {
      const command = editor.commands.get('insertFootnotes');
      const buttonView = new ButtonView(locale);
      buttonView.set({
        label: editor.t('Footnotes'),
        icon,
        tooltip: true
      });
      buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');
      this.listenTo(buttonView, 'execute', () => {
        this._showUI();
      });
      return buttonView;
    });

    editor.editing.view.addObserver( ClickObserver );
    editor.listenTo( editor.editing.view.document, 'click', ( evt, data ) => {
      const modelElement = editor.editing.mapper.toModelElement( data.target);

      if ( modelElement.name == 'Footnotes' ) {
        this.formView.footnotesText.fieldView.value = modelElement.getAttribute('text');
        this.formView.footnotesValue.fieldView.value = modelElement.getAttribute('value');
        this._showUI();
      }
    } );
  }
  _createFormView() {
    const editor = this.editor;
    const formView = new FormView( editor.locale );
    // Execute the command after clicking the "Save" button.
    this.listenTo( formView, 'submit', () => {
      // Grab values from input fields.
      const value = {
			  footnotes_text: formView.footnotesText.fieldView.element.value,
				footnotes_value: formView.footnotesValue.fieldView.element.value
			};
      editor.execute( 'insertFootnotes', value );
      // Hide the form view after submit.
      this._hideUI();
    });
    // Hide the form view after clicking the "Cancel" button.
    this.listenTo( formView, 'cancel', () => {
      this._hideUI();
    });
    // Hide the form view when clicking outside the balloon.
    clickOutsideHandler( {
      emitter: formView,
      activator: () => this._balloon.visibleView === formView,
      contextElements: [ this._balloon.view.element ],
      callback: () => this._hideUI()
    });
    return formView;
  }
  _showUI() {
    this._balloon.add( {
      view: this.formView,
      position: this._getBalloonPositionData()
    });
    this.formView.focus();
  }
  _hideUI() {
    // Clear the input field values and reset the form.
    this.formView.footnotesText.fieldView.value = '';
    this.formView.footnotesValue.fieldView.value = '';
    this.formView.element.reset();
    this._balloon.remove( this.formView );
    // Focus the editing view after inserting the content so the user can start typing the content
    // right away and keep the editor focused.
    this.editor.editing.view.focus();
  }
  _getBalloonPositionData() {
    const view = this.editor.editing.view;
    const viewDocument = view.document;
    let target = null;
    // Set a target position by converting view selection range to DOM
    target = () => view.domConverter.viewRangeToDom( viewDocument.selection.getFirstRange() );
    return {
      target
    };
  }
}
