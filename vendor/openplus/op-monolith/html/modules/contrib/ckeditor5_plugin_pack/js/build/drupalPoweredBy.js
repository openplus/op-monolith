/*!
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */
!function(e,r){"object"==typeof exports&&"object"==typeof module?module.exports=r():"function"==typeof define&&define.amd?define([],r):"object"==typeof exports?exports.CKEditor5=r():(e.CKEditor5=e.CKEditor5||{},e.CKEditor5.drupalPoweredBy=r())}(self,(()=>(()=>{var e={"ckeditor5/src/core.js":(e,r,o)=>{e.exports=o("dll-reference CKEditor5.dll")("./src/core.js")},"dll-reference CKEditor5.dll":e=>{"use strict";e.exports=CKEditor5.dll}},r={};function o(t){var i=r[t];if(void 0!==i)return i.exports;var d=r[t]={exports:{}};return e[t](d,d.exports,o),d.exports}o.d=(e,r)=>{for(var t in r)o.o(r,t)&&!o.o(e,t)&&Object.defineProperty(e,t,{enumerable:!0,get:r[t]})},o.o=(e,r)=>Object.prototype.hasOwnProperty.call(e,r);var t={};return(()=>{"use strict";o.d(t,{default:()=>i});var e=o("ckeditor5/src/core.js");class r extends e.Plugin{static get pluginName(){return"drupalPoweredBy"}init(){const e=this.editor;e.config._config.drupalPoweredBy&&(e.config._config.ui?e.config._config.ui.poweredBy={forceVisible:!0}:e.config._config.ui={poweredBy:{forceVisible:!0}})}}const i={DrupalPoweredBy:r}})(),t=t.default})()));