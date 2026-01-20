(function (Drupal, once) {
  'use strict';

  /**
   * Handle collapse/expand for filters and grouping controls.
   */
  Drupal.behaviors.mediaLightTableControlsToggle = {
    attach: function (context) {
      once('media-light-table-toggle', '[data-target="#media-controls"]', context).forEach(function (toggleBtn) {
        toggleBtn.addEventListener('click', function (e) {
          e.preventDefault();
          var controlsId = toggleBtn.getAttribute('data-target');
          var controlsPanel = document.querySelector(controlsId);

          if (controlsPanel) {
            controlsPanel.classList.toggle('show');
            toggleBtn.setAttribute('aria-expanded', controlsPanel.classList.contains('show'));
          }
        });
      });
    }
  };

  /**
   * Direct handler for Apply/Reset grouping buttons.
   */
  Drupal.behaviors.mediaLightTableGroupingButtons = {
    attach: function (context) {
      once('media-light-table-apply-btn', '.apply-grouping', context).forEach(function (applyBtn) {
        applyBtn.addEventListener('click', function (e) {
          e.preventDefault();
          console.log('Apply grouping button clicked directly');

          // Find the grouping controls
          var groupingCtrl = applyBtn.closest('.grouping-actions').previousElementSibling;
          while (groupingCtrl && !groupingCtrl.classList.contains('grouping-controls')) {
            groupingCtrl = groupingCtrl.previousElementSibling;
          }

          if (!groupingCtrl) {
            // Try another approach: go up to control-section then find grouping-controls
            groupingCtrl = applyBtn.closest('.control-section').querySelector('.grouping-controls');
          }

          if (groupingCtrl) {
            applyCustomGrouping(groupingCtrl);
          } else {
            console.error('Could not find grouping-controls from apply button');
          }
        });
      });

      once('media-light-table-reset-btn', '.reset-grouping', context).forEach(function (resetBtn) {
        resetBtn.addEventListener('click', function (e) {
          e.preventDefault();
          console.log('Reset grouping button clicked directly');

          var groupingCtrl = resetBtn.closest('.control-section').querySelector('.grouping-controls');

          if (groupingCtrl) {
            resetGrouping(groupingCtrl);
          } else {
            console.error('Could not find grouping-controls from reset button');
          }
        });
      });
    }
  };

  /**
   * Handle media type filtering.
   */
  Drupal.behaviors.mediaLightTableFilters = {
    attach: function (context) {
      once('media-light-table-filters', '.media-type-filters', context).forEach(function (filterContainer) {
        var checkboxes = filterContainer.querySelectorAll('.media-filter');
        // Find the light table content - go up to the main wrapper then find light-table-content
        var wrapper = filterContainer.closest('[data-view-id]');
        var lightTableContent = wrapper ? wrapper.querySelector('.light-table-content') : null;

        if (!lightTableContent) {
          console.warn('Could not find light table content');
          return;
        }

        checkboxes.forEach(function (checkbox) {
          checkbox.addEventListener('change', function () {
            applyMediaTypeFilters(filterContainer, lightTableContent);
          });
        });
      });
    }
  };

  /**
   * Handle grouping level selection.
   */
  Drupal.behaviors.mediaLightTableGrouping = {
    attach: function (context) {
      once('media-light-table-grouping', '.grouping-controls', context).forEach(function (groupingCtrl) {
        var selects = groupingCtrl.querySelectorAll('.grouping-field');

        // Show/hide next level based on current selection
        selects.forEach(function (select) {
          select.addEventListener('change', function () {
            updateGroupingLevels(groupingCtrl);
          });
        });
      });
    }
  };

  /**
   * Apply media type filters to show/hide media.
   */
  function applyMediaTypeFilters(filterContainer, lightTableContent) {
    if (!lightTableContent) {
      console.warn('Light table content not found');
      return;
    }

    var selectedTypes = [];
    filterContainer.querySelectorAll('.media-filter:checked').forEach(function (checkbox) {
      selectedTypes.push(checkbox.value);
    });

    var mediaItems = lightTableContent.querySelectorAll('.media-item');
    var visibleCount = 0;

    mediaItems.forEach(function (item) {
      var mimeType = item.getAttribute('data-mime-type');
      var shouldShow = selectedTypes.length === 0 || selectedTypes.includes(mimeType);

      if (shouldShow) {
        item.style.display = '';
        visibleCount++;
      } else {
        item.style.display = 'none';
      }
    });

    console.log('Filtered to ' + visibleCount + ' items');
  }

  /**
   * Update grouping levels visibility.
   */
  function updateGroupingLevels(groupingCtrl) {
    var selects = groupingCtrl.querySelectorAll('.grouping-field');
    var levels = groupingCtrl.querySelectorAll('.grouping-level');

    // Hide all levels first
    levels.forEach(function (level) {
      level.style.display = 'none';
    });

    // Show first level and any subsequent ones that have selections
    var showNextLevel = true;
    selects.forEach(function (select, index) {
      if (showNextLevel) {
        levels[index].style.display = 'block';
        // Show next level if this one has a value
        if (select.value === '') {
          showNextLevel = false;
        }
      }
    });
  }

  /**
   * Listener for grouping changes to save to server (Twig handles display).
   */
  Drupal.behaviors.mediaLightTableGroupingApplier = {
    attach: function (context) {
      once('media-light-table-grouping-applier', '[data-view-id]', context).forEach(function (wrapper) {
        wrapper.addEventListener('mediaLightTableGroupingChanged', function (e) {
          var groupingConfig = e.detail.grouping_criteria;
          saveGroupingToServer(wrapper, groupingConfig);
        });

        wrapper.addEventListener('mediaLightTableGroupingReset', function (e) {
          saveGroupingToServer(wrapper, []);
        });
      });
    }
  };
  /**
   * Apply custom grouping configuration.
   */
  function applyCustomGrouping(groupingCtrl) {
    var groupingConfig = [];
    var selects = groupingCtrl.querySelectorAll('.grouping-field');

    selects.forEach(function (select) {
      if (select.value !== '') {
        groupingConfig.push({
          level: parseInt(select.getAttribute('data-level')),
          field: select.value
        });
      }
    });

    if (groupingConfig.length === 0) {
      console.warn('No grouping levels selected');
      return;
    }

    var wrapper = groupingCtrl.closest('[data-view-id]');
    if (!wrapper) {
      console.error('Could not find light table wrapper');
      return;
    }

    // Trigger custom event for grouping applier behavior
    var event = new CustomEvent('mediaLightTableGroupingChanged', {
      detail: {
        grouping_criteria: groupingConfig
      }
    });
    wrapper.dispatchEvent(event);

    console.log('Applied custom grouping:', groupingConfig);
  }

  /**
   * Reset grouping to view defaults.
   */
  function resetGrouping(groupingCtrl) {
    var selects = groupingCtrl.querySelectorAll('.grouping-field');
    selects.forEach(function (select) {
      select.value = '';
    });

    updateGroupingLevels(groupingCtrl);

    var wrapper = groupingCtrl.closest('[data-view-id]');
    var event = new CustomEvent('mediaLightTableGroupingReset', {
      detail: {}
    });
    wrapper.dispatchEvent(event);

    console.log('Reset grouping to defaults');
  }

  /**
   * Save grouping configuration to server (which reloads the page).
   */
  function saveGroupingToServer(wrapper, groupingConfig) {
    var viewId = wrapper.getAttribute('data-view-id');
    var displayId = wrapper.getAttribute('data-display-id');

    if (!viewId || !displayId) {
      console.error('Missing view or display ID');
      return;
    }

    var data = {
      view_id: viewId,
      display_id: displayId,
      grouping_criteria: groupingConfig
    };

    var token = getCSRFToken();

    fetch('/media-album-av-common/apply-grouping', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': token
      },
      body: JSON.stringify(data)
    })
    .then(function (response) {
      if (!response.ok) {
        throw new Error('HTTP error, status = ' + response.status);
      }
      return response.json();
    })
    .then(function (result) {
      if (result.success) {
        console.log('Grouping saved to server:', result);
        // Reload the page to show the new grouping
        location.reload();
      } else {
        console.warn('Grouping save failed:', result.message);
      }
    })
    .catch(function (error) {
      console.error('Error saving grouping to server:', error);
    });
  }

  /**
   * Get CSRF token for AJAX requests.
   */
  function getCSRFToken() {
    // Try to get from meta tag
    var token = document.querySelector('meta[name="csrf-token"]');
    if (token) {
      return token.getAttribute('content');
    }

    // Try to get from input field
    var input = document.querySelector('input[name="csrf_token"]');
    if (input) {
      return input.value;
    }

    // Try from Drupal settings
    if (window.drupalSettings && window.drupalSettings.csrfToken) {
      return window.drupalSettings.csrfToken;
    }

    return '';
  }

})(Drupal, once);
