import { Plugin } from 'ckeditor5/src/core';
import AbbreviationCommand from './abbreviationcommand';
import RemoveAbbreviationCommand from './removeabbreviationcommand';

export default class AbbreviationEditing extends Plugin {
	init() {
		this._defineSchema();
		this._defineConverters();

		this.editor.commands.add(
			'addAbbreviation', new AbbreviationCommand( this.editor )
		);
    this.editor.commands.add(
      'removeAbbreviation', new RemoveAbbreviationCommand( this.editor )
    );
	}
	_defineSchema() {
		const schema = this.editor.model.schema;

    	// Extend the text node's schema to accept the abbreviation attribute.
		schema.extend( '$text', {
			allowAttributes: [ 'abbreviation' ]
		} );
	}
	_defineConverters() {
		const conversion = this.editor.conversion;

        // Conversion from a model attribute to a view element
		conversion.for( 'downcast' ).attributeToElement( {
			model: 'abbreviation',

            // Callback function provides access to the model attribute value
			// and the DowncastWriter
			view: ( modelAttributeValue, conversionApi ) => {
				const { writer } = conversionApi;
        let titleAttribute = (modelAttributeValue)
          ? { title: modelAttributeValue }
          : null;
        return writer.createAttributeElement('abbr', titleAttribute);
			}
		} );

		// Conversion from a view element to a model attribute
		conversion.for( 'upcast' ).elementToAttribute( {
			view: {
				name: 'abbr',
				attributes: [ 'title' ]
			},
			model: {
				key: 'abbreviation',

                // Callback function provides access to the view element
				value: viewElement => {
					const title = viewElement.getAttribute( 'title' );
					return title;
				}
			}
		} );
	}
}
