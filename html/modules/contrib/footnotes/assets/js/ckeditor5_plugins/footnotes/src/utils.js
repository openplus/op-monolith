import FootnotesTextAreaView from "./footnotestextareaview";

/**
 * A helper for creating labeled footnotes text area inputs.
 *
 * This helper functions the same as createLabeledInputText(), but creates an instance of our custom text area instead.
 *
 * @param {module:ui/labeledview/labeledview~LabeledView} labeledFieldView The instance of the labeled view.
 * @param {String} viewUid An UID string that allows DOM logical connection between the
 * {@link module:ui/labeledview/labeledview~LabeledView#labelView labeled view's label} and the input.
 * @param {String} statusUid An UID string that allows DOM logical connection between the
 * {@link module:ui/labeledview/labeledview~LabeledView#statusView labeled view's status} and the input.
 * @returns {module:ui/inputtext/inputtextview~InputTextView} The input text view instance.
 */
export function createLabeledFootnotesTextArea(labeledFieldView, viewUid, statusUid ) {
  const inputView = new FootnotesTextAreaView( labeledFieldView.locale );

  inputView.set( {
    id: viewUid,
    ariaDescribedById: statusUid
  } );

  inputView.bind( 'isReadOnly' ).to( labeledFieldView, 'isEnabled', value => !value );
  inputView.bind( 'hasError' ).to( labeledFieldView, 'errorText', value => !!value );

  inputView.on( 'input', () => {
    // UX: Make the error text disappear and disable the error indicator as the user
    // starts fixing the errors.
    labeledFieldView.errorText = null;
  } );

  labeledFieldView.bind( 'isEmpty', 'isFocused', 'placeholder' ).to( inputView );

  return inputView;
}
