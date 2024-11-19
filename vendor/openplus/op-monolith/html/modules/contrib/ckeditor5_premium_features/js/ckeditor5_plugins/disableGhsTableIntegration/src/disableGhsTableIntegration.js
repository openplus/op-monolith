/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

class DisableGhsTableIntegration {
  constructor( editor ) {
    this.editor = editor;
  }

  static get requires() {
    return [ 'DataFilter' ]
  }

  init() {
    const dataFilter = this.editor.plugins.get( 'DataFilter' );

    dataFilter.on('register:table', (e) => {
      e.stop();
    }, { priority: 'high' });
  }
}

export default DisableGhsTableIntegration;
