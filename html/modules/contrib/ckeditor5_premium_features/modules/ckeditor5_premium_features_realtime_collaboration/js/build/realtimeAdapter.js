/*!
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */
!function(e,t){"object"==typeof exports&&"object"==typeof module?module.exports=t():"function"==typeof define&&define.amd?define([],t):"object"==typeof exports?exports.CKEditor5=t():(e.CKEditor5=e.CKEditor5||{},e.CKEditor5.realtimeAdapter=t())}(self,(()=>(()=>{"use strict";var e={d:(t,i)=>{for(var o in i)e.o(i,o)&&!e.o(t,o)&&Object.defineProperty(t,o,{enumerable:!0,get:i[o]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t)},t={};e.d(t,{default:()=>o});const i=class{constructor(e){this.editor=e,this.elementId=this.editor.sourceElement.dataset.ckeditor5PremiumElementId}processCollaborationCommandDisable(e){if(!this.isCollaborationDisabled())return!1;const t=this.editor.commands._commands.get(e);return void 0===t||t.forceDisabled("premium-features-module"),!0}processRevisionDisable(){return!!this.isCollaborationDisabled()&&(this.editor.plugins.has("RevisionTracker")&&(this.editor.plugins.get("RevisionTracker").isEnabled=!1),!0)}isCollaborationDisabled(){return void 0!==drupalSettings.ckeditor5Premium&&void 0!==drupalSettings.ckeditor5Premium.disableCollaboration&&!0===drupalSettings.ckeditor5Premium.disableCollaboration}getEditorParentContainer(e){let t=document.getElementById(e);for(;t&&void 0!==t&&void 0!==t.classList&&!t.classList.contains("ck-editor-container");)t=t.parentElement;return t&&void 0!==t?t.parentElement:null}getSourceDataSelector(e){return{trackChanges:".track-changes",comments:".comments",revisionHistory:".revision-history",revisionHistoryContainer:".revision-history-container",resolvedSuggestionsComments:".resolved-suggestions-comments"}[e]+"-data"+`[data-ckeditor5-premium-element-id="${this.elementId}"]`}};const o={RealtimeAdapter:class{constructor(e){this.editor=e,this.storage=new i(e),void 0!==drupalSettings.ckeditor5ChannelId&&void 0!==this.editor.sourceElement.dataset.ckeditorfieldid&&void 0!==drupalSettings.ckeditor5ChannelId[this.editor.sourceElement.dataset.ckeditorfieldid]&&(this.editor.config._config.collaboration={channelId:drupalSettings.ckeditor5ChannelId[this.editor.sourceElement.dataset.ckeditorfieldid]},this.setPresenceListContainer())}static get pluginName(){return"RealtimeAdapter"}init(){const e=this.editor,t=e.plugins.has("RealTimeCollaborativeEditing"),i=e.plugins.has("SourceEditing");t&&i&&(console.info("The Source editing plugin is not compatible with real-time collaboration, so it has been disabled. If you need it, please contact us to discuss your use case - https://ckeditor.com/contact/"),e.plugins.get("SourceEditing").forceDisabled("drupal-rtc"))}setPresenceListContainer(){const e=this.editor.config._config.presenceList;if(e&&void 0!==e){if(!e.container){const t=this.editor.sourceElement.id+"-presence-list-container";e.container=document.getElementById(t)}e.collapseAt||(e.collapseAt=drupalSettings.presenceListCollapseAt)}}afterInit(){if(this.storage.processCollaborationCommandDisable("trackChanges"),this.storage.processCollaborationCommandDisable("addCommentThread"),this.checkIfInitialDataChanged(),drupalSettings.ckeditor5Premium.notificationsEnabled){this.editor.sourceElement.closest("form").addEventListener("submit",(()=>{const e=this.editor.plugins.has("CommentsRepository"),t=this.editor.plugins.has("TrackChanges");if(!e||!t)return;const i=".track-changes",o=".comments",s=`[data-ckeditor5-premium-element-id="${this.editor.sourceElement.dataset.ckeditor5PremiumElementId}"]`;if(t){let e=new Map;const t=i+"-data",o=this.editor.plugins.get("TrackChanges").getSuggestions({skipNotAttached:!1}),r=document.querySelector(t+s);for(let t in o)null==o[t].head||null==o[t].next&&null==o[t].previous||o[t].setAttribute("head",o[t].head.id),e.set(o[t].id,o[t]);r.value=JSON.stringify(Array.from(e.values()))}if(e){const e=o+"-data",t=this.editor.plugins.get("CommentsRepository");document.querySelector(e+s).value=JSON.stringify(t.getCommentThreads({skipNotAttached:!0,skipEmpty:!0,toJSON:!0}))}}))}this.editor.on("ready",(()=>{let e=this.editor.sourceElement.dataset.editorActiveTextFormat,t=drupalSettings.ckeditor5Premium.tracking_changes.default_state;void 0!==t[e]&&t[e]&&this.editor.execute("trackChanges")}))}checkIfInitialDataChanged(){const e=this.editor.config._config.initialData;this.editor.on("ready",(()=>{e!==this.editor.getData()&&this.editor.sourceElement.setAttribute("data-editor-value-is-changed",!0)}))}}};return t=t.default})()));