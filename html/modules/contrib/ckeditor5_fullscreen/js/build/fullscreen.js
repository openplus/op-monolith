!function(e,t){"object"==typeof exports&&"object"==typeof module?module.exports=t():"function"==typeof define&&define.amd?define([],t):"object"==typeof exports?exports.CKEditor5=t():(e.CKEditor5=e.CKEditor5||{},e.CKEditor5.fullscreen=t())}(self,(()=>(()=>{var e={"ckeditor5/src/core.js":(e,t,s)=>{e.exports=s("dll-reference CKEditor5.dll")("./src/core.js")},"ckeditor5/src/ui.js":(e,t,s)=>{e.exports=s("dll-reference CKEditor5.dll")("./src/ui.js")},"dll-reference CKEditor5.dll":e=>{"use strict";e.exports=CKEditor5.dll}},t={};function s(r){var o=t[r];if(void 0!==o)return o.exports;var n=t[r]={exports:{}};return e[r](n,n.exports,s),n.exports}s.d=(e,t)=>{for(var r in t)s.o(t,r)&&!s.o(e,r)&&Object.defineProperty(e,r,{enumerable:!0,get:t[r]})},s.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t);var r={};return(()=>{"use strict";s.d(r,{default:()=>c});var e=s("ckeditor5/src/core.js"),t=s("ckeditor5/src/ui.js");const o='<?xml version="1.0" ?><svg enable-background="new 0 0 32 32" height="32px" id="svg2" version="1.1" viewBox="0 0 32 32" width="32px" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:cc="http://creativecommons.org/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd" xmlns:svg="http://www.w3.org/2000/svg"><g id="background"><rect fill="none" height="32" width="32"/></g><g id="fullscreen"><path d="M20,8l8,8V8H20z M4,24h8l-8-8V24z"/><path d="M32,28V4H0v24h14v2H8v2h16v-2h-6v-2H32z M2,26V6h28v20H2z"/></g></svg>';class n extends e.Plugin{init(){const e=this.editor;e.ui.componentFactory.add("fullscreen",(s=>{const r=new t.ButtonView(s),n=e.sourceElement.nextElementSibling;let i=0,c=!1;return r.set({label:"Full screen",icon:o,tooltip:!0}),r.on("execute",(()=>{1==i?(n.scrollIntoView({block:"center"}),n.removeAttribute("data-fullscreen"),document.body.removeAttribute("data-fullscreen"),r.set({label:"Full screen",icon:o,isOn:!1}),i=0,e.focus(),e.ui.view.stickyPanel.isSticky=c):(n.scrollIntoView({block:"center"}),n.setAttribute("data-fullscreen","fullscreeneditor"),document.body.setAttribute("data-fullscreen","fullscreenoverlay"),r.set({label:"Mode Normal",icon:'<?xml version="1.0" ?><svg enable-background="new 0 0 32 32" height="32px" id="svg2" version="1.1" viewBox="0 0 32 32" width="32px" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:cc="http://creativecommons.org/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd" xmlns:svg="http://www.w3.org/2000/svg"><g id="background"><rect fill="none" height="32" width="32"/></g><g id="fullscreen_x5F_cancel"><path d="M4,16v8h8L4,16z M0,4v24h14v2H8v2h16v-0.06c2.702-0.299,5.042-1.791,6.481-3.94H32V4H0z M23,29.999   c-3.865-0.008-6.994-3.135-7-6.999c0.006-3.865,3.135-6.994,7-7c3.864,0.006,6.991,3.135,6.999,7   C29.991,26.864,26.864,29.991,23,29.999z M30,17.35c-0.57-0.707-1.244-1.326-2-1.832V8h-8l6.896,6.896   C25.717,14.328,24.398,14,23,14c-4.972,0-9,4.028-9,9c0,1.054,0.19,2.061,0.523,3H2V6h28V17.35z"/><polygon points="19,25 21,27 23,25 25,27 27,25 25,23 27,21 25,19 23,21 21,19 19,21 21,23  "/></g></svg>',isOn:!0}),i=1,e.focus(),c=e.ui.view.stickyPanel.isSticky,e.ui.view.stickyPanel.isSticky=!c)})),r}))}}class i extends e.Plugin{static get requires(){return[n]}}const c={Fullscreen:i}})(),r=r.default})()));