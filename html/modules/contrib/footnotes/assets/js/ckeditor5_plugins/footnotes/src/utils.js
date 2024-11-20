// eslint-disable-next-line import/no-unresolved
import { isWidget } from 'ckeditor5/src/widget';

/**
 * Checks if the provided model element is `footnotes`.
 *
 * @param  {module:engine/model/element~Element} modelElement
 *   The model element to be checked.
 * @return {boolean}
 *   A boolean indicating if the element is a footnotes element.
 *
 * @private
 */
export function isFootnotes(modelElement) {
  return !!modelElement && modelElement.is('element', 'footnotes');
}

/**
 * Checks if view element is <footnotes> element.
 *
 * @param  {module:engine/view/element~Element} viewElement
 *   The view element.
 * @return {boolean}
 *   A boolean indicating if the element is a <footnotes> element.
 *
 * @private
 */
export function isFootnotesWidget(viewElement) {
  return isWidget(viewElement) && !!viewElement.getCustomProperty('footnotes');
}

/**
 * Gets `footnotes` element from selection.
 *
 * @param  {module:engine/model/selection~Selection|module:engine/model/documentselection~DocumentSelection} selection
 *   The current selection.
 * @return {module:engine/model/element~Element|null}
 *   The `footnotes` element which could be either the current selected an
 *   ancestor of the selection. Returns null if the selection has no Drupal
 *   Media element.
 *
 * @private
 */
export function getClosestSelectedFootnotesElement(selection) {
  const selectedElement = selection.getSelectedElement();

  return isFootnotes(selectedElement)
    ? selectedElement
    : selection.getFirstPosition().findAncestor('footnotes');
}

/**
 * Gets selected Drupal Media widget if only Drupal Media is currently selected.
 *
 * @param  {module:engine/model/selection~Selection} selection
 *   The current selection.
 * @return {module:engine/view/element~Element|null}
 *   The currently selected Drupal Media widget or null.
 *
 * @private
 */
export function getClosestSelectedFootnotesWidget(selection) {
  const viewElement = selection.getSelectedElement();
  if (viewElement && isFootnotesWidget(viewElement)) {
    return viewElement;
  }

  let { parent } = selection.getFirstPosition();

  while (parent) {
    if (parent.is('element') && isFootnotesWidget(parent)) {
      return parent;
    }

    parent = parent.parent;
  }

  return null;
}

/**
 * Checks if value is a JavaScript object.
 *
 * This will return true for any type of JavaScript object. (e.g. arrays,
 * functions, objects, regexes, new Number(0), and new String(''))
 *
 * @param {*} value
 *   Value to check.
 * @return {boolean}
 *   True if value is an object, else false.
 */
export function isObject(value) {
  const type = typeof value;
  return value != null && (type === 'object' || type === 'function');
}

/**
 * Gets the preview container element from the media element.
 *
 * @param  {Iterable.<module:engine/view/element~Element>} children
 *   The child elements.
 * @return {null|module:engine/view/element~Element}
 *   The preview child element if available.
 */
export function getPreviewContainer(children) {
  // eslint-disable-next-line no-restricted-syntax
  for (const child of children) {
    if (child.hasAttribute('data-footnotes-preview')) {
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
 * Gets model attribute key based on Drupal Element Style group.
 *
 * @example
 *    Example: 'align' -> 'drupalElementStyleAlign'
 *
 * @param  {string} group
 *   The name of the group (ex. 'align', 'viewMode').
 * @return {string}
 *   Model attribute key.
 *
 * @internal
 */
export function groupNameToModelAttributeKey(group) {
  // Manipulate string to have first letter capitalized to append in camel case.
  const capitalizedFirst = group[0].toUpperCase() + group.substring(1);
  return `drupalElementStyle${capitalizedFirst}`;
}
