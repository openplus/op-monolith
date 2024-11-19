import findAttributeRange from '@ckeditor/ckeditor5-typing/src/utils/findattributerange';
import AbbreviationCommand from './abbreviationcommand';

export default class RemoveAbbreviationCommand extends AbbreviationCommand {
  refresh() {
    super.refresh();
    // The command is enabled when the "abbreviation" attribute exists.
    this.isEnabled = !!this.value;
  }

  execute() {
    const model = this.editor.model;
    const selection = model.document.selection;

    model.change( writer => {
      // If the selection is collapsed and the caret is inside an abbreviation, remove it.
      if ( selection.isCollapsed && selection.hasAttribute( 'abbreviation' ) ) {
        // Find the entire range containing the abbreviation under the caret position.
        const abbreviationRange = findAttributeRange( selection.getFirstPosition(), 'abbreviation', selection.getAttribute( 'abbreviation' ), model );

        // Remove the abbreviation.
        writer.removeAttribute( 'abbreviation', abbreviationRange );
      }
      // If the selection has non-collapsed ranges, remove the "abbreviation" attribute from nodes inside those ranges
      // omitting nodes where the "abbreviation" attribute is disallowed.
      else {
      	const ranges = model.schema.getValidRanges( selection.getRanges(), 'abbreviation' );

      	for ( const range of ranges ) {
          writer.removeAttribute( 'abbreviation', range );
        }
      }
    } );
  }
}
