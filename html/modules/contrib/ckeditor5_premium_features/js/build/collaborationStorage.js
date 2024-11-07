/*!
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */
!function(e,t){"object"==typeof exports&&"object"==typeof module?module.exports=t():"function"==typeof define&&define.amd?define([],t):"object"==typeof exports?exports.CKEditor5=t():(e.CKEditor5=e.CKEditor5||{},e.CKEditor5.collaborationStorage=t())}(self,(()=>(()=>{"use strict";var e={d:(t,o)=>{for(var i in o)e.o(o,i)&&!e.o(t,i)&&Object.defineProperty(t,i,{enumerable:!0,get:o[i]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t)},t={};e.d(t,{default:()=>o});const o={CollaborationStorage:class{constructor(e){this.editor=e,this.elementId=this.editor.sourceElement.dataset.ckeditor5PremiumElementId}processCollaborationCommandDisable(e){if(!this.isCollaborationDisabled())return!1;const t=this.editor.commands._commands.get(e);return void 0===t||t.forceDisabled("premium-features-module"),!0}processRevisionDisable(){return!!this.isCollaborationDisabled()&&(this.editor.plugins.has("RevisionTracker")&&(this.editor.plugins.get("RevisionTracker").isEnabled=!1),!0)}isCollaborationDisabled(){return void 0!==drupalSettings.ckeditor5Premium&&void 0!==drupalSettings.ckeditor5Premium.disableCollaboration&&!0===drupalSettings.ckeditor5Premium.disableCollaboration}getEditorParentContainer(e){let t=document.getElementById(e);for(;t&&void 0!==t&&void 0!==t.classList&&!t.classList.contains("ck-editor-container");)t=t.parentElement;return t&&void 0!==t?t.parentElement:null}getSourceDataSelector(e){return{trackChanges:".track-changes",comments:".comments",revisionHistory:".revision-history",revisionHistoryContainer:".revision-history-container",resolvedSuggestionsComments:".resolved-suggestions-comments"}[e]+"-data"+`[data-ckeditor5-premium-element-id="${this.elementId}"]`}}};return t=t.default})()));