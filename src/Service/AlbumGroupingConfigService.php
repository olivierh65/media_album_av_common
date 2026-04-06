<?php

namespace Drupal\media_album_av_common\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Service for managing per-album grouping configuration.
 */
class AlbumGroupingConfigService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The grouping fields service.
   *
   * @var \Drupal\media_album_av_common\Service\AlbumGroupingFieldsService
   */
  protected $groupingFieldsService;

  /**
   * Constructs an AlbumGroupingConfigService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\media_album_av_common\Service\AlbumGroupingFieldsService $grouping_fields_service
   *   The grouping fields service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AlbumGroupingFieldsService $grouping_fields_service,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->groupingFieldsService = $grouping_fields_service;
  }

  /**
   * Get grouping fields for an album node.
   *
   * @param \Drupal\node\NodeInterface $album_node
   *   The album node.
   *
   * @return array
   *   Array of field configurations with structure:
   *   [
   *     [
   *       'field' => 'node:field_name' or 'media:field_name',
   *       'terms' => ['tid' => weight, ...] or []
   *     ],
   *     ...
   *   ]
   */
  public function getAlbumGroupingFields(NodeInterface $album_node) {
    // @todo content type should be dynamic based on actual album content type.
    if ($album_node->bundle() !== 'media_album_av') {
      return [];
    }

    // Get the grouping config field name from settings.
    $config = \Drupal::config('media_album_av.settings');
    $grouping_field = $config->get('grouping_config_field') ?? 'field_media_album_av_grouping';

    if (!$album_node->hasField($grouping_field)) {
      return [];
    }

    $fields = [];
    foreach ($album_node->get($grouping_field) as $item) {
      // The field stores JSON: {"field": "node:field_name", "terms": {...}}.
      $config_data = json_decode($item->value, TRUE);

      if (!empty($config_data['field'])) {
        $field_name = $config_data['field'];
        if ($this->groupingFieldsService->isValidField($field_name)) {
          $fields[] = [
            'field' => $field_name,
            'terms' => $config_data['terms'] ?? [],
            'terms_rendered' => $this->getRenderedTerms($config_data['terms'] ?? []),
          ];
        }
      }
      // Fallback for legacy plain text values.
      elseif (!empty($item->value) && $this->groupingFieldsService->isValidField($item->value)) {
        $fields[] = [
          'field' => $item->value,
          'terms' => [],
          'terms_rendered' => [],

        ];
      }
    }

    return $fields;
  }

  /**
   * Get grouping fields configuration with metadata.
   */
  public function getAlbumGroupingFieldsConfig(NodeInterface $album_node) {
    $fields = $this->getAlbumGroupingFields($album_node);
    $config = [];

    foreach ($fields as $level => $field_data) {
      $prefixed_field = $field_data['field'];
      $terms = $field_data['terms'] ?? [];

      $parsed = $this->parseFieldName($prefixed_field);
      if (!$parsed) {
        continue;
      }

      $field_config = $this->groupingFieldsService->getField(
        $parsed['field_name'],
        $parsed['source']
      );
      if ($field_config) {
        $config[] = [
          'level' => $level,
          // Garde le préfixe pour l'identification unique.
          'field_name' => $prefixed_field,
          'field_name_raw' => $parsed['field_name'],
          'label' => $field_config['label'] ?? $parsed['field_name'],
          'type' => $field_config['type'] ?? '',
          'source' => $parsed['source'],
          'terms' => $terms,
          'terms_rendered' => $this->getRenderedTerms($terms),
        ];
      }
    }

    return $config;
  }

  /**
   * Convert grouping fields from service to renderGrouping format.
   *
   * @param array $grouping_fields
   *   Array with structure:
   *   [
   *     ['field' => 'node:field_event', 'terms' => ['1' => 0, ...]],
   *     ['field' => 'media:field_author', 'terms' => []],
   *   ].
   *
   * @return array
   *   Format expected by $this->options['grouping'].
   */
  public function convertFieldsToViewGrouping(array $grouping_fields, bool $rendered) {
    $grouping = [];

    foreach ($grouping_fields as $delta => $field_data) {
      // Support new format: ['field' => '...', 'terms' => [...]].
      if (is_array($field_data) && isset($field_data['field'])) {
        $prefixed_field = $field_data['field'];
      }
      else {
        // Fallback for legacy format: simple string.
        $prefixed_field = $field_data;
      }

      // Remove the prefix node: or media:.
      $clean_field = preg_replace('/^(node|media):/', '', $prefixed_field);

      $grouping[$delta] = [
        'field' => $clean_field,
        'rendered' => $rendered,
        'rendered_strip' => FALSE,
      ];
    }

    return $grouping;
  }

  /**
   * Get rendered terms without HTML tags.
   *
   * @param array $terms
   *   Array of term IDs with weights: ['tid' => weight, ...].
   *
   * @return array
   *   Array of rendered terms: ['tid' => 'rendered_label', ...].
   */
  private function getRenderedTerms(array $terms) {
    if (empty($terms)) {
      return [];
    }

    $rendered = [];
    try {
      $term_ids = array_keys($terms);
      $taxonomy_terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($term_ids);

      foreach ($term_ids as $tid) {
        if (isset($taxonomy_terms[$tid])) {
          $term = $taxonomy_terms[$tid];
          // Get the term label and strip HTML tags.
          $label = $term->label();
          $rendered[$tid] = strip_tags($label);
        }
      }
    }
    catch (\Exception $e) {
      // Return empty array if there's an error loading terms.
      \Drupal::logger('media_album_av_common')->warning(
        'Error loading rendered terms: @message',
        ['@message' => $e->getMessage()]
      );
    }

    return $rendered;
  }

  /**
   * Parse a prefixed field name.
   *
   * @param string $prefixed_name
   *   Format: "node:field_name" or "media:field_name".
   *
   * @return array|null
   *   ['source' => 'node'|'media', 'field_name' => 'field_name'] or null
   */
  public function parseFieldName($prefixed_name) {
    if (strpos($prefixed_name, 'node:') === 0) {
      return ['source' => 'node', 'field_name' => substr($prefixed_name, 5)];
    }
    if (strpos($prefixed_name, 'media:') === 0) {
      return ['source' => 'media', 'field_name' => substr($prefixed_name, 6)];
    }
    // Fallback pour la rétrocompatibilité (ancien format sans préfixe)
    $source = $this->groupingFieldsService->getFieldSource($prefixed_name);
    if ($source) {
      return ['source' => $source, 'field_name' => $prefixed_name];
    }
    return NULL;
  }

  /**
   * Check if album has grouping fields configured.
   *
   * @param \Drupal\node\NodeInterface $album_node
   *   The album node.
   *
   * @return bool
   *   TRUE if grouping fields are configured, FALSE otherwise.
   */
  public function hasGroupingFields(NodeInterface $album_node) {
    return !empty($this->getAlbumGroupingFields($album_node));
  }

  /**
   * Get a human-readable summary of the grouping hierarchy.
   *
   * @param \Drupal\node\NodeInterface $album_node
   *   The album node.
   *
   * @return array
   *   Array of items with format: ['Level 1: Field Label', 'Level 2: Field Label', ...]
   */
  public function getGroupingHierarchySummary(NodeInterface $album_node) {
    $summary = [];
    $config = $this->getAlbumGroupingFieldsConfig($album_node);

    foreach ($config as $item) {
      // 1-indexed for humans
      $level_num = $item['level'] + 1;
      $label = $item['label'];
      $source = $item['source'] === 'media' ? ' (média)' : '';
      $summary[] = "Niveau $level_num: $label$source";
    }

    return $summary;
  }

}
