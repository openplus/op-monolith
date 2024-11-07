/*!
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */
!function(e,o){"object"==typeof exports&&"object"==typeof module?module.exports=o():"function"==typeof define&&define.amd?define([],o):"object"==typeof exports?exports.CKEditor5=o():(e.CKEditor5=e.CKEditor5||{},e.CKEditor5.removeIncorrectCollaborationMarkers=o())}(self,(()=>(()=>{"use strict";var e={d:(o,t)=>{for(var r in t)e.o(t,r)&&!e.o(o,r)&&Object.defineProperty(o,r,{enumerable:!0,get:t[r]})},o:(e,o)=>Object.prototype.hasOwnProperty.call(e,o)},o={};e.d(o,{default:()=>t});const t={RemoveIncorrectCollaborationMarkers:class{constructor(e){this.editor=e}afterInit(){this.editor.model.document.once("change:data",(()=>{const e=this.editor.plugins.has("TrackChanges"),o=this.editor.plugins.has("CommentsRepository"),t=Array.from(this.editor.model.document.differ.getChangedMarkers()),r=t.filter((e=>e.name.includes("suggestion"))),i=t.filter((e=>e.name.includes("comment")));if(e){const e=this.editor.plugins.get("TrackChanges");for(const o of r){const{name:t}=o,r=t.split(":"),i=r.length<5?r[2]:r[3];e.getSuggestions().some((e=>e.id==i))||this._removeSuggestionMarker(i)}}if(o){const e=this.editor.plugins.get("CommentsRepository");for(const o of i){const{name:t}=o,r=t.split(":")[1];e.getCommentThreads().some((e=>e.id==r))||this._removeCommentMarker(r)}}}),{priority:"high"})}_removeSuggestionMarker(e){const o=this.editor.model.markers.getMarkersGroup("suggestion"),t=Array.from(o).filter((o=>o.name.includes(e)));this.editor.model.change((e=>{e.removeMarker(...t)}),{priority:"high"})}_removeCommentMarker(e){const o=this.editor.model.markers.getMarkersGroup("comment"),t=Array.from(o).filter((o=>o.name.includes(e)));this.editor.model.change((e=>{e.removeMarker(...t)}))}}};return o=o.default})()));