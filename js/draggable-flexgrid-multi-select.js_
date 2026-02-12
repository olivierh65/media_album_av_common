/**
 * @file
 * Handles multi-select with Shift+click for media grid checkboxes.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.mediaDropMultiSelect = {
    attach: function (context) {
      // Initialize multi-select functionality once per form
      once('media-drop-multi-select', '.vbo-view-form', context).forEach(function (form) {
        var lastCheckedIndex = -1;

        // Use event delegation to handle checkbox clicks, including dynamically added ones
        form.addEventListener('click', function(e) {
          if (e.target && e.target.matches('input[type="checkbox"].js-vbo-checkbox')) {
            var checkbox = e.target;
            var checkboxes = Array.from(form.querySelectorAll('input[type="checkbox"].js-vbo-checkbox'));
            var currentIndex = checkboxes.indexOf(checkbox);

            if (e.shiftKey && lastCheckedIndex !== -1) {
              // Shift+click: select or deselect range
              var start = Math.min(lastCheckedIndex, currentIndex);
              var end = Math.max(lastCheckedIndex, currentIndex);
              var isChecking = checkbox.checked;

              for (var i = start; i <= end; i++) {
                checkboxes[i].checked = isChecking;
              }

              // Emit change event for each checkbox to update counters
              checkboxes.slice(start, end + 1).forEach(function(cb) {
                cb.dispatchEvent(new Event('change', { bubbles: true }));
              });
            } else {
              // Regular click: just update last checked index
              lastCheckedIndex = currentIndex;
            }
          }
        });
      });
    }
  };
})(Drupal, once);
