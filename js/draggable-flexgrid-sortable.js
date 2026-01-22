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
      once(
        "sortable",
        settings?.dragtool?.containers ?? ".js-draggable-flexgrid",
        context,
      ).forEach(function (grid) {
        console.log("Initializing Sortable on grid:", grid);

        // Initialiser Sortable sur le conteneur
        Sortable.create(grid, {
          draggable: ".js-draggable-item",
          handle: settings?.dragtool?.handleSelector ?? ".draggable-flexgrid__handle",

        });
      });

      // Fonction pour mettre à jour l'ordre après un drag Sortable
      function updateOrderFromSortable(grid) {
        var items = grid.querySelectorAll(".js-draggable-item");
        var order = [];

        items.forEach(function (item, index) {
          item.setAttribute("data-index", index);
          var entityId = item.getAttribute("data-entity-id");
          if (entityId) {
            order.push(entityId);
          }
        });

        console.log("New order:", order);

        var orderInput = grid.querySelector(".media-drop-vbo-order");
        if (orderInput) {
          orderInput.value = JSON.stringify(order);
          console.log("Updated hidden field:", orderInput.value);
        }

        saveOrderToServer(order);
      }

      // Fonction pour sauvegarder l'ordre sur le serveur
      function saveOrderToServer(order) {
        fetch(Drupal.url(settings?.dragtool?.callbacks['saveorder'] ?? "media-drop/draggable-flexgrid/save-order"), {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": drupalSettings.csrf_token,
          },
          body: JSON.stringify({ order: order }),
        })
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
