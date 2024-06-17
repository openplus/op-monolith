/* eslint-disable import/no-extraneous-dependencies */
// cSpell:words conversionutils datafilter eventinfo downcastdispatcher generalhtmlsupport
import { Plugin } from 'ckeditor5/src/core';
import { setViewAttributes } from '@ckeditor/ckeditor5-html-support/src/conversionutils';

/**
 * View-to-model conversion helper for Drupal View.
 * Used for preserving allowed attributes on the Drupal View model.
 *
 * @param {module:html-support/datafilter~DataFilter} dataFilter
 *   The General HTML support data filter.
 *
 * @return {function}
 *   Function that adds an event listener to upcastDispatcher.
 */
function viewToModelDrupalViewAttributeConverter(dataFilter) {
  return (dispatcher) => {
    dispatcher.on(
      'element:drupal-view',
      (evt, data, conversionApi) => {
        function preserveElementAttributes(viewElement, attributeName) {
          const viewAttributes = dataFilter.processViewAttributes(
            viewElement,
            conversionApi,
          );

          if (viewAttributes) {
            conversionApi.writer.setAttribute(
              attributeName,
              viewAttributes,
              data.modelRange,
            );
          }
        }

        const viewMediaElement = data.viewItem;
        preserveElementAttributes(viewMediaElement, 'htmlAttributes');
      },
      { priority: 'low' },
    );
  };
}

/**
 * Gets descendant element from a container.
 *
 * @param {module:engine/model/writer~Writer} writer
 *   The writer.
 * @param {module:engine/view/element~Element} containerElement
 *   The container element.
 * @param {string} elementName
 *   The element name.
 * @return {module:engine/view/element~Element|undefined}
 *   The descendant element matching element name or undefined if not found.
 */
function getDescendantElement(writer, containerElement, elementName) {
  const range = writer.createRangeOn(containerElement);

  // eslint-disable-next-line no-restricted-syntax
  for (const { item } of range.getWalker()) {
    if (item.is('element', elementName)) {
      return item;
    }
  }
}

/**
 * Model to view converter for the Drupal Media wrapper attributes.
 *
 * @param {module:utils/eventinfo~EventInfo} evt
 *   An object containing information about the fired event.
 * @param {Object} data
 *   Additional information about the change.
 * @param {module:engine/conversion/downcastdispatcher~DowncastDispatcher} conversionApi
 *   Conversion interface to be used by the callback.
 */
function modelToDataAttributeConverter(evt, data, conversionApi) {
  if (!conversionApi.consumable.consume(data.item, evt.name)) {
    return;
  }

  const viewElement = conversionApi.mapper.toViewElement(data.item);

  setViewAttributes(conversionApi.writer, data.attributeNewValue, viewElement);
}

/**
 * Model to data view attribute converter.
 *
 * @return {function}
 *   Function that adds an event listener to downcastDispatcher.
 */
function modelToDataViewAttributeConverter() {
  return (dispatcher) => {
    dispatcher.on(
      'attribute:htmlAttributes:insertViewAdv',
      modelToDataAttributeConverter,
      { priority: 'low' },
    );
  };
}

/**
 * Integrates Drupal Media with General HTML Support.
 *
 * @private
 */
export default class DrupalViewGeneralHtmlSupport extends Plugin {
  /**
   * @inheritdoc
   */
  constructor(editor) {
    super(editor);

    // This plugin is only needed if General HTML Support plugin is loaded.
    if (!editor.plugins.has('GeneralHtmlSupport')) {
      return;
    }
    // This plugin works only if `DataFilter` and `DataSchema` plugins are
    // loaded. These plugins are dependencies of `GeneralHtmlSupport` meaning
    // that these should be available always when `GeneralHtmlSupport` is
    // enabled.
    if (
      !editor.plugins.has('DataFilter') ||
      !editor.plugins.has('DataSchema')
    ) {
      console.error(
        'DataFilter and DataSchema plugins are required for Drupal Media to integrate with General HTML Support plugin.',
      );
    }

    const { schema } = editor.model;
    const { conversion } = editor;
    const dataFilter = this.editor.plugins.get('DataFilter');
    const dataSchema = this.editor.plugins.get('DataSchema');

    // This needs to be initialized in ::constructor() to ensure this runs
    // before the General HTML Support has been initialized.
    // @see module:html-support/generalhtmlsupport~GeneralHtmlSupport
    dataSchema.registerBlockElement({
      model: 'insertViewAdv',
      view: 'drupal-view',
    });

    dataFilter.on('register:drupal-view', (evt, definition) => {
      if (definition.model !== 'insertViewAdv') {
        return;
      }

      schema.extend('insertViewAdv', {
        allowAttributes: ['htmlAttributes'],
      });

      conversion
        .for('upcast')
        .add(viewToModelDrupalViewAttributeConverter(dataFilter));
      conversion.for('dataDowncast').add(modelToDataViewAttributeConverter());

      evt.stop();
    });
  }

}
