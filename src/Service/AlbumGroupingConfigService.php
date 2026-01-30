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
   *   Array of field names in grouping order (lowest to highest level).
   */
  public function getAlbumGroupingFields(NodeInterface $album_node) {
    // @todo content type should be dynamic based on actual album content type.
    if ($album_node->bundle() !== 'media_album_av') {
      return [];
    }

    // @todo field should be dynamic based on actual field name.
    if (!$album_node->hasField('field_media_album_av_grouping')) {
      return [];
    }

    $fields = [];
    foreach ($album_node->get('field_media_album_av_grouping') as $item) {
      $field_name = $item->value;
      if (!empty($field_name) && $this->groupingFieldsService->isValidField($field_name)) {
        $fields[] = $field_name;
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

    foreach ($fields as $level => $prefixed_field) {
      $parsed = $this->parseFieldName($prefixed_field);
      if (!$parsed) {
        continue;
      }

      $field_config = $this->groupingFieldsService->getField($parsed['field_name'], $parsed['source']);
      if ($field_config) {
        $config[] = [
          'level' => $level,
        // On garde le préfixe pour l'identification unique.
          'field_name' => $prefixed_field,
          'field_name_raw' => $parsed['field_name'],
          'label' => $field_config['label'] ?? $parsed['field_name'],
          'type' => $field_config['type'] ?? '',
          'source' => $parsed['source'],
        ];
      }
    }

    return $config;
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
