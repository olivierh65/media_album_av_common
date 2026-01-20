# Media Light Table - Filtres et Regroupements

## Vue d'ensemble

La Light Table supporte maintenant :
1. **Filtrage par type de média** - Afficher/masquer les types spécifiques
2. **Regroupement multi-niveaux** - Organiser les médias par 1, 2 ou 3 critères
3. **Aperçu visuel de l'ordre** - Les sélecteurs cascadants montrent clairement l'ordre des regroupements

## Interface Utilisateur

### Filtres par Type de Média

Un panneau "Filters & Grouping" contient des checkboxes pour :
- Video
- Audio
- Image
- PNG

Cocher les types souhaités masquera automatiquement les autres médias.

### Sélecteur de Regroupement Hiérarchique

L'interface propose **3 niveaux de regroupement** :

```
Level 1 ▼ [Category    ]
Level 2 ▼ [Author      ]  ← Apparaît seulement si Level 1 a une valeur
Level 3 ▼ [Date        ]  ← Apparaît seulement si Level 2 a une valeur
```

#### Fonctionnement Hiérarchique

```javascript
// Exemple 1: Un seul niveau
Level 1 = "Category"
Level 2 = ""  (vide)
Level 3 = ""  (vide)
→ Affichage: [Média groupés par Catégorie]

// Exemple 2: Deux niveaux
Level 1 = "Category"
Level 2 = "Author"
Level 3 = ""
→ Affichage: [Catégorie] → [Auteur] → [Médias]

// Exemple 3: Trois niveaux
Level 1 = "Category"
Level 2 = "Author"
Level 3 = "Date"
→ Affichage: [Catégorie] → [Auteur] → [Date] → [Médias]
```

## Comportement

### Cascade des Sélecteurs

- **Level 1** est toujours visible
- **Level 2** n'apparaît que si Level 1 a une valeur
- **Level 3** n'apparaît que si Level 2 a une valeur

Cela évite la confusion et force l'utilisateur à définir les niveaux dans l'ordre.

### Boutons d'Action

- **Apply Grouping** : Applique la configuration et réorganise la vue
- **Reset** : Revient au regroupement par défaut de la vue

## Événements JavaScript

Le système déclenche deux événements personnalisés :

```javascript
// Quand l'utilisateur applique un regroupement personnalisé
document.addEventListener('mediaLightTableGroupingChanged', function(e) {
  console.log(e.detail.grouping_criteria);
  // [
  //   { level: 0, field: 'field_category' },
  //   { level: 1, field: 'field_author' }
  // ]
});

// Quand l'utilisateur réinitialise les regroupements
document.addEventListener('mediaLightTableGroupingReset', function(e) {
  // Revenir à la config par défaut
});
```

## Format des Données

### Structure du Regroupement

```php
$grouping = [
  0 => [
    'level' => 0,
    'field' => 'field_category',
  ],
  1 => [
    'level' => 1,
    'field' => 'field_author',
  ],
];
```

### Passage au Service PHP

```php
$build = $media_view_renderer->renderEmbeddedMediaView(
  'view_id',
  'display_id',
  [],           // arguments
  [],           // libraries
  $grouping     // grouping_override (optionnel)
);
```

## Filtrage par Type de Média

### Types Supportés

- `video/mp4` - Vidéos MP4
- `audio/mpeg` - Fichiers audio MP3
- `image/jpeg` - Images JPEG
- `image/png` - Images PNG

### Extension Facile

Modifiez le template pour ajouter plus de types :

```twig
<label><input type="checkbox" class="media-filter" value="application/pdf" /> {{ 'PDF'|t }}</label>
```

Chaque type correspond au `data-mime-type` du média.

## Intégration avec VBO

Les filtres n'affectent pas les sélections VBO. Les médias masqués restent sélectionnables
via "Select all" - seuls les médias visibles seront affichés mais tous les sélectionnés
seront inclus dans les opérations.

## Personnalisation

### Ajouter des Champs de Regroupement

Modifiez le template `media-light-table.html.twig` pour ajouter des options :

```twig
<option value="field_custom">{{ 'My Field'|t }}</option>
```

### Modifier les Types de Média

Dans le fichier `media-light-table.html.twig`, section "Filter by Media Type"

### Ajouter Plus de Niveaux

Dupliquez une `grouping-level` dans le template (actuellement limité à 3)

## Limitations Actuelles

- Maximum 3 niveaux de regroupement
- Les filtres sont côté client (masquage visuel) et non côté serveur
- Les regroupements appliqués ne persistent pas au rechargement de page
