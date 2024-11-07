/*!
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */
!function(t,e){"object"==typeof exports&&"object"==typeof module?module.exports=e():"function"==typeof define&&define.amd?define([],e):"object"==typeof exports?exports.CKEditor5=e():(t.CKEditor5=t.CKEditor5||{},t.CKEditor5.wordCountAdapter=e())}(self,(()=>(()=>{"use strict";var t={d:(e,r)=>{for(var o in r)t.o(r,o)&&!t.o(e,o)&&Object.defineProperty(e,o,{enumerable:!0,get:r[o]})},o:(t,e)=>Object.prototype.hasOwnProperty.call(t,e)},e={};t.d(e,{default:()=>r});const r={WordCountAdapter:class{constructor(t){this.editor=t}init(){if(this.elementId=this.editor.sourceElement.getAttribute("id"),this.isRevHistoryEnabled=!1,this.elementId.includes("revision-history"))return void(this.isRevHistoryEnabled=!0);this.wordCountId=this.elementId+"-ck-word-count";const t=this.editor.sourceElement.closest(".form-item");if(this.wordCountWrapper=document.createElement("div"),this.wordCountWrapper.setAttribute("class","ck-word-count-container"),this.wordCountWrapper.setAttribute("id",this.wordCountId),t.parentNode.insertBefore(this.wordCountWrapper,t.nextSibling),this.isRevHistoryEnabled)return;const e=this.editor.plugins.get("WordCount");for(var r=0;r<e.wordCountContainer.children.length;r++)e.wordCountContainer.children[r].innerHTML=this.wrapNumber(e.wordCountContainer.children[r].innerHTML);this.wordCountWrapper.appendChild(e.wordCountContainer)}afterInit(){if(this.isRevHistoryEnabled)return;const t=this.editor.plugins.get("WordCount"),e=this.wordCountWrapper.querySelector(".ck-word-count__words span"),r=this.wordCountWrapper.querySelector(".ck-word-count__characters span");if(t.on("update",((t,o)=>{e&&(e.innerText=o.words),r&&(r.innerText=o.characters)})),this.editor.plugins.has("SourceEditing")){this.editor.plugins.get("SourceEditing").on("change:isSourceEditingMode",((t,e,r)=>{!0===r?this.wordCountWrapper.classList.add("ck-word-count-hide-element"):this.wordCountWrapper.classList.remove("ck-word-count-hide-element")}))}}wrapNumber(t){return t.replace(/(\d+)/gi,"<span>$1</span>")}}};return e=e.default})()));