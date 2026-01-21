/**
 * @file
 * Media Light Table More Info Popup functionality.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Initialize media light table more info popups.
   */
  Drupal.behaviors.mediaLightTableMoreInfo = {
    attach: function (context) {
      // Get all details field wrappers.
      const wrappers = once(
        'media-light-table-more-info',
        '.media-album-light-table__group-4',
        context
      );

      wrappers.forEach(function (wrapper) {
        const button = wrapper.querySelector('.media-album-light-table__more-info-btn');
        const popup = wrapper.querySelector('.media-album-light-table__popup');

        if (!button || !popup) {
          return;
        }

        /**
         * Toggle popup visibility.
         */
        function togglePopup(e) {
          e.preventDefault();
          e.stopPropagation();
          const isOpen = wrapper.hasAttribute('data-open');
          if (isOpen) {
            wrapper.removeAttribute('data-open');
          } else {
            // Close other popups.
            document.querySelectorAll('.media-album-light-table__group-4[data-open]').forEach(function (w) {
              if (w !== wrapper) {
                w.removeAttribute('data-open');
              }
            });
            wrapper.setAttribute('data-open', '');
          }
        }

        button.addEventListener('click', togglePopup);

        // Close popup when clicking outside.
        document.addEventListener('click', function (e) {
          if (wrapper.hasAttribute('data-open') && !wrapper.contains(e.target)) {
            wrapper.removeAttribute('data-open');
          }
        });

        // Allow keyboard access.
        button.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') {
            togglePopup(e);
          }
        });

        // Close on Escape key.
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && wrapper.hasAttribute('data-open')) {
            wrapper.removeAttribute('data-open');
          }
        });
      });
    }
  };

})(Drupal, once);
