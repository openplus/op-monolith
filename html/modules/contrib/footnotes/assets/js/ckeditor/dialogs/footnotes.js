/**
 * @file
 */

function footnotesDialog(editor, isEdit) {
  return {
    title: Drupal.t("Footnotes Dialog"),
    minWidth: 500,
    minHeight: 50,
    contents: [
      {
        id: "info",
        label: Drupal.t("Add a footnote"),
        title: Drupal.t("Add a footnote"),
        elements: [
          {
            id: "footnote",
            type: "textarea",
            class: 'footnote_text',
            label: Drupal.t("Footnote text :"),
            setup(element) {
              if (isEdit) {
                this.setValue(element.getHtml());
              }
            }
          },
          {
            id: "value",
            type: "text",
            label: Drupal.t("Value :"),
            setup(element) {
              if (isEdit) {
                this.setValue(element.getAttribute("value"));
              }
            }
          }
        ]
      }
    ],
    onShow() {
      if (isEdit) {
        this.fakeObj = CKEDITOR.plugins.footnotes.getSelectedFootnote(editor);
        this.realObj = editor.restoreRealElement(this.fakeObj);
      }
      this.setupContent(this.realObj);

      var dialog = this;
      CKEDITOR.on( 'instanceLoaded', function ( evt ) {
        dialog.editor_name = evt.editor.name;
        dialog.footnotes_editor = evt.editor;
      });

      var current_textarea = this.getElement().findOne('.footnote_text').getAttribute('id');
      var config = {
        stylesSet: false,
        customConfig: false,
        contentsCss: false,
        height: 80,
        autoGrow_minHeight: 80,
        autoParagraph: false,
        enterMode : CKEDITOR.ENTER_BR,
        toolbarGroups: [
          { name: 'basicstyles' },
        ]
      };
      CKEDITOR.replace(current_textarea, config);
    },
    onOk() {
      var dialog = this;
      var footnote_editor = CKEDITOR.instances[dialog.editor_name];
      var footnote_data   = footnote_editor.getData();

      CKEDITOR.plugins.footnotes.createFootnote(
        editor,
        this.realObj,
        footnote_data,
        this.getValueOf("info", "value")
      );
      delete this.fakeObj;
      delete this.realObj;
      footnote_editor.destroy();
    },
    onCancel() {
      var dialog = this;
      var footnote_editor = CKEDITOR.instances[dialog.editor_name];
      footnote_editor.destroy();
    }
  };
}

CKEDITOR.dialog.add("createfootnotes", editor => footnotesDialog(editor));
CKEDITOR.dialog.add("editfootnotes", editor => footnotesDialog(editor, 1));
