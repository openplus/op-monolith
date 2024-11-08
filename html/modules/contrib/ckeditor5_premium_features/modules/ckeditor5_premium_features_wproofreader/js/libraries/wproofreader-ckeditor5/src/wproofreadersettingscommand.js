import { Command } from 'ckeditor5/src/core';

/**
 * The {@code WProofreaderSettingsCommand} to open the {@code WProofreader} settings.
 */
export default class WProofreaderSettingsCommand extends Command {
	/**
	 * Executes the {@code WProofreaderSettingsCommand}.
	 * @public
	 * @inheritDoc
	 */
	execute(options = {}) {
		const wproofreader = this.editor.plugins.get('WProofreader');

		wproofreader.openSettings();
	}
}
