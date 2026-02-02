/**
 * @file
 * Media Light Table Edit Modal functionality.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Initialize media light table edit modal.
   */
  Drupal.behaviors.mediaLightTableEditModal = {
    attach: function (context) {
      // Get all edit triggers.
      const triggers = once(
        'media-light-table-edit-trigger',
        '.media-light-table-edit-trigger',
        context
      );

      if (!triggers || triggers.length === 0) {
        return;
      }

      /**
       * Open edit modal with media edit form loaded via AJAX.
       */
      function openEditModal(mediaId) {
        // Create modal container if not exists
        let modal = document.getElementById('media-light-table-edit-modal');
        if (!modal) {
          modal = createEditModal();
          document.body.appendChild(modal);
        }

        const modalFrame = modal.querySelector('.media-light-table-edit-modal__frame');

        // Clear previous content and show loading state
        modalFrame.innerHTML = '<div class="media-light-table-edit-modal__loading">Loading...</div>';

        // Load the media edit form via AJAX (without page wrapper)
        const editUrl = '/media/' + mediaId + '/edit?view=modal';

        fetch(editUrl, {
          method: 'GET',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
          }
        })
        .then(response => {
          if (!response.ok) {
            // Try alternative URL format without query param
            return fetch('/media/' + mediaId + '/edit', {
              method: 'GET',
              headers: {
                'X-Requested-With': 'XMLHttpRequest',
              }
            });
          }
          return response;
        })
        .then(response => response.text())
        .then(html => {
          // Remove loading indicator
          const loading = modalFrame.querySelector('.media-light-table-edit-modal__loading');
          if (loading) {
            loading.remove();
          }

          // Extract just the main content area (remove page wrapper, menus, etc)
          const tempDiv = document.createElement('div');
          tempDiv.innerHTML = html;

          // Try to find the main content area
          let content = tempDiv.querySelector('main') ||
                       tempDiv.querySelector('[role="main"]') ||
                       tempDiv.querySelector('.main-content') ||
                       tempDiv.querySelector('.region-content') ||
                       tempDiv.querySelector('article') ||
                       tempDiv;

          // Clear modal frame and add content
          modalFrame.innerHTML = '';

          // Create a wrapper for the form content
          const formWrapper = document.createElement('div');
          formWrapper.className = 'media-light-table-edit-modal__form-content';
          formWrapper.innerHTML = content.innerHTML;

          modalFrame.appendChild(formWrapper);

          // Attach Drupal behaviors to newly added content
          Drupal.attachBehaviors(formWrapper);
        })
        .catch(error => {
          console.error('Error loading form:', error);
          const loading = modalFrame.querySelector('.media-light-table-edit-modal__loading');
          if (loading) {
            loading.remove();
          }

          modalFrame.innerHTML = '<div class="media-light-table-edit-modal__error">Error loading edit form. <a href="/media/' + mediaId + '/edit" target="_blank" class="button">Open in new window</a></div>';
        });

        // Show modal
        modal.removeAttribute('hidden');
        modal.focus();
        document.body.style.overflow = 'hidden';
      }

      /**
       * Create edit modal structure.
       */
      function createEditModal() {
        const modal = document.createElement('div');
        modal.id = 'media-light-table-edit-modal';
        modal.className = 'media-light-table-edit-modal';
        modal.setAttribute('hidden', '');

        modal.innerHTML = `
          <div class="media-light-table-edit-modal__overlay"></div>
          <div class="media-light-table-edit-modal__content">
            <div class="media-light-table-edit-modal__header">
              <h2 class="media-light-table-edit-modal__title">Edit Media</h2>
              <button type="button" class="media-light-table-edit-modal__close" title="Close" aria-label="Close edit form">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="18" y1="6" x2="6" y2="18"></line>
                  <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
              </button>
            </div>
            <div class="media-light-table-edit-modal__frame"></div>
          </div>
        `;

        // Handle close button
        const closeBtn = modal.querySelector('.media-light-table-edit-modal__close');
        closeBtn.addEventListener('click', function() {
          closeEditModal();
        });

        // Handle overlay click
        const overlay = modal.querySelector('.media-light-table-edit-modal__overlay');
        overlay.addEventListener('click', function() {
          closeEditModal();
        });

        return modal;
      }

      /**
       * Close edit modal.
       */
      function closeEditModal() {
        const modal = document.getElementById('media-light-table-edit-modal');
        if (modal) {
          modal.setAttribute('hidden', '');
          document.body.style.overflow = '';
        }
      }

      /**
       * Handle edit trigger clicks.
       */
      triggers.forEach(function (trigger) {
        trigger.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          const mediaId = this.getAttribute('data-media-id');
          if (mediaId) {
            openEditModal(mediaId);
          }
        });

        // Allow keyboard access.
        trigger.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            e.stopPropagation();
            const mediaId = this.getAttribute('data-media-id');
            if (mediaId) {
              openEditModal(mediaId);
            }
          }
        });
      });

      // Close modal on Escape key.
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          const modal = document.getElementById('media-light-table-edit-modal');
          if (modal && !modal.hasAttribute('hidden')) {
            closeEditModal();
          }
        }
      });
    }
  };

})(Drupal, once);
