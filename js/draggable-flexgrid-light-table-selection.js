/**
 * @file
 * Media light table item selection management.
 */

(function ($, Drupal, drupalSettings, once) {
  // Stocker l'état global par album si nécessaire
  window.mediaLightTableState = window.mediaLightTableState || {
    reorganizationState: {},
  };
  const reorganizationState = window.mediaLightTableState.reorganizationState;
  const lightTableContentClass =
    "." +
    (drupalSettings?.dragtool?.lightTable?.container ?? "light-table-content");
  // TODO: harmoniser avec les autres classes (ex: media-light-table-content)
  // et verifier settings
  const AlbumClass =
    "." +
    (drupalSettings?.dragtool?.lightTable?.album ?? "media-light-table-group");
  const gridContainerClass =
    "." +
    (drupalSettings?.dragtool?.lightTable?.gridContainer ??
      "media-light-table-album-container");
  const mediaItemClass =
    "." +
    (drupalSettings?.dragtool?.lightTable?.mediaItem ??
      "media-light-table-media-item");
  const zoomTriggerClass =
    "." +
    (drupalSettings?.dragtool?.lightTable?.zoomTrigger ??
      "media-light-table-zoom-trigger");
  const handleClass =
    "." +
    (drupalSettings?.dragtool?.lightTable?.handle ??
      "media-light-table-handle");
  const menuHandleClass =
    "." +
    (drupalSettings?.dragtool?.lightTable?.menuHandle ??
      "media-light-table-menu-handle");
  const selectedClass =
    drupalSettings?.dragtool?.lightTable?.selectedClass ?? "selected";
  const groupContainerClass =
    "." +
    (drupalSettings?.dragtool?.lightTable?.groupContainer ??
      "media-light-table-group-container");
  const counterWrapperClass =
    "." +
    (drupalSettings?.dragtool?.lightTable?.counterWrapper ??
      "media-light-table-group-counter-wrapper");
  const counterClass =
    "." +
    (drupalSettings?.dragtool?.lightTable?.counter ??
      "media-light-table-group-selection-counter");
  const saveButtonClass =
    "." +
    (drupalSettings?.dragtool?.lightTable?.saveButton ??
      "media-light-table-save-button");
  const thumbnailClass =
    "." +
    (drupalSettings?.dragtool?.lightTable?.thumbnail ??
      "media-light-table-thumbnail");
  // Définir vos fonctions dans la portée globale ou Drupal
  window.MediaAlbumFunctions = {
    prepareReorgData: function (albumGrp) {
      if (!albumGrp) return null;
      const albumView = document.querySelector(
        `${groupContainerClass}[data-album-grp="${albumGrp}"]`,
      );
      if (!albumView) return null;

      const mediaOrder = [];
      albumView
        .querySelectorAll(`${gridContainerClass}[data-album-grp="${albumGrp}"]`)
        .forEach((grid) => {
          Array.from(grid.querySelectorAll(mediaItemClass)).forEach(function (
            item,
            index,
          ) {
            const thumbnail = item.querySelector(thumbnailClass);
            if (!thumbnail) return;
            const mediaId =
              thumbnail.dataset.mediaId || thumbnail.dataset.entityId;
            if (!mediaId) return;
            mediaOrder.push({
              media_id: mediaId,
              weight: index,
              album_grp: albumGrp,
              termid: thumbnail.dataset.termid,
              orig_termid: thumbnail.dataset.origTermid ?? null,
              nid: this.dataset.nid ?? 0,
              field_name: this.dataset.fieldName ?? "",
              field_type: this.dataset.fieldType ?? "",
              orig_field_name: thumbnail.dataset.origFieldName ?? "",
              orig_field_type: thumbnail.dataset.origFieldType ?? "",
            });
          }, grid);
        });

      return {
        action: "reorg",
        album_grp: albumGrp,
        media_order: mediaOrder,
        timestamp: new Date().toISOString(),
      };
    },

    prepareActionData: function (albumGrp) {
      const albumView = document.querySelector(
        `${groupContainerClass}[data-album-grp="${albumGrp}"]`,
      );
      const actionType = albumView.querySelector(
        "#media-light-table-action-select-" + albumGrp,
      ).value;

      if (!albumView || !actionType) return;

      const selectedItems = [];
      const gridsInGroup = albumView.querySelectorAll(
        `${gridContainerClass}[data-album-grp="${albumGrp}"]`,
      );
      gridsInGroup.forEach((grid) => {
        Array.from(
          grid.querySelectorAll(`${mediaItemClass}.${selectedClass}`),
        ).forEach(function (item) {
          const thumbnail = item.querySelector(thumbnailClass);
          if (!thumbnail) return;
          const mediaId =
            thumbnail.dataset.mediaId || thumbnail.dataset.entityId;
          if (!mediaId) return;
          selectedItems.push({
            media_id: mediaId,
            album_grp: albumGrp,
            termid: thumbnail.dataset.termid,
            nid: this.dataset.nid ?? 0,
            field_name: this.dataset.fieldName ?? "",
            field_type: this.dataset.fieldType ?? "",
          });
        }, grid);
      });

      return {
        action: actionType,
        album_grp: albumGrp,
        selected_items: selectedItems,
        timestamp: new Date().toISOString(),
      };
    },
  };

  // Gérer la fermeture de la modal via le bouton Cancel
  Drupal.behaviors.mediaActionFormCancel = {
    attach(context) {
      /* once("media-action-form-cancel", ".dialog-cancel", context).forEach(
        (cancelBtn) => {
          cancelBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Trouver la modal parente et la fermer
            const modal = this.closest(".ui-dialog");
            if (modal) {
              $(modal).dialog("close");
            } else {
              // Fallback: fermer via le wrapper de la modal
              const dialogWrapper = this.closest('[role="dialog"]');
              if (dialogWrapper && dialogWrapper.parentElement) {
                $(dialogWrapper.parentElement).dialog("destroy");
                dialogWrapper.parentElement.style.display = "none";
              }
            }
          });
        },
      ); */

      //
      // Gérer le clic sur le bouton d'exécution d'action
      // Note: ce code est nécessaire pour déclencher l'appel AJAX avec les données préparées,
      // car nous avons retiré la configuration AJAX du bouton dans le formulaire PHP.
      // remplace '__ACTION__' par le nom de l'action sélectionnée dans le select correspondant à ce groupe d'album

      once(
        "media-light-table-action",
        ".media-light-table-execute-action",
        context,
      ).forEach((el) => {
        el.addEventListener("click", function (e) {
          // Bloquer COMPLÈTEMENT le comportement par défaut et les autres handlers
          e.preventDefault();
          e.stopPropagation();
          e.stopImmediatePropagation();

          const albumGrp = this.dataset.albumGrp;
          const action = document.querySelector(
            `#media-light-table-action-select-${albumGrp}`,
          ).value;
          const data = window.MediaAlbumFunctions.prepareActionData(albumGrp); // ta fonction existante

          Drupal.ajax({
            url: this.href.replace("__ACTION__", action),
            submit: { prepared_media_data: JSON.stringify(data) },
          }).execute();
        });
      });
    },
  };

  Drupal.behaviors.mediaLightTableAjaxOverride = {
    attach: function (context, settings) {
      // ✅ Surcharger UNE SEULE FOIS
      once("ajax-override-beforeserialize", "body", context).forEach(
        function () {
          // Sauvegarder la méthode originale
          const originalBeforeSerialize = Drupal.Ajax.prototype.beforeSerialize;

          /**
           * Surcharge de beforeSerialize pour ajouter nos données personnalisées.
           */
          Drupal.Ajax.prototype.beforeSerialize = function (element, options) {
            // ✅ Appeler la méthode originale d'abord
            if (originalBeforeSerialize) {
              originalBeforeSerialize.call(this, element, options);
            }

            // ✅ 'this.element' contient l'élément déclencheur (le bouton)
            const $button = $(this.element);

            console.log("=== beforeSerialize override ===");
            console.log("Element:", this.element);
            console.log("Element tag:", this.element?.tagName);
            console.log("Element classes:", this.element?.className);

            // Vérifier si c'est un de nos boutons
            const albumGrp = $button.data("album-grp");
            const prepareFuncName = $button.data("prepare-function");

            if (!albumGrp || !prepareFuncName) {
              console.log("❌ Pas notre bouton");
              return;
            }

            console.log("✅ Notre bouton détecté !");
            console.log("Album:", albumGrp);
            console.log("Function:", prepareFuncName);

            // ✅ Préparer les données
            if (
              typeof window.MediaAlbumFunctions[prepareFuncName] === "function"
            ) {
              const preparedData =
                window.MediaAlbumFunctions[prepareFuncName](albumGrp);

              console.log("📦 Données préparées:", preparedData);

              if (preparedData) {
                // ✅ Ajouter directement dans options.data
                // options.data est un objet à ce stade (avant sérialisation)
                options.data = options.data || {};
                options.data.prepared_media_data = JSON.stringify(preparedData);

                console.log("✅ Données injectées dans options.data");
                console.log("Options.data:", options.data);
              } else {
                console.warn("⚠️ prepareData a retourné null");
              }
            } else {
              console.warn("⚠️ Fonction non trouvée:", prepareFuncName);
            }
          };

          console.log("✅ Drupal.Ajax.prototype.beforeSerialize surchargé");
        },
      );
    },
  };

  Drupal.behaviors.mediaLightTableSelection = {
    attach(context, settings) {
      once("media-light-table-selection", AlbumClass, context).forEach(
        (albumView) => {
          const allGrids = albumView.querySelectorAll(gridContainerClass);
          const allItems = albumView.querySelectorAll(mediaItemClass);

          // --- Click / Double-click ---
          allItems.forEach((item) => {
            // Double-click: select/deselect all
            item.addEventListener("dblclick", (e) => {
              const grid = item.closest(gridContainerClass);
              if (!grid) return;
              e.preventDefault();
              e.stopPropagation();
              const albumGrp = grid.dataset.albumGrp;
              const items = grid.querySelectorAll(mediaItemClass);
              const sortableInstance = grid._sortableInstance || null;
              const shouldSelect = !e.shiftKey;

              items.forEach((el) => {
                if (shouldSelect) {
                  el.classList.add(selectedClass);
                  if (sortableInstance) Sortable.utils.select(el);
                } else {
                  el.classList.remove(selectedClass);
                  if (sortableInstance) Sortable.utils.deselect(el);
                }
              });

              if (albumGrp) updateSelectionCountForGroup(albumView, albumGrp);
            });

            // Click: toggle / shift+click range
            item.addEventListener("click", (e) => {
              if (
                e.target.closest(zoomTriggerClass) ||
                e.target.closest(handleClass) ||
                e.target.closest(menuHandleClass)
              )
                return;
              const grid = item.closest(gridContainerClass);
              if (!grid) return;
              const sortableInstance = grid._sortableInstance || null;

              /* // Si multiDrag est activé, le laisser gérer la sélection
              // Sinon, utiliser notre système de sélection personnalisé
              if (!grid._sortableInstance?.multiDrag) { */
              e.preventDefault();
              e.stopPropagation();

              if (e.shiftKey) {
                const gridItems = Array.from(
                  grid.querySelectorAll(mediaItemClass),
                );
                const currentIndex = gridItems.indexOf(item);
                const lastSelectedIndex =
                  gridItems
                    .map((i, idx) =>
                      i.classList.contains(selectedClass) ? idx : -1,
                    )
                    .filter((i) => i !== -1)
                    .pop() ?? -1;

                if (lastSelectedIndex !== -1) {
                  const start = Math.min(lastSelectedIndex, currentIndex);
                  const end = Math.max(lastSelectedIndex, currentIndex);
                  for (let i = start; i <= end; i++) {
                    gridItems[i].classList.add(selectedClass);
                    if (sortableInstance) Sortable.utils.select(gridItems[i]);
                  }
                }
              } else {
                const isSelected = item.classList.contains(selectedClass);
                item.classList.toggle(selectedClass);
                if (sortableInstance) {
                  if (isSelected) Sortable.utils.deselect(item);
                  else Sortable.utils.select(item);
                }
              }
              /*  } else {
                // Quand multiDrag gère la sélection, on doit quand même appeler stopPropagation
                // pour éviter les défauts de comportement, mais on laisse multiDrag faire son travail
                e.stopPropagation();
              } */

              const albumGrp = grid.dataset.albumGrp;
              if (albumGrp) updateSelectionCountForGroup(albumView, albumGrp);
            });
          });

          // --- Drag & drop ---
          allGrids.forEach((grid) => {
            const sortableInstance = grid._sortableInstance;
            if (sortableInstance) {
              sortableInstance.option("onEnd", (evt) => {
                const grp = evt.from.dataset.albumGrp;
                if (grp) {
                  reorganizationState[grp] = true;
                  updateSelectionCountForGroup(albumView, grp);
                }
              });
            }
            const grp = grid.dataset.albumGrp;
            if (grp) {
              grid.addEventListener("sortupdate", () => {
                reorganizationState[grp] = true;
                updateSelectionCountForGroup(albumView, grp);
              });
            }
          });

          // --- Init counters ---
          allGrids.forEach((grid) => {
            const grp = grid.dataset.albumGrp;
            if (grp) updateSelectionCountForGroup(albumView, grp);
          });
        },
      );

      // --- Sauvegarde ---
      once("media-save-binding", ".js-media-save-reorg", context).forEach(
        (saveBtn) => {
          // Fonction spécifique à ce bouton / album
          saveBtn.handleReorgAjaxResponse = function () {
            const albumGrp = saveBtn.dataset.albumGrp;
            const data = drupalSettings.mediaReorg;

            console.log("AJAX terminé pour album", albumGrp);
            if (data?.result.success == true) {
              console.log("Réorganisation réussie pour album", albumGrp);
              delete reorganizationState[albumGrp];

              saveBtn.disabled = true;
              saveBtn.classList.remove("is-loading");

              const albumView = saveBtn.closest(lightTableContentClass);
            } else {
              console.error(
                "Erreur lors de la réorganisation de l'album",
                albumGrp,
                data?.result?.message ?? "Aucun message d'erreur fourni",
              );
              alert(
                "Une erreur est survenue lors de la sauvegarde de la réorganisation. Veuillez réessayer.",
              );
            }
            if (albumView) updateSelectionCountForGroup(albumView, albumGrp);
          };

          // Écouter l'événement jQuery déclenché par InvokeCommand
          (saveBtn._jQuery || jQuery(saveBtn)).on(
            "reorgAjaxResponse",
            function () {
              saveBtn.handleReorgAjaxResponse();
            },
          );
        },
      );

      // --- Exécution d'action ---
      // FONCTIONNEMENT MIXTE (PHP + JavaScript):
      // - PHP: gère le state 'disabled' du bouton par rapport au select d'action (value === 'none')
      //   car c'est une interaction standard que Drupal States gère bien.
      // - JavaScript: gère le state 'disabled' du bouton par rapport au nombre d'éléments sélectionnés,
      //   car les inputs hidden ne sont pas bien détectés par Drupal States en cas de mise à jour dynamique.
      // Cette approche hybride assure un comportement fiable et réactif du bouton d'exécution.
      once(
        "media-action-binding",
        ".media-light-table-execute-action",
        context,
      ).forEach((actionBtn) => {
        // Fonction spécifique à ce bouton / action
        actionBtn.handleActionAjaxResponse = function () {
          const actionType = actionBtn.dataset.actionType;
          const data = drupalSettings.mediaAction;

          console.log("AJAX terminé pour action", actionType);
          if (data?.result.success == true) {
            console.log("Action réussie:", actionType);

            actionBtn.disabled = true;
            actionBtn.classList.remove("is-loading");

            const albumView = actionBtn.closest(lightTableContentClass);
            if (albumView) {
              const albumGrp = actionBtn.dataset.albumGrp;
              if (albumGrp) updateSelectionCountForGroup(albumView, albumGrp);
            }
          } else {
            console.error(
              "Erreur lors de l'exécution de l'action",
              actionType,
              data?.result?.message ?? "Aucun message d'erreur fourni",
            );
            alert(
              "Une erreur est survenue lors de l'exécution de l'action. Veuillez réessayer.",
            );
          }
        };

        // Écouter l'événement jQuery déclenché par InvokeCommand
        (actionBtn._jQuery || jQuery(actionBtn)).on(
          "actionAjaxResponse",
          function () {
            actionBtn.handleActionAjaxResponse();
          },
        );

        /* // Écouteur sur le select pour mettre à jour l'état du bouton
        const actionSelectId = `media-light-table-action-select-${actionBtn.dataset.albumGrp}`;
        const actionSelect = actionBtn
          .closest(lightTableContentClass)
          ?.querySelector(`#${actionSelectId}`);
        if (actionSelect) {
          actionSelect.addEventListener("change", () => {
            const isActionSelected = actionSelect.value !== "none";
            const hiddenCountField = actionBtn
              .closest(lightTableContentClass)
              ?.querySelector(
                `#selected-items-count-${actionBtn.dataset.albumGrp}`,
              );
            const totalSelected = parseInt(hiddenCountField?.value ?? 0);
            actionBtn.disabled = !isActionSelected || totalSelected === 0;
          });
        } */
      });

      // --- Fonctions utilitaires ---
      function updateSelectionCountForGroup(albumView, albumGrp) {
        if (!albumView || !albumGrp) return;
        const gridsInGroup = albumView.querySelectorAll(
          `${gridContainerClass}[data-album-grp="${albumGrp}"]`,
        );
        let totalSelected = 0;
        gridsInGroup.forEach(
          (grid) =>
            (totalSelected += grid.querySelectorAll(
              `${mediaItemClass}.${selectedClass}`,
            ).length),
        );
        const hasChanges = reorganizationState[albumGrp] || false;

        let counterWrapper = null;
        gridsInGroup.forEach((grid) => {
          const container = grid.closest(groupContainerClass);
          if (!counterWrapper && container)
            counterWrapper = container.querySelector(counterWrapperClass);
        });
        if (!counterWrapper) return;
        const counter = counterWrapper.querySelector(counterClass);
        const saveBtn = counterWrapper.querySelector(saveButtonClass);
        if (!counter || !saveBtn) return;

        // Mettre à jour le champ caché avec le nombre d'éléments sélectionnés
        const hiddenCountField = albumView.querySelector(
          `#selected-items-count-${albumGrp}`,
        );
        if (hiddenCountField) {
          hiddenCountField.value = totalSelected;

          /*    // Aussi mettre à jour l'état du bouton d'exécution d'action
          const actionSelectId = `media-light-table-action-select-${albumGrp}`;
          const actionSelect = albumView.querySelector(`#${actionSelectId}`);
          const executeButton = albumView.querySelector(
            `.media-light-table-execute-action[data-album-grp="${albumGrp}"]`,
          );

          if (actionSelect && executeButton) {
            // Désactiver le bouton si pas de sélection d'action OU aucun élément sélectionné
            const isActionSelected = actionSelect.value !== "none";
            executeButton.disabled = !isActionSelected || totalSelected === 0;
          } */
        }

        if (hasChanges || totalSelected > 0) {
          counterWrapper.style.display = "flex";
          counter.textContent =
            totalSelected > 0
              ? `${totalSelected} sélectionné${totalSelected > 1 ? "s" : ""}`
              : "-- Aucune sélection --";
          saveBtn.disabled = !hasChanges;
        } else counterWrapper.style.display = "none";
      }

      function prepareReorgData(saveBtn) {
        const albumGrp = saveBtn.dataset.albumGrp;
        const albumView = saveBtn.closest(groupContainerClass);
        if (!albumView) return;

        const mediaOrder = [];
        albumView
          .querySelectorAll(
            `${gridContainerClass}[data-album-grp="${albumGrp}"]`,
          )
          .forEach((grid) => {
            Array.from(grid.querySelectorAll(mediaItemClass)).forEach(function (
              item,
              index,
            ) {
              const thumbnail = item.querySelector(thumbnailClass);
              if (!thumbnail) return;
              const mediaId =
                thumbnail.dataset.mediaId || thumbnail.dataset.entityId;
              if (!mediaId) return;
              mediaOrder.push({
                media_id: mediaId,
                weight: index,
                album_grp: albumGrp,
                termid: thumbnail.dataset.termid,
                orig_termid: thumbnail.dataset.origTermid ?? null,
                nid: this.dataset.nid ?? 0,
                field_name: this.dataset.fieldName ?? "",
                field_type: this.dataset.fieldType ?? "",
                orig_field_name: thumbnail.dataset.origFieldName ?? "",
                orig_field_type: thumbnail.dataset.origFieldType ?? "",
              });
              // this is the grid element
            }, grid);
          });

        const hiddenInput = albumView.querySelector(`#reorg-data-${albumGrp}`);
        if (hiddenInput) hiddenInput.value = JSON.stringify(mediaOrder);
      }

      function prepareActionData(actionBtn) {
        const albumGrp = actionBtn.dataset.albumGrp;
        const albumView = actionBtn.closest(groupContainerClass);
        const actionType = albumView.querySelector(
          "#media-light-table-action-select-" + albumGrp,
        ).value;

        if (!albumView || !actionType) return;

        const selectedItems = [];
        const gridsInGroup = albumView.querySelectorAll(
          `${gridContainerClass}[data-album-grp="${albumGrp}"]`,
        );
        gridsInGroup.forEach((grid) => {
          Array.from(
            grid.querySelectorAll(`${mediaItemClass}.${selectedClass}`),
          ).forEach(function (item) {
            const thumbnail = item.querySelector(thumbnailClass);
            if (!thumbnail) return;
            const mediaId =
              thumbnail.dataset.mediaId || thumbnail.dataset.entityId;
            if (!mediaId) return;
            selectedItems.push({
              media_id: mediaId,
              album_grp: albumGrp,
              termid: thumbnail.dataset.termid,
              nid: this.dataset.nid ?? 0,
              field_name: this.dataset.fieldName ?? "",
              field_type: this.dataset.fieldType ?? "",
            });
          }, grid);
        });

        const hiddenInput = albumView.querySelector(`#action-data-${albumGrp}`);
        if (hiddenInput)
          hiddenInput.value = JSON.stringify({
            action_type: actionType,
            selected_items: selectedItems,
          });
      }
    }, // attach
  };
})(jQuery, Drupal, drupalSettings, once);
