import { Plugin } from 'ckeditor5/src/core';
import { toWidget, toWidgetEditable } from 'ckeditor5/src/widget';
import { Widget } from 'ckeditor5/src/widget';
import InsertViewCommand from './insertviewcommand';

/**
 * Gets the preview container element from the media element.
 *
 * @param {Iterable.<module:engine/view/element~Element>} children
 *   The child elements.
 * @return {null|module:engine/view/element~Element}
 *   The preview child element if available.
 */
export function getPreviewContainer(children) {
  // eslint-disable-next-line no-restricted-syntax
  for (const child of children) {
    if (child.hasAttribute('data-drupal-media-preview')) {
      return child;
    }

    if (child.childCount) {
      const recursive = getPreviewContainer(child.getChildren());
      // Return only if preview container was found within this element's
      // children.
      if (recursive) {
        return recursive;
      }
    }
  }

  return null;
}

/**
 * CKEditor 5 plugins do not work directly with the DOM. They are defined as
 * plugin-specific data models that are then converted to markup that
 * is inserted in the DOM.
 *
 * CKEditor 5 internally interacts with simpleBox as this model:
 * <simpleBox>
 *    <simpleBoxTitle></simpleBoxTitle>
 *    <simpleBoxDescription></simpleBoxDescription>
 * </simpleBox>
 *
 * Which is converted for the browser/user as this markup
 * <section class="simple-box">
 *   <h2 class="simple-box-title"></h1>
 *   <div class="simple-box-description"></div>
 * </section>
 *
 * This file has the logic for defining the simpleBox model, and for how it is
 * converted to standard DOM markup.
 */
export default class InsertViewAdvEditing extends Plugin {
  static get requires() {
    return [Widget];
  }

  init() {
    this.attrs = {
      drupalViewId: 'data-view-id',
      drupalViewDisplay: 'data-display-id',
      drupalViewAttributes: 'data-arguments',
    };
    const options = this.editor.config.get('insertViewAdv');
    if (!options) {
      return;
    }
    const { previewURL, themeError } = options;
    this.previewUrl = previewURL;
    this.labelError = Drupal.t('Preview failed');
    this.themeError =
      themeError ||
      `
      <p>${Drupal.t(
        'An error occurred while trying to preview the view. Please save your work and reload this page.',
      )}<p>
    `
    this._defineSchema();
    this._defineConverters();
    this.editor.commands.add(
      'insertViewAdv',
      new InsertViewCommand(this.editor),
    );
  }

  /*
   * This registers the structure that will be seen by CKEditor 5 as
   * <simpleBox>
   *    <simpleBoxTitle></simpleBoxTitle>
   *    <simpleBoxDescription></simpleBoxDescription>
   * </simpleBox>
   *
   * The logic in _defineConverters() will determine how this is converted to
   * markup.
   */
  _defineSchema() {
    // Schemas are registered via the central `editor` object.
    const schema = this.editor.model.schema;

    schema.register('insertViewAdv', {
      // Behaves like a self-contained object (e.g. an image).
      isObject: true,
      isContent: true,
      isBlock: true,
      allowAttributes: Object.keys(this.attrs),
      // Allow in places where other blocks are allowed (e.g. directly in the root).
      allowWhere: '$block',
    });
    this.editor.editing.view.domConverter.blockElements.push('drupal-view');
  }

