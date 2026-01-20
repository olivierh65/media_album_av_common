/**
 * EXEMPLE D'UTILISATION DU SERVICE AVEC REGROUPEMENT PERSONNALISÉ
 *
 * Le service MediaViewRendererService permet maintenant de passer un regroupement
 * personnalisé sans altérer la vue d'origine, qui peut être utilisée par d'autres processus.
 */

// Utilisation simple - utilise le regroupement de la vue
$build = $media_view_renderer->renderEmbeddedMediaView(
  'media_album_av_editor',
  'media_album_av_editor',
  []  // arguments
);

// Utilisation avec regroupement personnalisé
$custom_grouping = [
  0 => [
    'field' => 'field_my_taxonomy',  // Champ de regroupement
    'label' => 'Mon regroupement',    // Label optionnel
  ],
  1 => [
    'field' => 'field_author',
    'label' => 'Auteur',
  ]
];

$build = $media_view_renderer->renderEmbeddedMediaView(
  'media_album_av_editor',
  'media_album_av_editor',
  [],                          // arguments
  [],                          // libraries
  $custom_grouping             // grouping_override
);

/**
 * AVANTAGES :
 *
 * 1. La vue d'origine reste inchangée
 * 2. Plusieurs appels peuvent utiliser des regroupements différents
 * 3. Pas d'altération de la base de données (config)
 * 4. Performance - pas de rechargement de la vue configurée
 *
 * FORMATS DE REGROUPEMENT SUPPORTÉS :
 *
 * Simple (un niveau) :
 *   $grouping = [
 *     0 => ['field' => 'field_name']
 *   ]
 *
 * Multi-niveaux :
 *   $grouping = [
 *     0 => ['field' => 'field_first'],
 *     1 => ['field' => 'field_second']
 *   ]
 *
 * Avec labels :
 *   $grouping = [
 *     0 => [
 *       'field' => 'field_name',
 *       'label' => 'My Custom Label'
 *     ]
 *   ]
 */
