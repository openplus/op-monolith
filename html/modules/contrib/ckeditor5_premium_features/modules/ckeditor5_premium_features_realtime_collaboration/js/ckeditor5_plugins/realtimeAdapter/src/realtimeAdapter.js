/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

import CollaborationStorage
  from "../../../../../../js/ckeditor5_plugins/collaborationStorage/src/collaborationStorage";

class RealtimeAdapter {
  constructor(editor) {
    this.editor = editor;
    this.storage = new CollaborationStorage(editor);

    if (typeof drupalSettings.ckeditor5ChannelId == "undefined" ||
      typeof this.editor.sourceElement.dataset.ckeditorfieldid == "undefined" ||
      typeof drupalSettings.ckeditor5ChannelId[this.editor.sourceElement.dataset.ckeditorfieldid] == "undefined") {
      return;
    }
    this.editor.config._config.collaboration = {
      channelId: drupalSettings.ckeditor5ChannelId[this.editor.sourceElement.dataset.ckeditorfieldid],
    }
    this.setPresenceListContainer();
  }

  static get pluginName() {
    return 'RealtimeAdapter'
  }

  init() {
    const editor = this.editor;
    const hasRTC = editor.plugins.has('RealTimeCollaborativeEditing');
    const hasSourceEditing = editor.plugins.has('SourceEditing');
    if (hasRTC && hasSourceEditing) {
      console.info('The Source editing plugin is not compatible with real-time collaboration, so it has been disabled. If you need it, please contact us to discuss your use case - https://ckeditor.com/contact/');
      editor.plugins.get('SourceEditing').forceDisabled('drupal-rtc');
    }
  }

  setPresenceListContainer() {
    const presenceListConfig = this.editor.config._config.presenceList;
    if (!presenceListConfig || typeof presenceListConfig === "undefined") {
      return;
    }

    if (!presenceListConfig.container) {
      const presenceListContainerId = this.editor.sourceElement.id + '-presence-list-container';
      presenceListConfig.container = document.getElementById(presenceListContainerId);
    }
    if (!presenceListConfig.collapseAt) {
      presenceListConfig.collapseAt = drupalSettings.presenceListCollapseAt;
    }
  }

  /**
   * Executed after plugin is initialized.
   *
   * For the RTC it's the most suitable place to dynamically disable toolbar
   * items.
   */
  afterInit() {
    this.storage.processCollaborationCommandDisable("trackChanges");
    this.storage.processCollaborationCommandDisable("addCommentThread");
    this.checkIfInitialDataChanged();

    if (drupalSettings.ckeditor5Premium.notificationsEnabled) {
      // Hook to form submit.
      const form = this.editor.sourceElement.closest('form');
      form.addEventListener("submit", () => {
        const isCommentsEnabled = this.editor.plugins.has('CommentsRepository');
        const isTrackChangesEnabled = this.editor.plugins.has('TrackChanges');
        if (!isCommentsEnabled || !isTrackChangesEnabled) {
          return
        }

        const elementId = this.editor.sourceElement.dataset.ckeditor5PremiumElementId
        const types = {
          'trackChanges': '.track-changes',
          'comments': '.comments',
        };
        const dataAttribute = `[data-ckeditor5-premium-element-id="${elementId}"]`;

        if (isTrackChangesEnabled) {
          let trackedSuggestion = new Map()
          const trackChangesCssClass = types['trackChanges'] + '-data';
          const trackChangesPlugin = this.editor.plugins.get( 'TrackChanges' );
          const suggestions = trackChangesPlugin.getSuggestions({skipNotAttached: false});
          const trackChangesElement = document.querySelector(trackChangesCssClass + dataAttribute);
          for (let i in suggestions) {
            if (suggestions[i].head != null && (suggestions[i].next != null || suggestions[i].previous != null)) {
              suggestions[i].setAttribute('head', suggestions[i].head.id);
            }
            trackedSuggestion.set(suggestions[i].id, suggestions[i]);
          }
          trackChangesElement.value = JSON.stringify(Array.from(trackedSuggestion.values()));
        }

        if (isCommentsEnabled) {
          const commentsCssClass = types['comments'] + '-data';
          const commentsRepositoryPlugin = this.editor.plugins.get( 'CommentsRepository' );
          const commentsElement = document.querySelector(commentsCssClass + dataAttribute);
          commentsElement.value = JSON.stringify(commentsRepositoryPlugin.getCommentThreads({
            skipNotAttached: true,
            skipEmpty: true,
            toJSON: true
          }));
        }
      });
    }

    this.editor.on('ready', () => {
      let textFormat = this.editor.sourceElement.dataset.editorActiveTextFormat;
      let isTrackingChangesOn = drupalSettings.ckeditor5Premium.tracking_changes.default_state;
      if (typeof isTrackingChangesOn[textFormat] !== 'undefined' && isTrackingChangesOn[textFormat]) {
        this.editor.execute('trackChanges');
      }
    });
  }

  /**
   *  Check if the editor's initial data is different from the data from CS.
   *  If so, set "data-editor-value-is-changed" attribute to TRUE.
   */
  checkIfInitialDataChanged() {
    const initialData = this.editor.config._config.initialData;
    this.editor.on('ready', () => {
      if (initialData !== this.editor.getData()) {
        this.editor.sourceElement.setAttribute('data-editor-value-is-changed', true);
      }
    } );
  }

}

export default RealtimeAdapter;
