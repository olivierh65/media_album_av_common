<?php

namespace Drupal\media_album_av_common\Traits;

use Drupal\field\Entity\FieldConfig;

/**
 * Trait pour les fonctions liées aux taxonomies.
 *
 * @package Drupal\media_album_av_common\Traits
 */
trait TaxonomyTrait {

  /**
   * Retourne les noms des champs d'un bundle qui référencent un vocabulaire donné.
   *
   * @param string $bundle
   *   Le type de contenu (ex: 'article').
   * @param string $vocabulary_name
   *   Le nom machine du vocabulaire (ex: 'tags').
   *
   * @return array
   *   Liste des noms de champs (ex: ['field_tags', 'field_category']).
   */
  protected function getTaxonomyReferenceFields($bundle, $vocabulary_name) {
    $fields = [];

    // Charge toutes les configurations de champ pour ce bundle.
    $field_configs = FieldConfig::loadMultiple(\Drupal::entityQuery('field_config')
      ->condition('entity_type', 'node')
      ->condition('bundle', $bundle)
      ->execute());

    foreach ($field_configs as $field_name => $field_config) {
      // Vérifie le type et la cible.
      if ($field_config->getType() == 'entity_reference' &&
      $field_config->getSetting('target_type') == 'taxonomy_term') {

        $handler_settings = $field_config->getSetting('handler_settings');
        $target_bundles = $handler_settings['target_bundles'] ?? [];

        // Si le vocabulaire est autorisé (tableau vide = tous les vocabulaires).
        if (empty($target_bundles) || in_array($vocabulary_name, $target_bundles)) {
          $fields[] = $field_config->getName();
        }
      }
    }

    return $fields;
  }

}
