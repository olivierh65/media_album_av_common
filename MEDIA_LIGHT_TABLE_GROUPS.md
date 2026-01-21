# Media Album Light Table - Transformation Complete

## Summary of Changes

La transformation de `MediaAlbumLightTableStyle` est maintenant complète pour avoir la même présentation et fonctionnalité que `media_drop_manage`, avec une simple modal pour l'aperçu des médias.

## Fichiers Modifiés

### 1. PHP - MediaAlbumLightTableStyle

**Fichier:** `/web/modules/custom/media_album_av_common/src/Plugin/views/style/MediaAlbumLightTableStyle.php`

#### Modifications:
- ✅ Modifié `buildOptionsForm()` pour supporter `media_album_light_gallery` en plus de `media_drop_manage`
- ✅ Modifié `render()` pour passer `grouped_fields` au template et ajouter la library `media-light-table-modal`
- ✅ La configuration des 6 groupes de champs est déjà en place (héritage de `media_drop_manage`)

### 2. Twig Template

**Fichier:** `/web/modules/custom/media_album_av_common/templates/views-view-media-album-light-table.html.twig`

#### Modifications:
- ✅ Complètement refactorisé pour afficher les médias avec groupes de champs
- ✅ Utilise la structure existante de `groups` avec récursion pour tous les niveaux
- ✅ 6 groupes de champs configurables:
  - **Group 1:** Thumbnail Field (affichage du thumbnail)
  - **Group 2:** VBO Actions Field (actions en masse)
  - **Group 3:** Name Field (label du média)
  - **Group 4:** Media Details Fields (popup "More...")
  - **Group 5:** Action Field (lien pour voir le média)
  - **Group 6:** Image Preview Field (non utilisé dans light table)
- ✅ Modal simple pour l'aperçu du média

### 3. CSS - Styles

#### Fichier: `css/media-light-table-modal.css`
- Modal fixe avec overlay sombre
- Animation de slide-in/scale-up
- Bouton de fermeture accessible
- Responsive design
- Support clavier (Escape pour fermer)

#### Fichier: `css/media-light-table-groups.css`
- Styles pour l'affichage des groupes de champs
- Thumbnail responsif avec aspect-ratio 4:3
- Label avec ellipsis sur 2 lignes max
- Popup "More info" positionnée en-dessous du bouton
- Styles de drag handle et zoom
- Responsive breakpoints pour mobile

### 4. JavaScript - Comportements

#### Fichier: `js/media-light-table-modal.js`
- Gestion de l'ouverture/fermeture de la modal
- Trigger sur les boutons zoom
- Support clavier complet (Enter, Space, Escape)
- Fermeture sur clic de l'overlay
- Utilise le système Drupal `once()` pour éviter les doubles attachements

#### Fichier: `js/media-light-table-more-info.js`
- Gestion du popup "More info"
- Toggle de visibilité
- Fermeture des autres popups au-dessous ouverture
- Fermeture sur clic extérieur
- Support clavier (Enter, Space, Escape)

### 5. Libraries Configuration

**Fichier:** `media_album_av_common.libraries.yml`

#### Ajout:
```yaml
media-light-table-modal:
  version: 1.x
  css:
    theme:
      css/media-light-table-modal.css: {}
  js:
    js/media-light-table-modal.js: {}
  dependencies:
    - core/drupal
    - core/once
```

#### Mise à jour de `media-light-table`:
- Ajout de `css/media-light-table-groups.css`
- Ajout de `js/media-light-table-more-info.js`

## Configuration des Groupes de Champs

Pour configurer la light table avec les groupes de champs:

### Dans les paramètres de la vue:
1. Sélectionner le style "Media Album Light Table"
2. Aller à "Field Groups Configuration"
3. Configurer les 6 groupes selon vos besoins:
   - **Group 1 - Thumbnail:** Champs à afficher comme miniature
   - **Group 2 - VBO:** Actions en masse (si applicable)
   - **Group 3 - Name:** Champs du nom du média
   - **Group 4 - Details:** Champs affichés dans le popup "More info"
   - **Group 5 - Action:** Champs d'action (liens, boutons)
   - **Group 6 - Preview:** Non utilisé pour light table

## Fonctionnalités

### Affichage:
- ✅ Grille responsive avec contrôle des colonnes
- ✅ Espacement configurable entre les items
- ✅ Groupes de champs configurables
- ✅ Structure hiérarchique (groupes/sous-groupes) conservée

### Interactions:
- ✅ Drag & drop des médias (via dragula)
- ✅ Zoom sur les médias via modal simple
- ✅ Popup "More info" avec détails du média
- ✅ Support clavier complet (accessible)
- ✅ Support responsive (mobile, tablette, desktop)

### Données Affichées (dans le popup):
- Nom du fichier
- Chemin du fichier
- Taille du fichier (en MB)
- Type MIME
- Dimensions (si image/vidéo)
- Type de média

## Accessibilité

- ✅ Support complet du clavier
- ✅ Boutons focusables avec outline visible
- ✅ Rôles ARIA appropriés (dialog, button)
- ✅ Labels accessibles
- ✅ Contraste de couleur suffisant
- ✅ Pas de dépendance à la couleur seule

## Notes Techniques

1. **Modal simple:** Pas de lightgallery, juste une modal CSS/JS
2. **Données préparées:** Tous les données nécessaires sont dans la structure `groups` transmise au template
3. **Récursion:** Le template gère tous les niveaux de groupes/sous-groupes automatiquement
4. **Drag & Drop:** Utilise dragula déjà présent dans media_drop
5. **Performance:** Utilise Drupal `once()` pour éviter les attachements multiples

## Utilisation

La configuration fonctionne automatiquement quand:
- La vue a l'ID `media_album_light_gallery`
- Le display est `page_1`, `page` ou `default`
- Le style de vue est configuré à "Media Album Light Table"

La configuration des groupes de champs est persistée dans les options du style de vue.
