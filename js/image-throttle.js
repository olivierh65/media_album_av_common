(function () {
  // S'assurer que le DOM est prêt (important sous Drupal)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCustomLazyLoad);
  } else {
    initCustomLazyLoad();
  }

  function initCustomLazyLoad() {
    const CONCURRENCY = 5;
    const RETRY_DELAY = 2000;
    const MAX_RETRIES = 3;

    let active = 0;
    const queue = [];

    function drain() {
      while (active < CONCURRENCY && queue.length > 0) {
        const item = queue.shift();
        loadImage(item);
      }
    }

    function loadImage({ img, src, retries }) {
      active++;
      const temp = new Image();

      temp.onload = () => {
        console.log(`Image loading  ${src}.`);
        img.src = src;
        active--;
        drain();
      };

      temp.onerror = () => {
        fetch(src, { method: 'HEAD' }).then(res => {
          console.log(`Image load failed for ${src}. Status: ${res.status}. Retries: ${retries}`);
          if (res.status >= 500 && retries < MAX_RETRIES) {
            const delay = RETRY_DELAY * Math.pow(2, retries);
            setTimeout(() => {
              queue.unshift({ img, src, retries: retries + 1 });
              active--;
              drain();
            }, delay);
          } else {
            active--;
            drain();
          }
        }).catch(() => {
          console.log(`Image load failed for ${src}. Network error. Retries: ${retries}`);
          active--;
          drain();
        });
      };

      temp.src = src;
    }

    // 1. Intercepter les images dès le chargement de la page
    const images = document.querySelectorAll('img.media-album-av-thumbnail');

    if (images.length === 0) return; // Sécurité si on est pas sur la bonne page

    // Un pixel transparent en base64 pour éviter que l'image n'affiche une icône d'erreur
    const placeholder = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";

    images.forEach(img => {
      if (img.dataset.throttled) return; // Éviter de traiter 2 fois

      const realSrc = img.src;
      if (!realSrc || realSrc === placeholder) return;

      // On remplace le src par un placeholder et on sauvegarde le vrai src
      img.dataset.throttled = '1';
      img.dataset.src = realSrc;
      img.src = placeholder;

      // On enlève le loading="lazy" natif pour prendre le contrôle total
      img.removeAttribute('loading');
    });

    // 2. Utiliser IntersectionObserver pour savoir quand l'image devient visible
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;
          observer.unobserve(img); // On n'observe plus une fois qu'elle est visible

          const src = img.dataset.src;
          if (src) {
            // Ajouter à votre file d'attente avec limite d'accès concurrent
            queue.push({ img, src, retries: 0 });
            drain();
          }
        }
      });
    }, {
      // Commencer à charger 200px avant que l'image n'entre dans l'écran
      rootMargin: '200px 0px'
    });

    // 3. Observer toutes les images interceptées
    images.forEach(img => observer.observe(img));

    // --- GESTION DRUPAL AJAX / VUES ---
    // Si votre album se charge via AJAX (Views Load More, etc.),
    // il faut réagir quand du nouveau HTML est injecté.
    if (typeof Drupal !== 'undefined' && Drupal.behaviors) {
      Drupal.behaviors.myThrottledAlbumLazyload = {
        attach: function (context, settings) {
          // On cible uniquement les nouveaux éléments ajoutés au DOM par Drupal
          const newImages = context.querySelectorAll('img.media-album-av-thumbnail:not([data-throttled])');
          newImages.forEach(img => {
            const realSrc = img.src;
            if (!realSrc || realSrc === placeholder) return;

            img.dataset.throttled = '1';
            img.dataset.src = realSrc;
            img.src = placeholder;
            img.removeAttribute('loading');

            observer.observe(img);
          });
        }
      };
    }
  }
})();
