/**
 * @file
 * A CKeditor plugin to insert footnotes as in-place <fn> elements (consumed by Footnotes module in Drupal).
 *
 * This is a rather sophisticated plugin to show a dialog to insert
 * <fn> footnotes or edit existing ones. It produces and understands
 * the <fn>angle bracket</fn> variant and uses the fakeObjects API to
 * show a nice icon to the user, while producing proper <fn> tags when
 * the text is saved or View Source is pressed.
 *
 * If a text contains footnotes of the [fn]square bracket[/fn] variant,
 * they will be visible in the text and this plugin will not react to them.
 *
 * This plugin uses Drupal.t() to translate strings and will not as such
 * work outside of Drupal. (But removing those functions would be the only
 * change needed.) While being part of a Wysiwyg compatible module, it could
 * also be used together with the CKEditor module.
 *
 * Drupal Wysiwyg requirement: The first argument to CKEDITOR.plugins.add()
 * must be equal to the key used in $plugins[] in hook_wysiwyg_plugin().
 */

CKEDITOR.plugins.add("footnotes", {
  requires: ["fakeobjects", "dialog"],
  icons: "footnotes",
  onLoad() {
    CKEDITOR.addCss(
      `.cke_footnote{` +
        `height: 14px;` +
        `display: inline-block;` +
        `vertical-align: super;` +
        `}`
    );
  },
  init(editor) {
    editor.addCommand(
      "createfootnotes",
      new CKEDITOR.dialogCommand("createfootnotes", {
        allowedContent: "fn[value]"
      })
    );
    editor.addCommand(
      "editfootnotes",
      new CKEDITOR.dialogCommand("editfootnotes", {
        allowedContent: "fn[value]"
      })
    );

    // Drupal Wysiwyg requirement: The first argument to editor.ui.addButton()
    // must be equal to the key used in $plugins[<pluginName>]['buttons'][<key>]
    // in hook_wysiwyg_plugin().
    if (editor.ui.addButton) {
      editor.ui.addButton("footnotes", {
        label: Drupal.t("Add a footnote"),
        command: "createfootnotes",
        icon: "footnotes"
      });
    }

    if (editor.addMenuItems) {
      editor.addMenuGroup("footnotes", 100);
      editor.addMenuItems({
        footnotes: {
          label: Drupal.t("Edit footnote"),
          command: "editfootnotes",
          icon: "footnotes",
          group: "footnotes"
        }
      });
    }
    if (editor.contextMenu) {
      editor.contextMenu.addListener(element => {
        if (!element || element.data("cke-real-element-type") !== "fn") {
          return null;
        }
        return { footnotes: CKEDITOR.TRISTATE_ON };
      });
    }

    editor.on("doubleclick", evt => {
      if (CKEDITOR.plugins.footnotes.getSelectedFootnote(editor)) {
        evt.data.dialog = "editfootnotes";
      }
    });

    CKEDITOR.dialog.add("createfootnotes", `${this.path}dialogs/footnotes.js`);
    CKEDITOR.dialog.add("editfootnotes", `${this.path}dialogs/footnotes.js`);
  },
  afterInit(editor) {
    const { dataProcessor } = editor;
    const { dataFilter } = dataProcessor;
    if (dataFilter) {
      dataFilter.addRules({
        elements: {
          fn(element) {
            const fakeElement = editor.createFakeParserElement(
              element,
              "cke_footnote",
              "hiddenfield",
              false
            );
            const value = element['attributes']['value'] ? element['attributes']['value'] : 0;
            const svgWidth = 10 + value.length * 6;
            const icon = "data:image/svg+xml;utf8,<svg width='" + svgWidth + "' height='14' xmlns='http://www.w3.org/2000/svg' xmlns:svg='http://www.w3.org/2000/svg'><text fill='blue' style='font-size:0.7em;font-weight:500;font-family: sans-serif;' x='0' y='9'>[" + value + "]</text></svg>";
            fakeElement.attributes['title'] = Drupal.t('Footnote') + ' ' + value;
            fakeElement.attributes['src'] = icon;
            return fakeElement;
          }
        }
      });
    }
  }
});

CKEDITOR.plugins.footnotes = {
  createFootnote(editor, origElement, text, value) {
    let realElement;
    if (!origElement) {
      realElement = CKEDITOR.dom.element.createFromHtml("<fn></fn>");
    } else {
      realElement = origElement;
    }

    if (text && text.length > 0) {
      realElement.setHtml(text);
    }
    if (value && value.length > 0) {
      realElement.setAttribute("value", value);
    }

    const fakeElement = editor.createFakeElement(
      realElement,
      "cke_footnote",
      "hiddenfield",
      false
    );

    const svgWidth = 10 + value.length * 6;
    const icon = "data:image/svg+xml;utf8,<svg width='" + svgWidth + "' height='14' xmlns='http://www.w3.org/2000/svg' xmlns:svg='http://www.w3.org/2000/svg'><text fill='blue' style='font-size:0.7em;font-weight:500;font-family: sans-serif;' x='0' y='9'>[" + value + "]</text></svg>";
    fakeElement.setAttribute('title', Drupal.t('Footnote') + ' ' + value);
    fakeElement.setAttribute('src', icon);

    editor.insertElement(fakeElement);
  },

  getSelectedFootnote(editor) {
    const selection = editor.getSelection();
    const element = selection.getSelectedElement();
    const seltype = selection.getType();

    if (
      seltype === CKEDITOR.SELECTION_ELEMENT &&
      element.data("cke-real-element-type") === "hiddenfield"
    ) {
      return element;
    }
  }
};
