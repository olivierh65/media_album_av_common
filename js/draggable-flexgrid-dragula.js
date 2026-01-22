(function ($, Drupal, drupalSettings, once) {
  "use strict";

  Drupal.behaviors.dragulaFlexGrid = {
    attach: function (context, settings) {
      // ========================================
      // DRAGULA - Gestion du drag & drop
      // ========================================
      if ((settings?.dragtool?.dragtool ?? '') != 'dragula') {
        return;
      }
      once("dragula", settings?.dragtool?.containers ?? ".js-draggable-flexgrid", context).forEach(
        function (grid) {
          console.log("Initializing Dragula on grid:", grid);

          var drake = dragula([grid], {
            moves: function (el, container, handle) {
              if (!el.classList.contains("js-draggable-item")) return false;
              if (handle.closest(settings?.dragtool?.excludeSelector ?? ".media-drop-info-wrapper")) return false;
              return (
                handle.classList.contains("draggable-flexgrid__handle") ||
                handle.closest(settings?.dragtool?.handleSelector ?? ".draggable-flexgrid__handle")
              );
            },
            revertOnSpill: true,
            removeOnSpill: false,
            direction: "horizontal",
            delay: 300,
            delayOnTouchOnly: true,
            mirrorContainer: document.body,
            scroll: false, // IMPORTANT: Désactiver le scroll automatique de Dragula

            invalid: function (el, handle) {
              if (
                handle &&
                // !handle.classList.contains("draggable-flexgrid__handle") &&
                !handle.closest(settings?.dragtool?.handleSelector ?? ".draggable-flexgrid__handle")
              ) {
                return true;
              }
              return false;
            },
          });

          // Variables pour le scroll de la page
          let scrollInterval = null;
          let isDragging = false;
          let lastMouseY = 0;

          // Fonction pour gérer le scroll de la page
          function handlePageScroll(e) {
            if (!isDragging) return;

            // Obtenir la position Y
            let clientY;
            if (e.type === "touchmove" && e.touches && e.touches[0]) {
              clientY = e.touches[0].clientY;
            } else if (e.type === "mousemove") {
              clientY = e.clientY;
            } else {
              return;
            }

            lastMouseY = clientY;

            // Démarrer l'intervalle de scroll si nécessaire
            if (!scrollInterval) {
              startAutoScroll();
            }
          }

          // Démarrer l'auto-scroll
          function startAutoScroll() {
            scrollInterval = setInterval(function () {
              if (!isDragging) {
                stopAutoScroll();
                return;
              }

              performAutoScroll();
            }, 16); // ~60fps
          }

          // Effectuer le scroll
          function performAutoScroll() {
            const margin = 100; // Zone de déclenchement
            const speed = 30; // Vitesse de scroll
            const y = lastMouseY;

            if (y < margin) {
              // Scroll vers le haut
              const currentScroll =
                window.pageYOffset || document.documentElement.scrollTop;
              const newScroll = Math.max(0, currentScroll - speed);
              window.scrollTo(0, newScroll);
            } else if (y > window.innerHeight - margin) {
              // Scroll vers le bas
              const currentScroll =
                window.pageYOffset || document.documentElement.scrollTop;
              const maxScroll =
                document.documentElement.scrollHeight - window.innerHeight;
              const newScroll = Math.min(maxScroll, currentScroll + speed);
              window.scrollTo(0, newScroll);
            }
          }

          // Arrêter l'auto-scroll
          function stopAutoScroll() {
            if (scrollInterval) {
              clearInterval(scrollInterval);
              scrollInterval = null;
            }
          }

          // Événement au début du drag
          drake.on("drag", function (el, source) {
            el.classList.add("draggable-flexgrid__item--drag");
            isDragging = true;

            // Ajouter les écouteurs pour la page entière
            document.addEventListener("mousemove", handlePageScroll);
            document.addEventListener("touchmove", handlePageScroll, {
              passive: true,
            });

            // Stocker la position initiale
            document.addEventListener("mousemove", storeMousePosition);
            document.addEventListener("touchmove", storeMousePosition, {
              passive: true,
            });
          });

          // Stocker la position de la souris
          function storeMousePosition(e) {
            if (e.type === "touchmove" && e.touches && e.touches[0]) {
              lastMouseY = e.touches[0].clientY;
            } else if (e.type === "mousemove") {
              lastMouseY = e.clientY;
            }
          }

          // Événement à la fin du drag
          drake.on("drop", function (el, target, source, sibling) {
            cleanup();
            el.classList.remove("draggable-flexgrid__item--drag");
            updateOrderFromDragula(grid);
          });

          // Événement si le drag est annulé
          drake.on("cancel", function (el) {
            cleanup();
            el.classList.remove("draggable-flexgrid__item--drag");
          });

          // Événement quand le drag se termine
          drake.on("dragend", function (el) {
            cleanup();
          });

          // Nettoyage
          function cleanup() {
            isDragging = false;
            stopAutoScroll();

            document.removeEventListener("mousemove", handlePageScroll);
            document.removeEventListener("touchmove", handlePageScroll);
            document.removeEventListener("mousemove", storeMousePosition);
            document.removeEventListener("touchmove", storeMousePosition);
          }
        },
      );

      // Fonction pour mettre à jour l'ordre après un drag Dragula
      function updateOrderFromDragula(grid) {
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
        /* if (typeof drupalSettings.csrf_token === "undefined") {
          console.warn("CSRF token not available, skipping save");
          return;
        } */

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
