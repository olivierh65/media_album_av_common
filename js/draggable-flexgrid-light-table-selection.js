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
    attach(context, settings) {
      // Get CSS class names from settings with fallback defaults
      const lightTableContentClass = settings?.dragtool?.lightTable?.container ?? '.light-table-content';
      const mediaGridClass = settings?.dragtool?.lightTable?.gridContainer ?? '.media-grid.media-light-table-album-container';
      const mediaItemClass = settings?.dragtool?.lightTable?.mediaItem ?? '.media-light-table-media-item';
      const zoomTriggerClass = settings?.dragtool?.lightTable?.zoomTrigger ?? '.media-light-table-zoom-trigger';
      const handleClass = settings?.dragtool?.lightTable?.handle ?? '.draggable-flexgrid__handle';
      const menuHandleClass = settings?.dragtool?.lightTable?.menuHandle ?? '.draggable-flexgrid__menu-handle';
      const selectedClass = settings?.dragtool?.lightTable?.selectedClass ?? 'selected';
      const groupContainerClass = settings?.dragtool?.lightTable?.groupContainer ?? '.draggable-flexgrid__group-container';
      const counterWrapperClass = settings?.dragtool?.lightTable?.counterWrapper ?? 'media-light-table-group-counter-wrapper';
      const counterClass = settings?.dragtool?.lightTable?.counter ?? 'media-light-table-group-selection-counter';
      const saveButtonClass = settings?.dragtool?.lightTable?.saveButton ?? 'media-light-table-save-button';
      const thumbnailClass = settings?.dragtool?.lightTable?.thumbnail ?? '.media-light-table-thumbnail';
      const saveOrderEndpoint = settings?.dragtool?.callbacks?.saveMediaOrder ?? 'media-album-av-common/save-media-order';

      // Process each album view independently
      once('media-light-table-selection', lightTableContentClass, context).forEach(function (albumView) {
        // Find all grids within this album view
        const allGrids = albumView.querySelectorAll(mediaGridClass);

        // Attach click listeners to all items in all grids
        const allItems = albumView.querySelectorAll(mediaItemClass);

        allItems.forEach((item) => {
          item.addEventListener('click', function (e) {
            // Prevent triggering on children like zoom button or drag handle
            if (e.target.closest(zoomTriggerClass) ||
                e.target.closest(handleClass) ||
                e.target.closest(menuHandleClass)) {
              return;
            }

            e.preventDefault();
            e.stopPropagation();

            // Get the parent grid
            const grid = item.closest(mediaGridClass);
            const sortableInstance = grid ? grid._sortableInstance || null : null;

            // Shift+click: range selection within the same grid
            if (e.shiftKey) {
              const gridItems = grid ? Array.from(grid.querySelectorAll(mediaItemClass)) : [];
              const currentIndex = gridItems.indexOf(item);

              // Find last selected item in this grid
              let lastSelectedIndex = -1;
              for (let i = gridItems.length - 1; i >= 0; i--) {
                if (gridItems[i].classList.contains(selectedClass)) {
                  lastSelectedIndex = i;
                  break;
                }
              }

              if (lastSelectedIndex !== -1) {
                const start = Math.min(lastSelectedIndex, currentIndex);
                const end = Math.max(lastSelectedIndex, currentIndex);

                for (let i = start; i <= end; i++) {
                  const itemToSelect = gridItems[i];
                  itemToSelect.classList.add(selectedClass);
                  // Sync with Sortable's multiDrag system
                  if (sortableInstance) {
                    Sortable.utils.select(itemToSelect);
                  }
                }
              }
            }
            // Ctrl/Cmd+click or regular click: toggle
            else {
              const isSelected = item.classList.contains(selectedClass);
              item.classList.toggle(selectedClass);
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
        const gridsInGroup = albumView.querySelectorAll(`${mediaGridClass}[data-album-grp="${albumGrp}"]`);

        // Count selected items in all grids of this group
        let totalSelected = 0;
        gridsInGroup.forEach((grid) => {
          const selectedCount = grid.querySelectorAll(`.${mediaItemClass.replace(/^\.|^#/g, '')}.${selectedClass}`).length;
          totalSelected += selectedCount;
        });

        // Check if there are unsaved reorganization changes
        const hasChanges = reorganizationState[albumGrp] || false;

        // Find or create counter wrapper for this group
        let counterWrapper = null;
        let groupContainer = null;

        // Look for the group commandes div
        for (const grid of gridsInGroup) {
          const container = grid.closest(groupContainerClass);
          if (container) {
            groupContainer = container;
            counterWrapper = container.querySelector(`.${counterWrapperClass}`);
            if (counterWrapper) break;
          }
        }

        // If no counter wrapper found, create one in the first grid's parent
        if (!counterWrapper && gridsInGroup.length > 0) {
          const firstGrid = gridsInGroup[0];
          let container = firstGrid.closest(groupContainerClass);

          if (container) {
            counterWrapper = document.createElement('div');
            counterWrapper.className = counterWrapperClass;
            container.insertBefore(counterWrapper, container.firstChild);

            // Create counter
            const counter = document.createElement('span');
            counter.className = counterClass;
            counterWrapper.appendChild(counter);

            // Create save button
            const saveBtn = document.createElement('button');
            saveBtn.className = saveButtonClass;
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
        const counter = counterWrapper ? counterWrapper.querySelector(`.${counterClass}`) : null;
        const saveBtn = counterWrapper ? counterWrapper.querySelector(`.${saveButtonClass}`) : null;

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
        const gridsInGroup = albumView.querySelectorAll(`${mediaGridClass}[data-album-grp="${albumGrp}"]`);

        // Build media order data
        const mediaOrder = [];
        gridsInGroup.forEach((grid) => {
          const items = grid.querySelectorAll(mediaItemClass);
          items.forEach((item, index) => {
            const thumbnailEl = item.querySelector(thumbnailClass);
            const termId = thumbnailEl?.dataset.termid || grid.dataset.termid;
            const origTermid = thumbnailEl?.dataset.origTermid || null;
            const nid = grid.dataset.nid;
            const fieldName = thumbnailEl?.dataset.fieldName || grid.dataset.fieldName;
            const fieldType = thumbnailEl?.dataset.fieldType || grid.dataset.fieldType;
            const origFieldName = thumbnailEl?.dataset.origFieldName || null;
            const origFieldType = thumbnailEl?.dataset.origFieldType || null;

            const mediaId = thumbnailEl?.dataset.mediaId || thumbnailEl?.dataset.entityId;
            if (mediaId) {
              mediaOrder.push({
                media_id: mediaId,
                weight: index,
                album_grp: albumGrp,
                termid: termId,
                orig_termid: origTermid,
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
          const saveBtn = albumView.querySelector(`.${saveButtonClass}[data-album-grp="${albumGrp}"]`);
          if (saveBtn) {
            saveBtn.disabled = true;
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Sauvegarde en cours...';

            // Also add a loading indicator
            saveBtn.classList.add('is-loading');
          }

          fetch(Drupal.url(saveOrderEndpoint), {
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
                const selectedItems = albumView.querySelectorAll(`${mediaItemClass}.${selectedClass}`);
                selectedItems.forEach(item => {
                  item.classList.remove(selectedClass);
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
