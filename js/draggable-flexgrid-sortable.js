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
        "sortable",
        settings?.dragtool?.sortable?.containers ?? ".js-draggable-flexgrid",
        context,
      ).forEach(function (grid) {
        console.log("Initializing Sortable on grid:", grid);

        // Get CSS class names from settings with fallback defaults
        const thumbnailClass = settings?.dragtool?.lightTable?.thumbnail ?? '.media-light-table-thumbnail';

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
          selectedClass: "selected",
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
        const el = evt.item; // L'élément déplacé
        const toElement = evt.to; // Conteneur cible
        const fromElement = evt.from; // Conteneur source

        // Get termId from thumbnail of the moved element
        const thumbnailEl = el.querySelector(settings?.dragtool?.lightTable?.thumbnail ?? '.media-light-table-thumbnail');
        const toTermId = toElement.dataset.termid;
        const fromTermId = thumbnailEl?.dataset.origTermid;

        console.log(
          "Element moved:",
          thumbnailEl?.dataset.mediaId,
          "from termid",
          fromTermId,
          "to termid",
          toTermId,
        );
      }
      /**
       * Retourne l'ordre des médias par conteneur pour un album donné
       * @param {string} groupName ex: 'album-group-23'
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


      // Fonction pour sauvegarder l'ordre sur le serveur
      function saveOrderToServer(order) {
        fetch(
          Drupal.url(
            settings?.dragtool?.callbacks["saveorder"] ??
              "media-drop/draggable-flexgrid/save-order",
          ),
          {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-CSRF-Token": drupalSettings.csrf_token,
            },
            body: JSON.stringify({ order: order }),
          },
        )
          .then((response) => response.json())
          .then((data) => {
            console.log("Order saved successfully:", data);
          })
          .catch((error) => {
            console.error("Error saving order:", error);
          });
      }
    },
  };
})(jQuery, Drupal, drupalSettings, once);
