// eslint-disable-next-line import/no-unresolved
import { DomEventObserver } from 'ckeditor5/src/engine';

/**
 * Ckeditor5 doesn't support double click out of the box.
 * Register it here so we can use it.
 */
export default class DoubleClickObserver extends DomEventObserver {
  constructor(view) {
    super(view);
    this.domEventType = 'dblclick';
  }

  onDomEvent(domEvent) {
    this.fire(domEvent.type, domEvent);
  }
}
