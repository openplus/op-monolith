/**
 * @file
 * This is a JS option for popping-up the reference.
 *
 * By default, users are jumped from the citation to the bottom of the page.
 * Although they can jump back via the backlink, the experience can be
 * jarring/disorienting. This loads the reference content into a dialog
 * as a progressive enhancement. If javascript is disabled, the default
 * functionality of the module remains in place.
 */
Drupal.behaviors.footnotesDialog = {
  /**
   * @param {mixed} context The context from Drupal.
   */
  attach(context) {
    const footnotesDialog = context.querySelector('#js-footnotes-dialog');
    if (footnotesDialog) {
      const footnotesDialogCitationNumber = footnotesDialog.querySelector(
        '#js-footnotes-dialog-citation-number',
      );
      const footnotesDialogText = footnotesDialog.querySelector(
        '#js-footnotes-dialog-text',
      );

      // Use arrow function syntax for event handlers
      const handleFootnoteCitationClick = (event) => {
        event.preventDefault();
        const href = event.currentTarget.getAttribute('href');
        const linkParts = href.split('#');
        const anchor = linkParts.pop();
        if (anchor) {
          const footnote = document.getElementById(anchor);
          if (footnote) {
            // Get the citation number.
            const citationNumber = footnote.innerHTML;
            if (footnotesDialogCitationNumber) {
              footnotesDialogCitationNumber.innerHTML = citationNumber;
            }

            // Get the text.
            const parentFootnoteReference = footnote.closest(
              '.js-footnote-reference',
            );
            const footnoteReferenceText = parentFootnoteReference.querySelector(
              '.js-footnote-reference-text',
            );
            const referenceText = footnoteReferenceText
              ? footnoteReferenceText.innerHTML
              : '';
            if (footnotesDialogText) {
              footnotesDialogText.innerHTML = referenceText;
            }

            footnotesDialog.showModal();

            // Prevent click within the dialog from closing it.
            const footnotesDialogForm = context.querySelector(
              '#js-footnotes-dialog-form',
            );
            footnotesDialogForm.addEventListener('click', (subEvent) =>
              subEvent.stopPropagation(),
            );

            // Check for reduced motion preference.
            const prefersReducedMotion = window.matchMedia(
              '(prefers-reduced-motion: reduce)',
            );
            if (!prefersReducedMotion || prefersReducedMotion.matches) {
              footnotesDialog.addEventListener(
                'click',
                () => footnotesDialog.close(),
                false,
              );
            } else {
              const handleCloseAnimationEnd = () => {
                footnotesDialog.classList.remove('hide');
                footnotesDialog.close();
                footnotesDialog.removeEventListener(
                  'animationend',
                  handleCloseAnimationEnd,
                  false,
                );
              };

              const handleClose = (subEvent) => {
                if (subEvent.target !== event.target) {
                  footnotesDialog.classList.add('hide');
                  footnotesDialog.addEventListener(
                    'animationend',
                    handleCloseAnimationEnd,
                    false,
                  );
                }
              };

              footnotesDialog.addEventListener('click', handleClose, false);

              footnotesDialogForm.addEventListener('submit', (subEvent) => {
                subEvent.preventDefault();
                handleClose(subEvent);
              });
            }
          }
        }
      };

      document.querySelectorAll('.js-footnote-citation').forEach((link) => {
        link.addEventListener('click', handleFootnoteCitationClick);
      });
    }
  },
};
