/*!
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */
!function(e,t){"object"==typeof exports&&"object"==typeof module?module.exports=t():"function"==typeof define&&define.amd?define([],t):"object"==typeof exports?exports.CKEditor5=t():(e.CKEditor5=e.CKEditor5||{},e.CKEditor5.mentionsIntegration=t())}(self,(()=>(()=>{"use strict";var e={d:(t,i)=>{for(var o in i)e.o(i,o)&&!e.o(t,o)&&Object.defineProperty(t,o,{enumerable:!0,get:i[o]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t)},t={};e.d(t,{default:()=>i});const i={MentionsIntegration:class{constructor(e){if(this.editor=e,void 0===this.editor.plugins._availablePlugins||!this.editor.plugins._availablePlugins.has("Mention")||void 0===drupalSettings.ckeditor5Premium||void 0===drupalSettings.ckeditor5Premium.mentions)return;const t={feeds:[{feed:this.getFeedItems,marker:drupalSettings.ckeditor5Premium.mentions.marker,minimumCharacters:drupalSettings.ckeditor5Premium.mentions.minCharacter,dropdownLimit:drupalSettings.ckeditor5Premium.mentions.dropdownLimit}]};this.editor.config._config.mention=t,void 0!==this.editor.config._config.comments&&void 0!==this.editor.config._config.comments.editorConfig&&(this.editor.config._config.comments.editorConfig.extraPlugins.push(this.editor.plugins._availablePlugins.get("Mention")),this.editor.config._config.comments.editorConfig.mention=t)}static get pluginName(){return"MentionsIntegration"}getFeedItems(e){if(void 0!==drupalSettings.ckeditor5Premium&&void 0!==drupalSettings.ckeditor5Premium.mentions)return new Promise((t=>{jQuery.ajax("/ck5/api/annotations",{data:{query:e},success:function(e){t(e)}})}))}}};return t=t.default})()));