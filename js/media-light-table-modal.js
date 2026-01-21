/**
 * @file
 * Media Light Table Modal functionality.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Initialize media light table modal.
   */
  Drupal.behaviors.mediaLightTableModal = {
    attach: function (context) {
      // Get all zoom triggers.
      const triggers = once(
        'media-light-table-modal-trigger',
        '.media-album-light-table__zoom-trigger',
        context
      );

      const modal = document.getElementById('media-light-table-modal');
      const modalImage = document.getElementById('modal-media-image');
      const modalClose = document.querySelector('.media-album-light-table__modal-close');
      const modalOverlay = document.querySelector('.media-album-light-table__modal-overlay');

      if (!modal || !modalImage) {
        return;
      }

      /**
       * Open modal with image.
       */
      function openModal(imageSrc, imageAlt) {
        modalImage.src = imageSrc;
        modalImage.alt = imageAlt;
        modal.removeAttribute('hidden');
        modal.focus();
        document.body.style.overflow = 'hidden';
      }

      /**
       * Close modal.
       */
      function closeModal() {
        modal.setAttribute('hidden', '');
        document.body.style.overflow = '';
      }

      /**
       * Handle zoom trigger clicks.
       */
      triggers.forEach(function (trigger) {
        trigger.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          const imageSrc = this.getAttribute('data-image-src');
          const imageAlt = this.getAttribute('data-image-alt');
          if (imageSrc) {
            openModal(imageSrc, imageAlt || 'Media preview');
          }
        });

        // Allow keyboard access.
        trigger.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            e.stopPropagation();
            const imageSrc = this.getAttribute('data-image-src');
            const imageAlt = this.getAttribute('data-image-alt');
            if (imageSrc) {
              openModal(imageSrc, imageAlt || 'Media preview');
            }
          }
        });
      });

      // Close modal on close button click.
      if (modalClose) {
        modalClose.addEventListener('click', closeModal);
      }

      // Close modal on overlay click.
      if (modalOverlay) {
        modalOverlay.addEventListener('click', closeModal);
      }

      // Close modal on Escape key.
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hasAttribute('hidden')) {
          closeModal();
        }
      });
    }
  };

})(Drupal, once);
