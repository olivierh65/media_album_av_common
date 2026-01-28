/**
 * @file
 * Media light table item selection management.
 *
 * Allows selecting media items by clicking on them:
 * - Click: toggle selection
 * - Shift+click: select range between last selected and current
 * - Ctrl/Cmd+click: toggle selection
 */

(function (Drupal, once) {

  // Store reorganization state per album group
  const reorganizationState = {};

  Drupal.behaviors.mediaLightTableSelection = {
    attach(context) {
      // Process each album view independently
      once('media-light-table-selection', '.light-table-content', context).forEach(function (albumView) {
        // Find all grids within this album view
        const allGrids = albumView.querySelectorAll('.media-grid.media-light-table-album-container');

        // Attach click listeners to all items in all grids
        const allItems = albumView.querySelectorAll('.media-light-table-media-item');

        allItems.forEach((item) => {
          item.addEventListener('click', function (e) {
            // Prevent triggering on children like zoom button or drag handle
            if (e.target.closest('.media-light-table-zoom-trigger') ||
                e.target.closest('.draggable-flexgrid__handle') ||
                e.target.closest('.draggable-flexgrid__menu-handle')) {
              return;
            }

            e.preventDefault();
            e.stopPropagation();

            // Get the parent grid
            const grid = item.closest('.media-grid.media-light-table-album-container');
            const sortableInstance = grid ? grid._sortableInstance || null : null;

            // Shift+click: range selection within the same grid
            if (e.shiftKey) {
              const gridItems = grid ? Array.from(grid.querySelectorAll('.media-light-table-media-item')) : [];
              const currentIndex = gridItems.indexOf(item);

              // Find last selected item in this grid
              let lastSelectedIndex = -1;
              for (let i = gridItems.length - 1; i >= 0; i--) {
                if (gridItems[i].classList.contains('selected')) {
                  lastSelectedIndex = i;
                  break;
                }
              }

              if (lastSelectedIndex !== -1) {
                const start = Math.min(lastSelectedIndex, currentIndex);
                const end = Math.max(lastSelectedIndex, currentIndex);

                for (let i = start; i <= end; i++) {
                  const itemToSelect = gridItems[i];
                  itemToSelect.classList.add('selected');
                  // Sync with Sortable's multiDrag system
                  if (sortableInstance) {
                    Sortable.utils.select(itemToSelect);
                  }
                }
              }
            }
            // Ctrl/Cmd+click or regular click: toggle
            else {
              const isSelected = item.classList.contains('selected');
              item.classList.toggle('selected');
              // Sync with Sortable's multiDrag system
              if (sortableInstance) {
                if (isSelected) {
                  Sortable.utils.deselect(item);
                } else {
                  Sortable.utils.select(item);
                }
              }
            }

            // Update count for the affected album group
            if (grid) {
              const albumGrp = grid.getAttribute('data-album-grp');
              if (albumGrp) {
                updateSelectionCountForGroup(albumView, albumGrp);
              }
            }
          });
        });

        // Attach drag and drop listeners to detect reorganization
        allGrids.forEach((grid) => {
          const sortableInstance = grid._sortableInstance;
          if (sortableInstance) {
            // Listen for any change in the sortable (including drag/drop)
            sortableInstance.option('onEnd', function (evt) {
              const grid = evt.from;
              const albumGrp = grid.getAttribute('data-album-grp');
              if (albumGrp) {
                // Mark this group as having changes
                reorganizationState[albumGrp] = true;
                updateSelectionCountForGroup(albumView, albumGrp);
              }
            });
          }

          // Also listen to Sortable's 'change' event for multi-drag
          const albumGrp = grid.getAttribute('data-album-grp');
          if (albumGrp) {
            grid.addEventListener('sortupdate', function (evt) {
              reorganizationState[albumGrp] = true;
              updateSelectionCountForGroup(albumView, albumGrp);
            });
          }
        });

        // Initialize counts for all groups
        allGrids.forEach((grid) => {
          const albumGrp = grid.getAttribute('data-album-grp');
          if (albumGrp) {
            updateSelectionCountForGroup(albumView, albumGrp);
          }
        });
      });

      function updateSelectionCountForGroup(albumView, albumGrp) {
        if (!albumView || !albumGrp) return;

        // Find all grids with this album group
        const gridsInGroup = albumView.querySelectorAll(`.media-grid.media-light-table-album-container[data-album-grp="${albumGrp}"]`);

        // Count selected items in all grids of this group
        let totalSelected = 0;
        gridsInGroup.forEach((grid) => {
          const selectedCount = grid.querySelectorAll('.media-light-table-media-item.selected').length;
          totalSelected += selectedCount;
        });

        // Check if there are unsaved reorganization changes
        const hasChanges = reorganizationState[albumGrp] || false;

        // Find or create counter wrapper for this group
        let counterWrapper = null;
        let groupContainer = null;

        // Look for the group commandes div
        for (const grid of gridsInGroup) {
          const container = grid.closest('.draggable-flexgrid__group-container');
          if (container) {
            groupContainer = container;
            counterWrapper = container.querySelector('.media-light-table-group-counter-wrapper');
            if (counterWrapper) break;
          }
        }

        // If no counter wrapper found, create one in the first grid's parent
        if (!counterWrapper && gridsInGroup.length > 0) {
          const firstGrid = gridsInGroup[0];
          let container = firstGrid.closest('.draggable-flexgrid__group-container');

          if (container) {
            counterWrapper = document.createElement('div');
            counterWrapper.className = 'media-light-table-group-counter-wrapper';
            container.insertBefore(counterWrapper, container.firstChild);

            // Create counter
            const counter = document.createElement('span');
            counter.className = 'media-light-table-group-selection-counter';
            counterWrapper.appendChild(counter);

            // Create save button
            const saveBtn = document.createElement('button');
            saveBtn.className = 'media-light-table-save-button';
            saveBtn.type = 'button';
            saveBtn.textContent = 'Sauvegarder';
            saveBtn.setAttribute('data-album-grp', albumGrp);
            saveBtn.addEventListener('click', function (e) {
              e.preventDefault();
              e.stopPropagation();
              saveAlbumReorganization(albumView, albumGrp);
            });
            counterWrapper.appendChild(saveBtn);
          }
        }

        // Update counter and button state
        const counter = counterWrapper ? counterWrapper.querySelector('.media-light-table-group-selection-counter') : null;
        const saveBtn = counterWrapper ? counterWrapper.querySelector('.media-light-table-save-button') : null;

        if (counter && saveBtn) {
          // Show wrapper if there are changes or selections
          if (hasChanges || totalSelected > 0) {
            counterWrapper.style.display = 'flex';

            // Update counter text
            if (totalSelected > 0) {
              counter.textContent = `${totalSelected} sélectionné(s)`;
            } else {
              counter.textContent = '';
            }

            // Enable save button if there are changes
            saveBtn.disabled = !hasChanges;
          } else {
            counterWrapper.style.display = 'none';
          }
        }
      }

      function saveAlbumReorganization(albumView, albumGrp) {
        // Find all grids with this album group
        const gridsInGroup = albumView.querySelectorAll(`.media-grid.media-light-table-album-container[data-album-grp="${albumGrp}"]`);

        // Build media order data
        const mediaOrder = [];
        gridsInGroup.forEach((grid) => {
          const termId = grid.getAttribute('data-termid');
          const nid = grid.getAttribute('data-nid');
          const fieldName = grid.getAttribute('data-field-name');
          const fieldType = grid.getAttribute('data-field-type');
          
          // Get orig field info from the first media item's thumbnail in this grid
          let origFieldName = null;
          let origFieldType = null;
          const thumbnailEl = grid.querySelector('.media-light-table-thumbnail');
          if (thumbnailEl) {
            origFieldName = thumbnailEl.getAttribute('data-orig-field-name');
            origFieldType = thumbnailEl.getAttribute('data-orig-field-type');
          }
          
          const items = grid.querySelectorAll('.media-light-table-media-item');
          items.forEach((item, index) => {
            const mediaId = item.getAttribute('data-media-id') || item.getAttribute('data-id');
            if (mediaId) {
              mediaOrder.push({
                media_id: mediaId,
                weight: index,
                album_grp: albumGrp,
                termid: termId,
                nid: nid,
                field_name: fieldName,
                field_type: fieldType,
                orig_field_name: origFieldName,
                orig_field_type: origFieldType
              });
            }
          });
        });

        // Send AJAX request to save reorganization
        if (mediaOrder.length > 0) {
          // Get save button and disable it
          const saveBtn = albumView.querySelector(`.media-light-table-save-button[data-album-grp="${albumGrp}"]`);
          if (saveBtn) {
            saveBtn.disabled = true;
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Sauvegarde en cours...';

            // Also add a loading indicator
            saveBtn.classList.add('is-loading');
          }

          fetch(Drupal.url('media-album-av-common/save-media-order'), {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              album_grp: albumGrp,
              media_order: mediaOrder
            })
          })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                // Show success message
                alert('Réorganisation sauvegardée avec succès');
                // Clear reorganization state for this group
                reorganizationState[albumGrp] = false;
                // Optionally clear selection
                const selectedItems = albumView.querySelectorAll(`.media-light-table-media-item.selected`);
                selectedItems.forEach(item => {
                  item.classList.remove('selected');
                });
                // Update counter
                updateSelectionCountForGroup(albumView, albumGrp);
              } else {
                alert('Erreur lors de la sauvegarde: ' + (data.message || 'Erreur inconnue'));
                // Re-enable button on error
                if (saveBtn) {
                  saveBtn.disabled = false;
                  saveBtn.textContent = 'Sauvegarder';
                  saveBtn.classList.remove('is-loading');
                }
              }
            })
            .catch(error => {
              console.error('Error:', error);
              alert('Erreur lors de la sauvegarde');
              // Re-enable button on error
              if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Sauvegarder';
                saveBtn.classList.remove('is-loading');
              }
            });
        }
      }
    }
  };

})(Drupal, once);