  /**
   * Converters determine how CKEditor 5 models are converted into markup and
   * vice-versa.
   */
  _defineConverters() {
    // Converters are registered via the central editor object.
    const { conversion } = this.editor;

    // Upcast Converters: determine how existing HTML is interpreted by the
    // editor. These trigger when an editor instance loads.
    //
    // If <section class="simplebox"> is present in the existing markup
    // processed by CKEditor, then CKEditor recognizes and loads it as a
    // <simpleBox> model.
    conversion.for('upcast').elementToElement({
      model: 'insertViewAdv',
      view: {
        name: 'drupal-view',
      },
    });

    // Data Downcast Converters: converts stored model data into HTML.
    // These trigger when content is saved.
    //
    // Instances of <simpleBox> are saved as
    // <section class="simple-box">{{inner content}}</section>.
    conversion.for('dataDowncast').elementToElement({
      model: 'insertViewAdv',
      view: {
        name: 'drupal-view',
      },
    });

    // Editing Downcast Converters. These render the content to the user for
    // editing, i.e. this determines what gets seen in the editor. These trigger
    // after the Data Upcast Converters, and are re-triggered any time there
    // are changes to any of the models' properties.
    //
    // Convert the <simpleBox> model into a container widget in the editor UI.
    conversion.for('editingDowncast').elementToElement({
      model: 'insertViewAdv',
      view: (modelElement, { writer: viewWriter }) => {
        const section = viewWriter.createContainerElement('drupal-view', {});
        viewWriter.setCustomProperty('insertViewAdv', true, section);
        return toWidget(section, viewWriter, { label: 'Insert View widget' });
      },
    }).add((dispatcher) => {
      const converter = (event, data, conversionApi) => {
        const viewWriter = conversionApi.writer;
        const modelElement = data.item;
        const container = conversionApi.mapper.toViewElement(data.item);

        // Search for preview container recursively from its children because
        // the preview container could be wrapped with an element such as
        // `<a>`.
        let view = getPreviewContainer(container.getChildren());

        // Use pre-existing media preview container if one exists. If the
        // preview element doesn't exist, create a new element.
        if (view) {
          // Stop processing if media preview is unavailable or a preview is
          // already loading.
          if (view.getAttribute('data-drupal-view-preview') !== 'ready') {
            return;
          }

          // Preview was ready meaning that a new preview can be loaded.
          // "Change the attribute to loading to prepare for the loading of
          // the updated preview. Preview is kept intact so that it remains
          // interactable in the UI until the new preview has been rendered.
          viewWriter.setAttribute(
            'data-drupal-view-preview',
            'loading',
            view,
          );
        } else {
          view = viewWriter.createRawElement('div', {
            'data-drupal-view-preview': 'loading',
          });
          viewWriter.insert(viewWriter.createPositionAt(container, 0), view);
        }

        this._fetchPreview(modelElement).then(({ preview }) => {
          if (!view) {
            // Nothing to do if associated preview wrapped no longer exist.
            return;
          }
          // CKEditor 5 doesn't support async view conversion. Therefore, once
          // the promise is fulfilled, the editing view needs to be modified
          // manually.
          this.editor.editing.view.change((writer) => {
            const viewPreview = writer.createRawElement(
              'div',
              { 'data-drupal-view-preview': 'ready' },
              (domElement) => {
                domElement.innerHTML = preview;
              },
            );
            // Insert the new preview before the previous preview element to
            // ensure that the location remains same even if it is wrapped
            // with another element.
            writer.insert(writer.createPositionBefore(view), viewPreview);
            writer.remove(view);
          });
        });
      };
      dispatcher.on('attribute:drupalViewId:insertViewAdv', converter);
      return dispatcher;
    });
    // Set attributeToAttribute conversion for all supported attributes.
    Object.keys(this.attrs).forEach((modelKey) => {
      const attributeMapping = {
        model: {
          key: modelKey,
          name: 'insertViewAdv',
        },
        view: {
          name: 'drupal-view',
          key: this.attrs[modelKey],
        },
      };
      // Attributes should be rendered only in dataDowncast to avoid having
      // unfiltered data-attributes on the Drupal Media widget.
      conversion.for('dataDowncast').attributeToAttribute(attributeMapping);
      conversion.for('upcast').attributeToAttribute(attributeMapping);
    });
  }

  /**
   * Fetches preview from the server.
   *
   * @param {module:engine/model/element~Element} modelElement
   *   The model element which preview should be loaded.
   * @return {Promise<{preview: string}>}
   *   A promise that returns an object.
   *
   * @private
   */
  async _fetchPreview(modelElement) {
    const query = {
      view_name: modelElement.getAttribute('drupalViewId'),
      view_display_id: modelElement.getAttribute('drupalViewDisplay'),
      view_args: modelElement.getAttribute('drupalViewAttributes'),
    };

    const response = await fetch(
      `${this.previewUrl}?${new URLSearchParams(query)}`,
      {},
    );
    if (response.ok) {
      const preview = await response.text();
      return { preview };
    }

    return { preview: this.themeError };
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'InsertViewAdvEditing';
  }

}
