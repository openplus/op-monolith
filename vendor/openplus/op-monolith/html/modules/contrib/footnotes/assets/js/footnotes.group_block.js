/**
 * @file
 * This is a JS option for grouping together all footnotes.
 *
 * Use the PHP option by default, but when loading footnotes
 * for example in lazy-loaded areas, they may not be processed
 * and this JS solution may be needed.
 *
 * When using this option, you must *not* disable the footnotes
 * references output via the Text Format -> Filters configuration. The
 * existing footnotes references output provides a fallback in case
 * JS is disabled. This JS is also most efficient when it can just
 * move the reference area rather than building a new area.
 *
 * This should not be run within once() as it must respond to
 * loading of new content, like content loaded via the lazy builder.
 */
Drupal.behaviors.footnotesGroup = {
  /**
   * @param {mixed} context The context from Drupal.
   */
  attach(context) {
    // The target location to move all sections to.
    // This may not be loaded immediately as it may be lazy-built. In
    // that case, this function will be called again and processing
    // will continue when it is available.
    const footnotesGroup = context.querySelector('#footnotes_group');
    if (footnotesGroup) {
      // The existing footnote reference section(s) that are not coming from the block.
      const footnotes = context.querySelectorAll(
        '.js-footnotes:not(#footnotes_group) .js-footnote-reference',
      );
      if (footnotes.length > 0) {
        // Move every footnote to the block.
        footnotes.forEach((footnote) => footnotesGroup.appendChild(footnote));

        // Remove every footnote reference list except the one in the block.
        const allFootnoteLists = context.querySelectorAll('.js-footnotes');
        allFootnoteLists.forEach((list) => {
          if (list.id !== 'footnotes_group') {
            list.remove();
          }
        });
      }

      // Ensure that auto-incremented numbers are set 1, 2, 3, etc.
      // Manual numbering combined with auto-numbering is not supported.
      // It is possible that we have for example 1, 2a, 2b, 2c, 3 as
      // backlinks, so this also needs to support that.
      const autoNumberedFootnotes = document.querySelectorAll(
        '.js-footnote-reference',
      );
      let counter = 1;
      autoNumberedFootnotes.forEach((footnote) => {
        // If there are multiple citations to this same reference, there
        // will be multiple backlinks.
        if (footnote.classList.contains('is-multiple')) {
          let subCounter = 'a';
          footnote.querySelectorAll('.js-is-auto').forEach((anchor) => {
            anchor.textContent = counter + subCounter;

            // Move to the next letter.
            subCounter = String.fromCharCode(subCounter.charCodeAt(0) + 1);
          });
        } else {
          // There is a single citation to this reference.
          footnote.querySelector('.js-is-auto').textContent = counter;
        }
        counter += 1;
      });

      // Hide empty wrapper if we have nothing to put in it.
      // Check that it has no items already as this JS may be called multiple
      // times for example if lazy built content subsequently loads.
      if (
        footnotesGroup.querySelectorAll('.js-footnote-reference').length > 0
      ) {
        footnotesGroup.style.display = 'block';
      } else {
        footnotesGroup.style.display = 'none';
      }
    }
  },
};
