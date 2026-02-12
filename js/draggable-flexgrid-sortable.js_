(function ($, Drupal, drupalSettings, once) {
  "use strict";

  Drupal.behaviors.sortableFlexGrid = {
    attach: function (context, settings) {
      // ========================================
      // SORTABLE - Gestion du drag & drop
      // ========================================
      if ((settings?.dragtool?.dragtool ?? "") != "sortable") {
        return;
      }
      const sortableInstances = [];
      once(
        "sortable-flexgrid",
        "." + (settings?.dragtool?.lightTable?.gridContainer ?? "js-draggable-flexgrid"),
        context,
      ).forEach(function (grid) {
        console.log("Initializing Sortable on grid:", grid);

        // Get CSS class names from settings with fallback defaults
        const thumbnailClass =
          "." + (settings?.dragtool?.lightTable?.thumbnail ?? "media-light-table-thumbnail");

        // Get group name from data attribute
        const groupClass = grid.dataset.albumGrp;

        // Check if this group is valid based on settings
        const albumGroups = settings?.dragtool?.sortable?.albumsGroup ?? [];
        const isValidGroup =
          albumGroups.length === 0 ||
          albumGroups.some((id) => groupClass === `album-group-${id}`);

        // Initialiser Sortable sur le conteneur
        const sortable = Sortable.create(grid, {
          ...(settings?.dragtool?.sortable?.options ?? {}),
          ...(groupClass ? { group: groupClass } : {}),
          dataIdAttr: "data-id",
          // Multi-drag support for selected items
          multiDrag: true,
          avoidImplicitDeselect: true,
          selectedClass: settings?.dragtool?.lightTable?.selectedClass ?? "selected",
          // filter: '.zoom-icon, .media-light-table-name-field, .media-light-table-info-wrapper',
          // preventOnFilter: false,
          // Called when dragging element changes position
          onChange_: function (/**Event*/ evt) {
            // most likely why this event is used is to get the dragging element's current index
            // same properties as onEnd
            var toElement = evt.to; // Target list
            var fromElement = evt.from; // Previous list
            var oldIndex = evt.oldIndex; // Previous index within parent
            var newIndex = evt.newIndex; // New index within parent
            var itemEl = evt.item;
            var order = this.toArray();
            var albumorder = getAlbumContainersOrder(evt.from.dataset.albumGrp);
            console.log("Current order:", order);
            console.log("Album order:", albumorder);
          },
          // On utilise onStart pour le tracking d'origine (plus fiable)
          onStart: function (evt) {
            console.log("Drag Start");
            const item = evt.item;
            const thumbnailEl = item.querySelector(thumbnailClass);
            if (thumbnailEl && !thumbnailEl.dataset.origTermid) {
              thumbnailEl.dataset.origTermid = thumbnailEl.dataset.termid;
            }
          },
          onEnd: function (evt) {
            // On utilise onEnd, mais on ajoute un log de sécurité
            console.log("Drag End Triggered!"); // Si ça ne s'affiche pas, le handler est écrasé

            updateOnEnd(evt);
          },
        });

        // onEnd dans Sortable peut être écrasé par d'autres listeners (ex: Drupal Toolbar)
        // SOLUTION RADICALE : On écoute l'événement 'end' que Sortable émet sur le DOM
        // On utilise 'true' (mode capture) pour passer devant les listeners de Drupal/Toolbar
        grid.addEventListener(
          "end",
          function (evt) {
            console.log("!!! VICTOIRE : onEnd capturé via DOM Event !!!");

            updateOnEnd(evt);
          },
          true,
        );
        // Store reference to Sortable instance on the grid element
        grid._sortableInstance = sortable;

        // Sauvegarder l'instance avec le nom du groupe
        sortableInstances.push({
          group: groupClass,
          sortable: sortable,
        });
      });

      function updateOnEnd(evt) {
        const fromTermId = evt.from.dataset.termid;
        const toTermId = evt.to.dataset.termid;


        // Logique multi-drag : SortableJS met les éléments dans evt.items
        const items =
          evt.items && evt.items.length > 0 ? evt.items : [evt.item];

        items.forEach((itemEl) => {
          const thumbnail = itemEl.querySelector(
            "." + (settings?.dragtool?.lightTable?.thumbnail ?? "media-light-table-thumbnail"),
          );
          if (!thumbnail) {
            console.warn(
              "Thumbnail element not found for item:",
              itemEl,
            );
            return;
          }
          thumbnail.dataset.termid = toTermId;

          console.log(
            "Element moved:",
            thumbnail.dataset.mediaId,
            "from termid",
            fromTermId,
            "to termid",
            toTermId,
          );
        });
      }
      /**
       * Retourne l'ordre des médias par conteneur pour un album donné
       * @param {string} groupName ex: '23'
       * @returns {Array} tableau d'objets : { album, containerId, order }
       */
      function getAlbumContainersOrder(groupName) {
        // Filtrer toutes les instances correspondant à ce groupe
        const instances = sortableInstances.filter(
          (s) => s.group === groupName,
        );

        // Construire le tableau résultat
        const result = instances.map((inst) => {
          const containerEl = inst.sortable.el; // conteneur DOM
          const containerId = containerEl.dataset.termid; // data-termid du conteneur
          const containerOldId = containerEl.dataset.origTermid; // data-termid-orig du conteneur
          const order = inst.sortable.toArray(); // IDs des médias (data-id)

          return {
            album: groupName,
            containerId: containerId,
            containerOldId: containerOldId,
            order: order,
          };
        });

        return result;
      }
    },
  };
})(jQuery, Drupal, drupalSettings, once);
