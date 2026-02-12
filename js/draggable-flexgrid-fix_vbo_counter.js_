/**
 * @file
 * Fixes the VBO (Views Bulk Operations) checkbox selection handling.
 *
 * Problem:
 * - Select all/Deselect all buttons don't properly sync checkboxes.
 * - VBO's own frontUi.js handles counter updates via AJAX.
 *
 * Solution:
 * - Ensure checkboxes properly trigger VBO's change events
 * - Let VBO handle all counter/summary updates via AJAX
 * - Only manage checkbox state synchronization
 */

(function (Drupal, once) {

  // Behavior to ensure proper checkbox selection handling
  Drupal.behaviors.vboCheckboxSync = {
    attach(context) {
      // Use event delegation to handle all checkboxes including dynamically added ones
      once('vbo-checkbox-sync', '.vbo-view-form', context).forEach(form => {

        // Handle Select all button
        const selectAllBtn = form.querySelector('.select-all');
        if (selectAllBtn) {
          selectAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const checkboxes = form.querySelectorAll('input[type="checkbox"].js-vbo-checkbox');
            checkboxes.forEach(cb => {
              if (!cb.checked) {
                cb.checked = true;
                // Let VBO's handler fire on change event
                cb.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
              }
            });
          });
        }

        // Handle Deselect all button
        const deselectAllBtn = form.querySelector('.deselect-all');
        if (deselectAllBtn) {
          deselectAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const checkboxes = form.querySelectorAll('input[type="checkbox"].js-vbo-checkbox');
            checkboxes.forEach(cb => {
              if (cb.checked) {
                cb.checked = false;
                // Let VBO's handler fire on change event
                cb.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
              }
            });
          });
        }
      });
    }
  };

})(Drupal, once);

})(Drupal, once);
