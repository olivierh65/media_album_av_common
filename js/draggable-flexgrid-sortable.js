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

        const albumGroups = settings?.dragtool?.sortable?.albumsGroup ?? [];

        // search if this grid is part of an album group
        const groupClass = albumGroups
          .map((id) => `album-group-${id}`)
          .find((cls) => grid.classList.contains(cls));

        // Initialiser Sortable sur le conteneur
        const sortable = Sortable.create(grid, {
          ...(settings?.dragtool?.sortable?.options ?? {}),
          ...(groupClass ? { group: groupClass } : {}),
          dataIdAttr: "data-id",
          // Multi-drag support for selected items
          multiDrag: true,
          selectedClass: "selected",
          // Called when dragging element changes position
          onChange: function (/**Event*/ evt) {
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
        });
        
        // Store reference to Sortable instance on the grid element
        grid._sortableInstance = sortable;
        
        // Sauvegarder l'instance avec le nom du groupe
        sortableInstances.push({
          group: groupClass,
          sortable: sortable,
        });
      });

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
          const order = inst.sortable.toArray(); // IDs des médias (data-id)

          return {
            album: groupName,
            containerId: containerId,
            order: order,
          };
        });

        return result;
      }

      /**
       * Retourne l'ordre des médias pour un album donné (groupName)
       * @param {string} groupName ex: 'album-group-23'
       * @returns {string[]} tableau d'IDs des médias dans ce conteneur
       */
      function getAlbumOrder(groupName) {
        const instance = sortableInstances.find((s) => s.group === groupName);
        if (!instance) return [];
        return instance.sortable.toArray();
      }

      function getGlobalOrder() {
        const order = {};

        sortableInstances.forEach((s) => {
          // Récupérer le group
          const groupName = s.options.group;
          if (!groupName) return;

          // Récupérer les IDs de tous les items
          order[groupName] = s.toArray(); // toArray() utilise l'attribut "id" par défaut
        });

        return order;
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
