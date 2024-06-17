import {
  View,
  LabeledFieldView,
  createLabeledInputText,
  ButtonView,
  submitHandler
} from 'ckeditor5/src/ui';
import { icons } from 'ckeditor5/src/core';
import { createLabeledFootnotesTextArea } from "./utils";

export default class FormView extends View {
  constructor(locale) {
    super(locale);
    this.footnotesText = this._createTextArea('Footnote text');
    this.footnotesValue = this._createInput('Value');
    this.saveButtonView = this._createButton('Save', icons.check, 'ck-button-save');
    this.saveButtonView.type = 'submit';
    this.cancelButtonView = this._createButton('Cancel', icons.cancel, 'ck-button-cancel');
    this.cancelButtonView.delegate('execute').to(this, 'cancel');
    this.childViews = this.createCollection([
      this.footnotesText,
      this.footnotesValue,
      this.saveButtonView,
      this.cancelButtonView
    ]);
    this.setTemplate({
      tag: 'form',
      attributes: {
        class: ['ck', 'ck-abbr-footnotes'],
        tabindex: '-1'
      },
      children: this.childViews
    });
  }
  render() {
    super.render();
    submitHandler({
      view: this
    });
  }
  focus() {
    this.childViews.first.focus();
  }
  _createTextArea(label) {
    const labeledInput = new LabeledFieldView(this.locale, createLabeledFootnotesTextArea);
    labeledInput.label = label;
    return labeledInput;
  }
  _createInput(label) {
    const labeledInput = new LabeledFieldView(this.locale, createLabeledInputText);
    labeledInput.label = label;
    return labeledInput;
  }
  _createButton(label, icon, className) {
    const button = new ButtonView();
    button.set({
      label,
      icon,
      tooltip: true,
      class: className
    });
    return button;
  }
}
