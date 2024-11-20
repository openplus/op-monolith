/*
 * Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

import AWSTextAdapter from "@ckeditor/ckeditor5-ai/src/adapters/awstextadapter";
import OpenAITextAdapter from "@ckeditor/ckeditor5-ai/src/adapters/openaitextadapter";

class AIServiceAdapter {

  static get pluginName() {
    return 'AIServiceAdapter'
  }

  constructor( editor ) {
    this.editor = editor;
    const AIAdapter = this.editor.plugins.get('AIAdapter');
    let textAdapter;
    if (this.editor.config._config.ai.textAdapter === 'aws') {
      textAdapter = new AWSTextAdapter(editor);
    } else {
      textAdapter = new OpenAITextAdapter(editor);
    }
    AIAdapter.set('textAdapter', textAdapter)
  }
}
export default AIServiceAdapter
