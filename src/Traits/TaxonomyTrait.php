<?php

namespace Drupal\media_album_av_common\Traits;

use Drupal\Core\Entity\EntityInterface;
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

  /**
   * Trouve ou crée un terme de taxonomie par son label.
   *
   * @param string $term_label
   * @param string $vocabulary_id
   *
   * @return int|null
   */
  public function findOrCreateTaxonomyTerm(string $term_label, string $vocabulary_id): ?int {
    if (empty($term_label) || empty($vocabulary_id)) {
      return NULL;
    }
    try {
      $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $ids = $storage->getQuery()
        ->condition('name', $term_label)
        ->condition('vid', $vocabulary_id)
        ->accessCheck(FALSE)
        ->execute();
      if (!empty($ids)) {
        return (int) reset($ids);
      }
      $term = $storage->create(['name' => $term_label, 'vid' => $vocabulary_id]);
      $term->save();
      return (int) $term->id();
    }
    catch (\Exception $e) {
      \Drupal::logger('media_drop')->error(
      'findOrCreateTaxonomyTerm "@label" (@vid): @err',
      ['@label' => $term_label, '@vid' => $vocabulary_id, '@err' => $e->getMessage()]
      );
      return NULL;
    }
  }

  /**
   * Résout une valeur brute de widget entity_reference vers un term ID.
   *
   * Gère : entité non sauvegardée (autocreate natif), "Label (123)",
   * "123|Label", ID numérique pur, label libre → findOrCreate.
   *
   * @param mixed $value
   * @param string $vocabulary_id
   *
   * @return int|null
   */
  public function resolveTermValue($value, string $vocabulary_id): ?int {
    // Entité directe (cas peu fréquent mais possible).
    if ($value instanceof EntityInterface) {
      if ($value->isNew()) {
        $value->save();
      }
      return (int) $value->id();
    }

    // Entité non sauvegardée (autocreate Drupal natif).
    // Le widget entity_autocomplete (sans #tags) stocke ['entity' => TermObject]
    // donc reset() retourne directement l'objet Term.
    if (is_array($value)) {
      $item = reset($value);
      // Cas: reset() donne l'objet Term directement (clé 'entity' de l'array).
      if ($item instanceof EntityInterface) {
        if ($item->isNew()) {
          $item->save();
        }
        return (int) $item->id();
      }
      // Cas: l'item est lui-même un array avec une clé 'entity'.
      if (is_array($item) && isset($item['entity']) && $item['entity'] instanceof EntityInterface) {
        if ($item['entity']->isNew()) {
          $item['entity']->save();
        }
        return (int) $item['entity']->id();
      }
      if (!empty($item['target_id']) && is_numeric($item['target_id'])) {
        return (int) $item['target_id'];
      }
      return NULL;
    }

    $value = trim((string) $value);

    // Format "Label (123)".
    if (preg_match('/\((\d+)\)\s*$/', $value, $matches)) {
      return (int) $matches[1];
    }
    // Format "123|Label".
    if (preg_match('/^(\d+)\|/', $value, $matches)) {
      return (int) $matches[1];
    }
    // ID numérique pur.
    if (is_numeric($value)) {
      return (int) $value;
    }
    // Label libre → findOrCreate.
    if (!empty($value)) {
      return $this->findOrCreateTaxonomyTerm($value, $vocabulary_id);
    }
    return NULL;
  }

}
