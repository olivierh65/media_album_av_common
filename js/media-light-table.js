(function (Drupal, once) {
  'use strict';

  /**
   * Initialize drag and drop for media light table using dragula.
   * Allows moving media within the same group or to different groups.
   */
  Drupal.behaviors.mediaLightTableDragula = {
    attach: function (context) {
      once('media-light-table-dragula', '.light-table-content', context).forEach(function (content) {
        if (typeof dragula === 'undefined') {
          console.warn('Dragula library not loaded');
          return;
        }

        // Get all media grids within this content area
        var grids = Array.from(content.querySelectorAll('.media-grid'));

        if (grids.length === 0) {
          return;
        }

        // Initialize dragula with all grids - allows dragging between groups
        var drake = dragula(grids, {
          moves: function (el, container, handle) {
            // MUST be a media-item to be draggable
            if (!el.classList.contains('media-item')) {
              return false;
            }
            
            // MUST be clicking on the SVG drag handle ONLY
            // First check: is the handle itself an SVG element or child of SVG?
            if (handle.tagName === 'svg') {
              // Direct click on SVG
              var svg = handle.closest('.media-album-light-table__handle svg');
              return svg ? true : false;
            }
            
            if (handle.tagName === 'line' || handle.tagName === 'polyline') {
              // Click on SVG child element
              var svg = handle.closest('.media-album-light-table__handle svg');
              return svg ? true : false;
            }
            
            // Check if handle is inside the SVG
            var svg = handle.closest('.media-album-light-table__handle svg');
            if (svg) {
              return true;
            }
            
            // All other cases: NOT draggable
            return false;
          },
          classes: {
            drag: 'media-item--drag',
            mirror: 'media-item--mirror',
            over: 'media-grid--over',
            transit: 'media-item--transit'
          },
          accepts: function (el, target, source, sibling) {
            // Accept drops in any media-grid (including empty ones)
            return target && target.classList && target.classList.contains('media-grid');
          },
          revertOnSpill: true,
          removeOnSpill: false,
          direction: 'horizontal',
          mirrorContainer: document.body,
          copySortSource: false,
          copy: false,
          delay: 200
        });

        // Listen to drag events
        drake.on('drop', function (el, target, source, sibling) {
          // Update the group and album information if moved to a different group
          updateMediaGroupInfo(el, target);
          updateAllMediaOrder(content);
          enableSaveButton(content);
        });

        drake.on('cancel', function (el) {
          updateAllMediaOrder(content);
        });

        // Store the drake instance on the content for later use
        content.dragulaInstance = drake;
      });
    }
  };

  /**
   * Get current media order from a specific grid.
   */
  function getMediaOrder(grid) {
    var order = [];
    var items = grid.querySelectorAll('.media-item');
    items.forEach(function (item) {
      var mediaId = item.getAttribute('data-media-id');
      if (mediaId) {
        order.push(mediaId);
      }
    });
    return order;
  }

  /**
   * Get all media across all groups and their current group/album association.
   */
  function getAllMediaOrder(content) {
    var mediaData = [];
    var groups = content.querySelectorAll('.media-album-light-table__group-container');

    groups.forEach(function (group, groupIndex) {
      var groupTitle = group.querySelector('.media-album-light-table__group-title');
      var groupName = groupTitle ? groupTitle.textContent : 'Group ' + (groupIndex + 1);

      // Note: .media-album-light-table__grid is the album container and media-grid
      var albums = group.querySelectorAll('.media-album-light-table__grid');
      albums.forEach(function (album, albumIndex) {
        var albumTitle = album.querySelector('.album-title');
        var albumName = albumTitle ? albumTitle.textContent : 'Album ' + (albumIndex + 1);

        // album contains media items
        var mediaItems = album.querySelectorAll('.media-item');
        mediaItems.forEach(function (item) {
          var mediaId = item.getAttribute('data-media-id');
          if (mediaId) {
            mediaData.push({
              id: mediaId,
              group: groupName,
              album: albumName,
              groupIndex: groupIndex,
              albumIndex: albumIndex
            });
          }
        });
      });
    });

    return mediaData;
  }

  /**
   * Update group and album information for a moved media item.
   */
  function updateMediaGroupInfo(element, targetGrid) {
    // Find the album container (media-grid) that contains this grid
    var albumContainer = targetGrid.closest('.media-album-light-table__grid');
    if (!albumContainer) {
      console.warn('Could not find album container for target grid');
      return;
    }

    // Update the data attribute with new group and album info
    var groupContainer = albumContainer.closest('.media-album-light-table__group-container');
    if (!groupContainer) {
      console.warn('Could not find media group container');
      return;
    }

    var groupIndex = Array.from(groupContainer.parentElement.children).filter(function(child) {
      return child.classList.contains('media-album-light-table__group-container');
    }).indexOf(groupContainer);

    var siblingAlbums = Array.from(albumContainer.parentElement.children).filter(function(child) {
      return child.classList.contains('media-album-light-table__grid');
    });
    var albumIndex = siblingAlbums.indexOf(albumContainer);

    element.setAttribute('data-group-index', groupIndex);
    element.setAttribute('data-album-index', albumIndex);

    console.log('Updated media group info:', {groupIndex: groupIndex, albumIndex: albumIndex});
  }

  /**
   * Update the hidden selected-ids input with all media order.
   */
  function updateAllMediaOrder(content) {
    var mediaOrder = getAllMediaOrder(content);
    // Find the parent wrapper (the main div containing everything)
    var wrapper = content.parentElement;
    var hiddenInput = wrapper ? wrapper.querySelector('.selected-ids') : null;

    if (hiddenInput) {
      var ids = mediaOrder.map(function (item) { return item.id; });
      hiddenInput.value = ids.join(',');
    }
  }

  /**
   * Update the hidden selected-ids input when order changes.
   */
  function updateMediaOrder(grid) {
    var order = getMediaOrder(grid);
    var container = grid.closest('.media-light-table-wrapper');
    if (container) {
      var hiddenInput = container.querySelector('.selected-ids');
      if (hiddenInput) {
        hiddenInput.value = order.join(',');
      }
    }
  }

  /**
   * Enable the save button.
   */
  function enableSaveButton(context) {
    var content = context.classList && context.classList.contains('light-table-content')
      ? context
      : context.closest('.light-table-content');

    if (!content) {
      return;
    }

    var wrapper = content.closest('div[class*="light-table"]');
    if (wrapper) {
      var saveBtn = wrapper.querySelector('.save-order');
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.classList.add('has-changes');
      }
    }
  }

  /**
   * Handle select all checkbox.
   */
  Drupal.behaviors.mediaLightTableSelectAll = {
    attach: function (context) {
      once('media-light-table-select-all', '.light-table-header', context).forEach(function (header) {
        var selectAllBtn = header.querySelector('.select-all');
        var deselectAllBtn = header.querySelector('.deselect-all');
        var container = header.closest('.media-light-table-wrapper');

        if (selectAllBtn && container) {
          selectAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            container.querySelectorAll('.media-select').forEach(function (checkbox) {
              checkbox.checked = true;
              checkbox.closest('.media-item').classList.add('selected');
            });
            updateMediaOrder(container.querySelector('.media-grid'));
            enableSaveButton(container.querySelector('.media-grid'));
          });
        }

        if (deselectAllBtn && container) {
          deselectAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            container.querySelectorAll('.media-select').forEach(function (checkbox) {
              checkbox.checked = false;
              checkbox.closest('.media-item').classList.remove('selected');
            });
            updateMediaOrder(container.querySelector('.media-grid'));
            enableSaveButton(container.querySelector('.media-grid'));
          });
        }
      });
    }
  };

  /**
   * Handle individual checkbox selections.
   */
  Drupal.behaviors.mediaLightTableCheckboxes = {
    attach: function (context) {
      once('media-light-table-checkbox', '.media-select', context).forEach(function (checkbox) {
        checkbox.addEventListener('change', function (e) {
          var mediaItem = e.target.closest('.media-item');
          var grid = mediaItem.closest('.media-grid');

          if (e.target.checked) {
            mediaItem.classList.add('selected');
          } else {
            mediaItem.classList.remove('selected');
          }

          if (grid) {
            updateMediaOrder(grid);
            enableSaveButton(grid);
          }
        });
      });
    }
  };

  /**
   * Handle save order button.
   */
  Drupal.behaviors.mediaLightTableSaveOrder = {
    attach: function (context) {
      once('media-light-table-save', '.save-order', context).forEach(function (saveBtn) {
        saveBtn.addEventListener('click', function (e) {
          e.preventDefault();

          var wrapper = saveBtn.closest('[data-grouping-criteria]');
          if (!wrapper) {
            console.error('Could not find media light table wrapper');
            return;
          }

          // Collect complete media data with groups
          var saveData = collectMediaSaveData(wrapper);

          // Show feedback
          saveBtn.textContent = 'Saving...';
          saveBtn.disabled = true;

          // Send to server
          sendMediaOrderToServer(saveData, wrapper, saveBtn);
        });
      });
    }
  };

  /**
   * Collect all media data with their current groups and grouping criteria.
   */
  function collectMediaSaveData(wrapper) {
    var groupingCriteriaStr = wrapper.getAttribute('data-grouping-criteria');
    var groupingCriteria = [];

    try {
      groupingCriteria = JSON.parse(groupingCriteriaStr || '[]');
    } catch (e) {
      console.warn('Could not parse grouping criteria', e);
    }

    var viewId = wrapper.getAttribute('data-view-id');
    var displayId = wrapper.getAttribute('data-display-id');

    var mediaList = [];
    var mediaItems = wrapper.querySelectorAll('.media-item');

    mediaItems.forEach(function (item) {
      var mediaData = {
        id: item.getAttribute('data-media-id'),
        group_title: item.getAttribute('data-group-title'),
        album_title: item.getAttribute('data-album-title'),
        group_index: parseInt(item.getAttribute('data-group-index') || 0),
        album_index: parseInt(item.getAttribute('data-album-index') || 0)
      };

      // Add subgroup info if present
      var subgroupTitle = item.getAttribute('data-subgroup-title');
      if (subgroupTitle) {
        mediaData.subgroup_title = subgroupTitle;
        mediaData.subgroup_index = parseInt(item.getAttribute('data-subgroup-index') || 0);
      }

      mediaList.push(mediaData);
    });

    return {
      view_id: viewId,
      display_id: displayId,
      grouping_criteria: groupingCriteria,
      media: mediaList
    };
  }

  /**
   * Send the collected media order data to the server.
   */
  function sendMediaOrderToServer(saveData, wrapper, saveBtn) {
    // Prepare AJAX request
    var xhr = new XMLHttpRequest();
    var endpoint = '/media-album-av-common/save-media-order';

    console.log('Sending media order data:', saveData);

    xhr.open('POST', endpoint, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    // Get CSRF token from Drupal
    // Try different methods to get the CSRF token
    var csrfToken = null;

    // Method 1: Check meta tag
    var tokenMeta = document.querySelector('meta[name="csrf-token"]');
    if (tokenMeta) {
      csrfToken = tokenMeta.getAttribute('content');
    }

    // Method 2: Check hidden input (common in forms)
    if (!csrfToken) {
      var tokenInput = document.querySelector('input[name="csrf_token"]');
      if (tokenInput) {
        csrfToken = tokenInput.value;
      }
    }

    // Method 3: Try Drupal settings
    if (!csrfToken && typeof Drupal !== 'undefined' && Drupal.settings) {
      csrfToken = Drupal.settings.csrfToken;
    }

    if (csrfToken) {
      xhr.setRequestHeader('X-CSRF-Token', csrfToken);
      console.log('CSRF token set');
    } else {
      console.warn('No CSRF token found');
    }

    xhr.onload = function () {
      console.log('Server response status:', xhr.status);
      console.log('Server response:', xhr.responseText);

      if (xhr.status === 200 || xhr.status === 201) {
        try {
          var response = JSON.parse(xhr.responseText);
          if (response.success) {
            saveBtn.textContent = 'Saved!';
            saveBtn.classList.add('success');
            saveBtn.classList.remove('error');
            saveBtn.disabled = false;
            console.log('Media order saved successfully');
            setTimeout(function () {
              saveBtn.textContent = 'Save order';
              saveBtn.classList.remove('has-changes', 'success');
              saveBtn.disabled = false;
            }, 2000);
          } else {
            showErrorMessage(saveBtn, response.message || 'Save failed');
          }
        } catch (e) {
          console.error('Error parsing response', e);
          showErrorMessage(saveBtn, 'Invalid response from server');
        }
      } else {
        console.error('Server error status:', xhr.status);
        showErrorMessage(saveBtn, 'Server error: ' + xhr.status);
      }
    };

    xhr.onerror = function () {
      console.error('Network error sending request');
      showErrorMessage(saveBtn, 'Network error');
    };

    xhr.onabort = function () {
      console.warn('Request was aborted');
      showErrorMessage(saveBtn, 'Request was cancelled');
    };

    xhr.send(JSON.stringify(saveData));

    // Trigger custom event for external handlers
    var event = new CustomEvent('mediaLightTableOrderChanged', {
      detail: saveData
    });
    wrapper.dispatchEvent(event);
  }

  /**
   * Show error message on save button.
   */
  function showErrorMessage(saveBtn, message) {
    saveBtn.textContent = 'Error: ' + message;
    saveBtn.classList.add('error');
    saveBtn.disabled = false;
    setTimeout(function () {
      saveBtn.textContent = 'Save order';
      saveBtn.classList.remove('has-changes', 'error');
    }, 3000);
  }

})(Drupal, once);
