/* eslint-disable import/no-unresolved */
import { Plugin } from 'ckeditor5/src/core';
import { toWidget, Widget } from 'ckeditor5/src/widget';
import Footnotescommand from './footnotescommand';

function transformClipboardContent(documentFragment) {
  // Function to create a new <footnotes> element
  function createFootnotesElement(value, reference) {
    const footnotesElement = document.createElement('footnotes');

    footnotesElement.setAttribute('data-value', value);
    footnotesElement.setAttribute('data-text', reference);
    footnotesElement.innerHTML = '&nbsp;'; // or any other content you wish to add

    return footnotesElement;
  }

  // Find all the footnotes
  const footnotes = documentFragment.querySelectorAll(
    '.sdfootnote, [ id*="ftn"] > p',
  );

  footnotes.forEach((footnote) => {
    let footnoteText;

    // Get the text content (not anchor content) of each footnote
    footnote.childNodes.forEach((node) => {
      if (node.nodeType === Node.TEXT_NODE) {
        footnoteText = node.textContent.trim();
      }
    });

    // Find the anchor element
    const anchor = footnote.querySelector('.sdfootnotesym, [ href*="_ftnref"]');

    if (anchor) {
      // Get the link, ensure that it only contains the fragment.
      let footnoteId = anchor.getAttribute('href').replace(/anc|ref|_/g, '');
      footnoteId = `#${footnoteId.split('#').pop()}`;

      // Find the corresponding anchor element and div
      const anchorSup = documentFragment.querySelector(
        `.sdfootnoteanc[href*="${footnoteId}sym"], [href*="_ftn"]`,
      );
      const anchorDiv = documentFragment.querySelector(`div${footnoteId}`);
      const supValue = '';

      if (anchorSup) {
        // Attempt to get footnote text from the anchor div
        // if not found yet.
        if (
          typeof footnoteText === 'undefined' &&
          anchorDiv.querySelector(`.MsoFootnoteReference`)
        ) {
          // Find the reference number like [1] and remove it so the html
          // remaining is only the reference text itself.
          const anchorReferenceNumber = anchorDiv.querySelector(
            `.MsoFootnoteReference`,
          ).parentNode;
          anchorReferenceNumber.parentNode.removeChild(anchorReferenceNumber);
          footnoteText = anchorDiv.querySelector('.MsoFootnoteText').innerHTML;
        }

        // Create the new drupal footnotes element
        const footnotesElement = createFootnotesElement(supValue, footnoteText);

        // Remove unwanted remaining html.
        anchorSup.parentNode.replaceChild(footnotesElement, anchorSup);
        anchorDiv.parentNode.removeChild(anchorDiv);
      }
    }
  });

  // Find all remaining/existing footnotes
  const drupalFootnotes = documentFragment.querySelectorAll('footnotes');

  // Reset the data value for automatic numbering
  drupalFootnotes.forEach((drupalFootnote) => {
    drupalFootnote.setAttribute('data-value', '');
  });

  return documentFragment;
}

/**
 * Footnotes editing functionality.
 */
