import { Plugin } from 'ckeditor5/src/core';
import AbbreviationEditing from './abbreviationediting';
import AbbreviationUI from './abbreviationui';

export default class Abbreviation extends Plugin {
	static get requires() {
		return [ AbbreviationEditing, AbbreviationUI ];
	}
}