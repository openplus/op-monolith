/*!
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */
!function(e,t){"object"==typeof exports&&"object"==typeof module?module.exports=t():"function"==typeof define&&define.amd?define([],t):"object"==typeof exports?exports.CKEditor5=t():(e.CKEditor5=e.CKEditor5||{},e.CKEditor5.wproofreader=t())}(self,(()=>(()=>{var e={"./node_modules/css-loader/dist/cjs.js!./modules/ckeditor5_premium_features_wproofreader/js/libraries/wproofreader-ckeditor5/theme/wproofreader.css":(e,t,s)=>{"use strict";s.d(t,{Z:()=>a});var r=s("./node_modules/css-loader/dist/runtime/noSourceMaps.js"),o=s.n(r),i=s("./node_modules/css-loader/dist/runtime/api.js"),n=s.n(i)()(o());n.push([e.id,".ck.ck-dropdown.ck-wproofreader-empty .ck-dropdown__panel {\n  display: none !important;\n}\n",""]);const a=n},"./node_modules/css-loader/dist/runtime/api.js":e=>{"use strict";e.exports=function(e){var t=[];return t.toString=function(){return this.map((function(t){var s="",r=void 0!==t[5];return t[4]&&(s+="@supports (".concat(t[4],") {")),t[2]&&(s+="@media ".concat(t[2]," {")),r&&(s+="@layer".concat(t[5].length>0?" ".concat(t[5]):""," {")),s+=e(t),r&&(s+="}"),t[2]&&(s+="}"),t[4]&&(s+="}"),s})).join("")},t.i=function(e,s,r,o,i){"string"==typeof e&&(e=[[null,e,void 0]]);var n={};if(r)for(var a=0;a<this.length;a++){var d=this[a][0];null!=d&&(n[d]=!0)}for(var c=0;c<e.length;c++){var l=[].concat(e[c]);r&&n[l[0]]||(void 0!==i&&(void 0===l[5]||(l[1]="@layer".concat(l[5].length>0?" ".concat(l[5]):""," {").concat(l[1],"}")),l[5]=i),s&&(l[2]?(l[1]="@media ".concat(l[2]," {").concat(l[1],"}"),l[2]=s):l[2]=s),o&&(l[4]?(l[1]="@supports (".concat(l[4],") {").concat(l[1],"}"),l[4]=o):l[4]="".concat(o)),t.push(l))}},t}},"./node_modules/css-loader/dist/runtime/noSourceMaps.js":e=>{"use strict";e.exports=function(e){return e[1]}},"./node_modules/style-loader/dist/runtime/injectStylesIntoStyleTag.js":e=>{"use strict";var t=[];function s(e){for(var s=-1,r=0;r<t.length;r++)if(t[r].identifier===e){s=r;break}return s}function r(e,r){for(var i={},n=[],a=0;a<e.length;a++){var d=e[a],c=r.base?d[0]+r.base:d[0],l=i[c]||0,u="".concat(c," ").concat(l);i[c]=l+1;var h=s(u),p={css:d[1],media:d[2],sourceMap:d[3],supports:d[4],layer:d[5]};if(-1!==h)t[h].references++,t[h].updater(p);else{var _=o(p,r);r.byIndex=a,t.splice(a,0,{identifier:u,updater:_,references:1})}n.push(u)}return n}function o(e,t){var s=t.domAPI(t);s.update(e);return function(t){if(t){if(t.css===e.css&&t.media===e.media&&t.sourceMap===e.sourceMap&&t.supports===e.supports&&t.layer===e.layer)return;s.update(e=t)}else s.remove()}}e.exports=function(e,o){var i=r(e=e||[],o=o||{});return function(e){e=e||[];for(var n=0;n<i.length;n++){var a=s(i[n]);t[a].references--}for(var d=r(e,o),c=0;c<i.length;c++){var l=s(i[c]);0===t[l].references&&(t[l].updater(),t.splice(l,1))}i=d}}},"./node_modules/style-loader/dist/runtime/insertBySelector.js":e=>{"use strict";var t={};e.exports=function(e,s){var r=function(e){if(void 0===t[e]){var s=document.querySelector(e);if(window.HTMLIFrameElement&&s instanceof window.HTMLIFrameElement)try{s=s.contentDocument.head}catch(e){s=null}t[e]=s}return t[e]}(e);if(!r)throw new Error("Couldn't find a style target. This probably means that the value for the 'insert' parameter is invalid.");r.appendChild(s)}},"./node_modules/style-loader/dist/runtime/insertStyleElement.js":e=>{"use strict";e.exports=function(e){var t=document.createElement("style");return e.setAttributes(t,e.attributes),e.insert(t,e.options),t}},"./node_modules/style-loader/dist/runtime/setAttributesWithoutAttributes.js":(e,t,s)=>{"use strict";e.exports=function(e){var t=s.nc;t&&e.setAttribute("nonce",t)}},"./node_modules/style-loader/dist/runtime/styleDomAPI.js":e=>{"use strict";e.exports=function(e){var t=e.insertStyleElement(e);return{update:function(s){!function(e,t,s){var r="";s.supports&&(r+="@supports (".concat(s.supports,") {")),s.media&&(r+="@media ".concat(s.media," {"));var o=void 0!==s.layer;o&&(r+="@layer".concat(s.layer.length>0?" ".concat(s.layer):""," {")),r+=s.css,o&&(r+="}"),s.media&&(r+="}"),s.supports&&(r+="}");var i=s.sourceMap;i&&"undefined"!=typeof btoa&&(r+="\n/*# sourceMappingURL=data:application/json;base64,".concat(btoa(unescape(encodeURIComponent(JSON.stringify(i))))," */")),t.styleTagTransform(r,e,t.options)}(t,e,s)},remove:function(){!function(e){if(null===e.parentNode)return!1;e.parentNode.removeChild(e)}(t)}}}},"./node_modules/style-loader/dist/runtime/styleTagTransform.js":e=>{"use strict";e.exports=function(e,t){if(t.styleSheet)t.styleSheet.cssText=e;else{for(;t.firstChild;)t.removeChild(t.firstChild);t.appendChild(document.createTextNode(e))}}},"ckeditor5/src/core.js":(e,t,s)=>{e.exports=s("dll-reference CKEditor5.dll")("./src/core.js")},"ckeditor5/src/ui.js":(e,t,s)=>{e.exports=s("dll-reference CKEditor5.dll")("./src/ui.js")},"ckeditor5/src/utils.js":(e,t,s)=>{e.exports=s("dll-reference CKEditor5.dll")("./src/utils.js")},"dll-reference CKEditor5.dll":e=>{"use strict";e.exports=CKEditor5.dll}},t={};function s(r){var o=t[r];if(void 0!==o)return o.exports;var i=t[r]={id:r,exports:{}};return e[r](i,i.exports,s),i.exports}s.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return s.d(t,{a:t}),t},s.d=(e,t)=>{for(var r in t)s.o(t,r)&&!s.o(e,r)&&Object.defineProperty(e,r,{enumerable:!0,get:t[r]})},s.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),s.nc=void 0;var r={};return(()=>{"use strict";s.d(r,{default:()=>j});var e=s("ckeditor5/src/core.js");class t extends e.Command{execute(e={}){this.editor.plugins.get("WProofreader").toggle()}}class o extends e.Command{execute(e={}){this.editor.plugins.get("WProofreader").openSettings()}}class i extends e.Command{execute(e={}){this.editor.plugins.get("WProofreader").openDialog()}}class n extends e.Plugin{static get pluginName(){return"WProofreaderEditing"}init(){this._addCommands()}afterInit(){this._enableInModes([{modeName:"TrackChanges",editingName:"TrackChangesEditing"},{modeName:"RestrictedEditingMode",editingName:"RestrictedEditingModeEditing"}])}_addCommands(){this.editor.commands.add("WProofreaderToggle",new t(this.editor)),this.editor.commands.add("WProofreaderSettings",new o(this.editor)),this.editor.commands.add("WProofreaderDialog",new i(this.editor))}_enableInModes(e){e.forEach((e=>{this._enableInMode(e.modeName,e.editingName)}))}_enableInMode(e,t){if(this.editor.plugins.has(e)){const e=this.editor.plugins.get(t);["WProofreaderToggle","WProofreaderSettings","WProofreaderDialog"].forEach((t=>e.enableCommand(t)))}}}var a=s("ckeditor5/src/ui.js"),d=s("ckeditor5/src/utils.js");var c=s("./node_modules/style-loader/dist/runtime/injectStylesIntoStyleTag.js"),l=s.n(c),u=s("./node_modules/style-loader/dist/runtime/styleDomAPI.js"),h=s.n(u),p=s("./node_modules/style-loader/dist/runtime/insertBySelector.js"),_=s.n(p),g=s("./node_modules/style-loader/dist/runtime/setAttributesWithoutAttributes.js"),m=s.n(g),f=s("./node_modules/style-loader/dist/runtime/insertStyleElement.js"),b=s.n(f),y=s("./node_modules/style-loader/dist/runtime/styleTagTransform.js"),E=s.n(y),S=s("./node_modules/css-loader/dist/cjs.js!./modules/ckeditor5_premium_features_wproofreader/js/libraries/wproofreader-ckeditor5/theme/wproofreader.css"),v={};v.styleTagTransform=E(),v.setAttributes=m(),v.insert=_().bind(null,"head"),v.domAPI=h(),v.insertStyleElement=b();l()(S.Z,v);S.Z&&S.Z.locals&&S.Z.locals;class w extends e.Plugin{static get pluginName(){return"WProofreaderUI"}constructor(e){super(e),this._commands={toggle:"WProofreaderToggle",settings:"WProofreaderSettings",proofreadDialog:"WProofreaderDialog"}}init(){this._registerDropdown()}_registerDropdown(){const e=this.editor,t=e.plugins.get("WProofreader");e.ui.componentFactory.add("wproofreader",(s=>{const r=(0,a.createDropdown)(s);let o,i;return r.buttonView.set({label:"WProofreader text checker",icon:'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><polygon points="10.46 17.747 7.014 14.292 8.076 13.232 10.461 15.624 16.377 9.708 17.437 10.768 10.46 17.747"/><path d="M10.147,12.276c.063.153.138.331.226.531a2.7,2.7,0,0,0,.144.286L12,11.614l-.01-.027L9.4,5.189c-.122-.321-.222-.576-.3-.768A2.871,2.871,0,0,0,8.8,3.883a1.355,1.355,0,0,0-.453-.409,1.4,1.4,0,0,0-.7-.159,1.387,1.387,0,0,0-.693.159,1.314,1.314,0,0,0-.453.416,3.4,3.4,0,0,0-.325.646L5.92,5.2,3.383,11.641c-.1.257-.178.457-.223.6a1.4,1.4,0,0,0-.067.419.852.852,0,0,0,.29.629.941.941,0,0,0,.67.277.756.756,0,0,0,.643-.26,4.432,4.432,0,0,0,.473-1l.473-1.272H9.673ZM6.17,9.524,7.638,5.432,9.132,9.524Z"/></svg>\n',tooltip:!0}),r.on("change:isOpen",(e=>{const s=t.isInstancesReady(),n=t.isInstancesEnabled();s?(o||(i=t.getStaticActions(),o=this._getDropdownItemsDefinitions(i),(0,a.addListToDropdown)(r,o)),r.class="",o.map((e=>{e.model.class=n?"":"ck-hidden","WProofreaderToggle"===e.model.commandParam&&(e.model.label=n?e.model.localization.disable:e.model.localization.enable,e.model.class="")}))):r.class="ck-wproofreader-empty"})),r.on("execute",(t=>{e.execute(t.source.commandParam)})),r.bind("isEnabled").to(e.commands.get("WProofreaderToggle")),r}))}_getDropdownItemsDefinitions(e){const t=new d.Collection;return e.forEach((e=>{const s={commandParam:this._commands[e.name],label:e.localization.default,localization:e.localization,class:"",withText:!0};let r={};try{r={type:"button",model:new a.Model(s)}}catch(e){r={type:"button",model:new a.ViewModel(s)}}t.add(r)})),t}}class C{constructor(){this._create()}_create(){window.WPROOFREADER_SRCSTORAGE=window.WPROOFREADER_SRCSTORAGE||{},this._storage=window.WPROOFREADER_SRCSTORAGE}has(e){return!!this._storage[e]}add(e){this._storage[e]={onLoad:[],onError:[]}}addCallbacks(e,t,s){this._storage[e].onLoad.push(t),this._storage[e].onError.push(s)}eachOnLoad(e,t){this._storage[e].onLoad.forEach(t)}eachOnError(e,t){this._storage[e].onError.forEach(t)}delete(e){delete this._storage[e]}get(e){return this._storage[e]}}class O{constructor(e){this._validateSrc(e),this._src=e,this._globalSrcStorage=new C}load(){return new Promise(((e,t)=>{this._isScriptOnPage()?this._processExistingScript(e,t):this._createScript(e,t)}))}_validateSrc(e){if(!e)throw new Error("Path to the script is not specified.")}_isScriptOnPage(){return!!document.querySelector('script[src="'+this._src+'"]')}_createScript(e,t){this._script=this._createElement(),this._globalSrcStorage.add(this._src),this._globalSrcStorage.addCallbacks(this._src,e,t),this._subscribeOnScriptLoad(),this._subscribeOnScriptError(),this._appendScript(this._script)}_createElement(){const e=document.createElement("script");return e.type="text/javascript",e.charset="UTF-8",e.src=this._src,e}_subscribeOnScriptLoad(){this._script.onload=()=>{this._globalSrcStorage.eachOnLoad(this._src,(e=>{e()})),this._destroy()}}_subscribeOnScriptError(){this._script.onerror=()=>{const e=new Error(`${this._src} failed to load.`);this._globalSrcStorage.eachOnError(this._src,(t=>{t(e)})),this._destroy()}}_destroy(){this._removeListeners(),this._globalSrcStorage.delete(this._src),this._src=null,this._script=null}_removeListeners(){this._script.onload=null,this._script.onerror=null}_appendScript(e){document.getElementsByTagName("head")[0].appendChild(e)}_processExistingScript(e,t){this._globalSrcStorage.has(this._src)?this._addCallbacks(e,t):this._processLoadedScript(e)}_addCallbacks(e,t){this._globalSrcStorage.addCallbacks(this._src,e,t)}_processLoadedScript(e){e()}}const P="InstancesDisabling";class T extends e.Plugin{static get requires(){return[n,w]}static get pluginName(){return"WProofreader"}constructor(e){super(e),this.set("isToggleCommandEnabled",!0),this._instances=[],this._collaborationPluginNames=["RealTimeCollaborativeEditing","RealTimeCollaborativeTrackChanges","RealTimeCollaborativeComments","RealTimeCollaborationClient"],this._restrictedEditingName="RestrictedEditingMode"}init(){this._userOptions=this._getUserOptions(),this._setTheme(),this._setAutoStartup(),this._setBadgeOffset(),this._setIsEnabled(this._userOptions.autoStartup,P),this._loadWscbundle().then((()=>{this._handleWscbundleLoaded()})).catch((e=>{throw new Error(e)})),this.bind("isToggleCommandEnabled").to(this.editor.commands.get("WProofreaderToggle"),"isEnabled",(e=>this._handleToggleCommandEnabled(e)))}destroy(){super.destroy(),this._instances.forEach((e=>e.destroy())),this._instances=null}_getUserOptions(){const e=this.editor.config.get("wproofreader");if(!e)throw new Error("No WProofreader configuration.");return e}_setTheme(){this._userOptions.theme||(this._userOptions.theme="ckeditor5")}_setAutoStartup(){this._userOptions.hasOwnProperty("autoStartup")||(this._userOptions.autoStartup=!0)}_setBadgeOffset(){this._userOptions.fullSizeBadge||(this._userOptions.hasOwnProperty("badgeOffsetX")||(this._userOptions.badgeOffsetX=11),this._userOptions.hasOwnProperty("badgeOffsetY")||(this._userOptions.badgeOffsetY=11))}_setIsEnabled(e,t){e?this.clearForceDisabled(t):this.forceDisabled(t)}_loadWscbundle(){return new O(this._userOptions.srcUrl).load()}_handleWscbundleLoaded(){"ready"===this.editor.state?this._createInstances():this._subscribeOnEditorReady()}_createInstances(){const e=this.editor.editing.view.domRoots.values();this._setFields();for(const t of e)this._createInstance(t)}_setFields(){this._isMultiRoot=this._checkMultiRoot(),this._isCollaborationMode=this._checkCollaborationMode(),this._isRestrictedEditingMode=this._checkRestrictedEditingMode(),this._options=this._createOptions()}_checkMultiRoot(){return this.editor.editing.view.domRoots.size>1}_checkCollaborationMode(){for(let e=0;e<=this._collaborationPluginNames.length;e++)if(this.editor.plugins.has(this._collaborationPluginNames[e]))return!0;return!1}_checkRestrictedEditingMode(){return this.editor.plugins.has(this._restrictedEditingName)}_createOptions(){return{appType:"proofreader_ck5",disableDialog:this._isMultiRoot||this._isCollaborationMode,restrictedEditingMode:this._isRestrictedEditingMode,disableBadgePulsing:!0,onCommitOptions:this._onCommitOptions.bind(this),onToggle:this._onToggle.bind(this)}}_onCommitOptions(e){this._syncOptions(e)}_syncOptions(e){this._instances.forEach((t=>{t.commitOption(e,{ignoreCallback:!0})}))}_onToggle(e){const t=!e.isDisabled();this._setIsEnabled(t,P),this._syncToggle(t)}_syncToggle(e){this._instances.forEach((t=>{e?this._enableInstance(t):this._disableInstance(t)}))}_enableInstance(e){this.isEnabled&&e.enable({ignoreCallback:!0})}_disableInstance(e){e.disable({ignoreCallback:!0})}_createInstance(e){WEBSPELLCHECKER.init(this._mergeOptions(e),this._handleInstanceCreated.bind(this))}_mergeOptions(e){return Object.assign({},this._userOptions,this._options,{container:e})}_handleInstanceCreated(e){e&&("destroyed"!==this.editor.state?(this.isEnabled||this._disableInstance(e),this._instances.push(e)):e.destroy())}_subscribeOnEditorReady(){this.editor.on("ready",(()=>{this._createInstances()}))}_handleToggleCommandEnabled(e){return this._setIsEnabled(e,"WProofreaderToggleCommandDisabling"),this._syncToggle(e),e}getStaticActions(){return 0===this._instances.length?[]:this._instances[0].getStaticActions()}toggle(){if(0===this._instances.length)return;const e=this.isInstancesEnabled();this._setIsEnabled(!e,P),this._syncToggle(!e)}openSettings(){0!==this._instances.length&&this._instances[0].openSettings()}openDialog(){0!==this._instances.length&&this._instances[0].openDialog()}isInstancesReady(){return this._instances.length>0}isInstancesEnabled(){return 0!==this._instances.length&&!this._instances[0].isDisabled()}}const j={WProofreader:T}})(),r=r.default})()));