export default class Footnotesediting extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return [Widget];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'footnotesEditing';
  }

  /**
   * @inheritdoc
   */
  init() {
    this.attrs = {
      footnotesText: 'data-text',
      footnotesValue: 'data-value',
    };
    const options = this.editor.config.get('footnotes');
    if (!options) {
      return;
    }
    const { previewURL, themeError } = options;
    this.previewUrl = previewURL;
    this.themeError =
      themeError ||
      `
        <p>${Drupal.t(
          'An error occurred while trying to preview the embedded content. Please save your work and reload this page.',
        )}<p>
        `;

    this._defineSchema();
    this._defineConverters();

    this.editor.commands.add('footnotes', new Footnotescommand(this.editor));

    // Automatically convert pasted content from Word/LibreOffice to
    // Footnotes format.
    this.editor.plugins.get('ClipboardPipeline').on(
      'inputTransformation',
      (evt, data) => {
        // Convert the view document fragment to a DOM document fragment.
        const viewFragment = data.content;
        const domFragment =
          this.editor.editing.view.domConverter.viewToDom(viewFragment);

        // Apply the footnotes changes and transform it back to a view document
        // fragment.
        const transformedDomFragment = transformClipboardContent(domFragment);
        const transformedViewFragment =
          this.editor.editing.view.domConverter.domToView(
            transformedDomFragment,
          );

        // Replace the data content with the transformed content.
        data.content = transformedViewFragment;
      },
      { priority: 'highest' },
    );
  }

  /**
   * Fetches the preview for the given model element.
   *
   * @param {Element} modelElement - The CKEditor model element representing footnotes.
   */
  async _fetchPreview(modelElement) {
    const query = {
      text: modelElement.getAttribute('footnotesText'),
      value: modelElement.getAttribute('footnotesValue'),
    };
    const response = await fetch(
      `${this.previewUrl}?${new URLSearchParams(query)}`,
    );
    if (response.ok) {
      return response.text();
    }

    return this.themeError;
  }

  /**
   * Registers footnotes as a block element in the DOM converter.
   */
  _defineSchema() {
    const { schema } = this.editor.model;
    schema.register('footnotes', {
      allowWhere: '$inlineObject',
      blockObject: false,
      isObject: true,
      isContent: true,
      isBlock: false,
      isInline: true,
      inlineObject: true,
      allowAttributes: Object.keys(this.attrs),
    });
    this.editor.editing.view.domConverter.blockElements.push('footnotes');
  }

  /**
   * Defines handling of drupal media element in the content lifecycle.
   *
   * @private
   */
  _defineConverters() {
    const { conversion } = this.editor;

    conversion.for('upcast').elementToElement({
      view: {
        name: 'footnotes',
      },
      model: 'footnotes',
    });

    conversion.for('dataDowncast').elementToElement({
      model: 'footnotes',
      view: {
        name: 'footnotes',
      },
    });
    conversion
      .for('editingDowncast')
      .elementToElement({
        model: 'footnotes',
        view: (modelElement, { writer }) => {
          const container = writer.createContainerElement('span');
          return toWidget(container, writer, {
            label: Drupal.t('Footnotes'),
          });
        },
      })
      .add((dispatcher) => {
        const converter = (event, data, conversionApi) => {
          const viewWriter = conversionApi.writer;
          const modelElement = data.item;
          const container = conversionApi.mapper.toViewElement(data.item);
          const footnotes = viewWriter.createRawElement('span', {
            'data-footnotes-preview': 'loading',
            class: 'footnotes-preview',
          });
          viewWriter.insert(
            viewWriter.createPositionAt(container, 0),
            footnotes,
          );
          this._fetchPreview(modelElement).then((preview) => {
            if (!footnotes) {
              return;
            }
            this.editor.editing.view.change((writer) => {
              const footnotesPreview = writer.createRawElement(
                'span',
                {
                  class: 'footnotes-preview',
                  'data-footnotes-preview': 'ready',
                },
                // eslint-disable-next-line max-nested-callbacks
                (domElement) => {
                  domElement.innerHTML = preview;
                },
              );
              writer.insert(
                writer.createPositionBefore(footnotes),
                footnotesPreview,
              );
              writer.remove(footnotes);
            });
          });
        };
        dispatcher.on('attribute:footnotesValue:footnotes', converter);
        return dispatcher;
      });

    Object.keys(this.attrs).forEach((modelKey) => {
      const attributeMapping = {
        model: {
          key: modelKey,
          name: 'footnotes',
        },
        view: {
          name: 'footnotes',
          key: this.attrs[modelKey],
        },
      };
      conversion.for('dataDowncast').attributeToAttribute(attributeMapping);
      conversion.for('upcast').attributeToAttribute(attributeMapping);
    });
  }
}
