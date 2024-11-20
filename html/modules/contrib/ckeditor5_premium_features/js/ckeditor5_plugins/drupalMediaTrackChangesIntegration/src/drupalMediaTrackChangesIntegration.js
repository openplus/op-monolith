/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

import { Plugin } from 'ckeditor5/src/core';

class DrupalMediaTrackChangesIntegration extends Plugin {

  static get pluginName() {
    return 'DrupalMediaTrackChangesIntegration';
  }

  afterInit() {
    const editor = this.editor;

    const trackChangesEditing = editor.plugins.get( 'TrackChangesEditing' );

    trackChangesEditing.enableCommand( 'insertDrupalMedia' );

    const t = editor.t;

    trackChangesEditing._descriptionFactory.registerElementLabel(
      'drupalMedia',

      quantity => t( {
        string: 'drupal media',
        plural: '%0 drupal medias',
        id: 'ELEMENT_DRUPAL_MEDIA'
      }, quantity )
    );
  }
}

export default DrupalMediaTrackChangesIntegration;
