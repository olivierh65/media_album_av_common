/**
 * @file
 * Manages VBO (Views Bulk Operations) checkbox functionality and counter updates
 * for views using draggable-flexgrid layout.
 *
 * Features:
 * - Sync VBO checkbox state with counter display
 * - Update counter when checkboxes change
 * - Fix multipage selector summary display
 * - Dispatch custom events for external integration
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Behavior to handle VBO checkbox synchronization in draggable flexgrid.
   */
  Drupal.behaviors.draggableFlexGridVboSync = {
    attach(context) {
      // Attach listeners to all VBO checkboxes in draggable-flexgrid items
      once('draggable-flexgrid-vbo-sync', '.draggable-flexgrid__item .js-vbo-checkbox', context).forEach(cb => {
        cb.addEventListener('change', () => {
          document.dispatchEvent(new Event('draggable-flexgrid-vbo-changed'));
          updateVboCounter();
        });
      });

      // Also listen for header checkbox (select all)
      once('draggable-flexgrid-vbo-header', '.field--views-bulk-operations-bulk-form input[type="checkbox"]:not(.js-vbo-checkbox)', context).forEach(headerCb => {
        headerCb.addEventListener('change', () => {
          // Give a moment for VBO to update the individual checkboxes
          setTimeout(() => {
            document.dispatchEvent(new Event('draggable-flexgrid-vbo-changed'));
            updateVboCounter();
          }, 50);
        });
      });

      // Initialize counter on attach
      updateVboCounter();
    }
  };

  /**
   * Update the VBO counter display based on checked checkboxes.
   */
  function updateVboCounter() {
    // Count checked individual item checkboxes (not the header checkbox)
    const checkedItems = document.querySelectorAll('.draggable-flexgrid__item .js-vbo-checkbox:checked').length;

    // Find the VBO status display area (usually in the header or form wrapper)
    const statusElements = document.querySelectorAll('.js-views-bulk-actions-status');

    statusElements.forEach(status => {
      if (checkedItems === 0) {
        status.textContent = Drupal.t('No items selected');
        status.classList.remove('has-selection');
      } else {
        status.textContent = Drupal.t('@count item(s) selected', { '@count': checkedItems });
        status.classList.add('has-selection');
      }
    });

    // Dispatch custom event for external listeners
    document.dispatchEvent(new CustomEvent('draggable-flexgrid-vbo-count-updated', {
      detail: { count: checkedItems }
    }));
  }

  /**
   * Behavior to fix the VBO multipage selector summary display.
   */
  Drupal.behaviors.draggableFlexGridVboSummaryFix = {
    attach(context) {
      once('draggable-flexgrid-vbo-summary-fix', '.vbo-multipage-selector summary', context).forEach(summary => {
        let observer;

        // Function to update summary based on actual checkbox state
        function updateSummary() {
          const count = document.querySelectorAll('.draggable-flexgrid__item .js-vbo-checkbox:checked').length;

          if (count === 0) {
            // Temporarily disconnect to avoid infinite loops
            if (observer) {
              observer.disconnect();
            }

            summary.textContent = Drupal.t('0 item(s) selected');

            // Reconnect observer
            if (observer) {
              observer.observe(summary, { childList: true, characterData: true, subtree: true });
            }
          }
        }

        // Create MutationObserver to fix summary when it changes
        observer = new MutationObserver(updateSummary);

        // Start observing changes
        observer.observe(summary, { childList: true, characterData: true, subtree: true });

        // Initialize summary on page load
        updateSummary();
      });
    }
  };

  /**
   * Behavior to handle VBO form submission in draggable flexgrid context.
   */
  Drupal.behaviors.draggableFlexGridVboSubmit = {
    attach(context) {
      // Find the VBO operations form if it exists
      once('draggable-flexgrid-vbo-submit', '.views-form', context).forEach(form => {
        const submitButtons = form.querySelectorAll('button[name="op"], input[type="submit"][name="op"]');

        submitButtons.forEach(button => {
          button.addEventListener('click', function(e) {
            const checkedCount = document.querySelectorAll('.draggable-flexgrid__item .js-vbo-checkbox:checked').length;

            // Dispatch event before submission
            document.dispatchEvent(new CustomEvent('draggable-flexgrid-vbo-before-submit', {
              detail: {
                action: this.value,
                checkedCount: checkedCount,
                checkedIds: Array.from(document.querySelectorAll('.draggable-flexgrid__item .js-vbo-checkbox:checked'))
                  .map(cb => cb.closest('.draggable-flexgrid__item')?.getAttribute('data-entity-id'))
                  .filter(id => id !== null)
              }
            }));
          });
        });
      });
    }
  };

  /**
   * Behavior to update checkbox visual state when VBO selection changes.
   */
  Drupal.behaviors.draggableFlexGridVboVisualUpdate = {
    attach(context) {
      document.addEventListener('draggable-flexgrid-vbo-changed', () => {
        // Update visual classes on draggable items based on checkbox state
        document.querySelectorAll('.draggable-flexgrid__item').forEach(item => {
          const checkbox = item.querySelector('.js-vbo-checkbox');
          if (checkbox && checkbox.checked) {
            item.classList.add('is-selected');
          } else {
            item.classList.remove('is-selected');
          }
        });
      });
    }
  };

})(Drupal, once);

