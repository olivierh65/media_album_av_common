<?php

namespace Drupal\media_album_av_common\Service;

use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service for managing media album grouping field configurations.
 */
class AlbumGroupingFieldsService {
  use StringTranslationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs an AlbumGroupingFieldsService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityFieldManagerInterface $entity_field_manager,
  ) {
    $this->configFactory = $config_factory;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * Get all available node fields for grouping.
   *
   * @return array
   *   Array of node fields keyed by field name.
   */
  public function getNodeFields() {
    $fields = [];

    // Get media_album_av node fields.
    // @todo content type should be dynamic based on actual album content type.
    $node_fields = $this->entityFieldManager->getFieldDefinitions('node', 'media_album_av');

    // Only include custom fields (exclude base fields)
    foreach ($node_fields as $field_name => $field_definition) {
      if ($field_definition instanceof BaseFieldDefinition) {
        // Include only specific base fields.
        if (in_array($field_name, ['title'])) {
          $label = $field_definition->getLabel();
          $fields[$field_name] = [
            'label' => is_object($label) ? $label->render() : (string) $label,
            'type' => $field_definition->getType(),
            'source' => 'node',
          ];
        }
      }
      else {
        // Include ALL custom fields except the grouping field itself and technical fields.
        $excluded_fields = [
        // Le champ lui-même.
          'field_media_album_av_grouping',
        // Le champ média (référence)
          'field_media_album_av_media',
        // Champ technique dossier.
          'field_media_album_av_directory',
        ];

        if (!in_array($field_name, $excluded_fields)) {
          $label = $field_definition->getLabel();
          $fields[$field_name] = [
            'label' => is_object($label) ? $label->render() : (string) $label,
            'type' => $field_definition->getType(),
            'source' => 'node',
          ];
        }
      }
    }

    return $fields;
  }

  /**
   * Get accepted media bundles from the node field configuration.
   *
   * @param string $entity_type
   *   The entity type (default: 'node').
   * @param string $bundle
   *   The bundle name (default: 'media_album_av').
   * @param string $field_name
   *   The field name that references media (default: 'field_media_album_av_media').
   *
   * @return array
   *   Array of accepted media bundle names.
   */
  protected function getAcceptedMediaBundles($entity_type = 'node', $bundle = 'media_album_av', $field_name = 'field_media_album_av_media') {
    $accepted_bundles = [];

    try {
      $field_config = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle)[$field_name] ?? NULL;

      if ($field_config && $field_config instanceof FieldConfigInterface) {
        $settings = $field_config->getSettings();

        if (!empty($settings['handler_settings']['target_bundles'])) {
          $accepted_bundles = array_keys($settings['handler_settings']['target_bundles']);
        }
      }
    }
    catch (\Exception $e) {
      // Log or handle exception, return empty array if field not found.
    }

    // Return empty array if no bundles found, caller should handle gracefully.
    return !empty($accepted_bundles) ? $accepted_bundles : [];
  }

  /**
   * Get all available media fields for grouping.
   *
   * @return array
   *   Array of media fields keyed by field name.
   */
  public function getMediaFields() {
    $fields = [];

    // Get accepted media bundles dynamically from the field configuration.
    $media_bundles = $this->getAcceptedMediaBundles();

    // If no bundles found, fall back to common bundles.
    if (empty($media_bundles)) {
      $media_bundles = ['media_album_av_photo', 'media_album_av_video'];
    }

    foreach ($media_bundles as $bundle) {
      try {
        $media_fields = $this->entityFieldManager->getFieldDefinitions('media', $bundle);

        foreach ($media_fields as $field_name => $field_definition) {
          if (!($field_definition instanceof BaseFieldDefinition)) {
            // Skip media file reference fields that don't add grouping value.
            if (!in_array($field_name, ['field_media_album_av_photo', 'field_media_album_av_video', 'field_media_document'])) {
              // Avoid duplicates by using field_name as key.
              if (!isset($fields[$field_name])) {
                $label = $field_definition->getLabel();
                $fields[$field_name] = [
                  'label' => is_object($label) ? $label->render() : (string) $label,
                  'type' => $field_definition->getType(),
                  'source' => 'media',
                ];
              }
            }
          }
        }
      }
      catch (\Exception $e) {
        // Silently skip bundles that don't exist.
        continue;
      }
    }

    return $fields;
  }

  /**
   * Get all available fields for grouping (node + media).
   *
   * @return array
   *   Array with 'node_fields' and 'media_fields' keys.
   */
  public function getAllAvailableFields() {
    return [
      'node_fields' => $this->getNodeFields(),
      'media_fields' => $this->getMediaFields(),
    ];
  }

  /**
   * Get maximum allowed grouping levels.
   *
   * @return int
   *   Maximum nesting levels.
   */
  public function getMaxGroupingLevels() {
    $config = $this->configFactory->get('media_album_av_common.grouping_fields');
    return $config->get('max_grouping_levels') ?? 5;
  }

  /**
   * Get default grouping configuration.
   *
   * @return array
   *   Array of default grouping configurations.
   */
  public function getDefaultGrouping() {
    $config = $this->configFactory->get('media_album_av_common.grouping_fields');
    return $config->get('default_grouping') ?? [];
  }

  /**
   * Get a single field configuration by name.
   *
   * @param string $field_name
   *   The field name.
   * @param string $source
   *   Either 'node' or 'media'.
   *
   * @return array|null
   *   The field configuration or NULL if not found.
   */
  public function getField($field_name, $source = 'node') {
    $fields = ($source === 'media') ? $this->getMediaFields() : $this->getNodeFields();
    return $fields[$field_name] ?? NULL;
  }

  /**
   * Get all field options for a select form element.
   *
   * @return array
   *   Array of field options grouped by source (node/media).
   */
  public function getFieldOptions() {
    $options = [
      $this->t('Node Fields') => [],
      $this->t('Media Fields') => [],
    ];

    foreach ($this->getNodeFields() as $field_name => $field_config) {
      $options[$this->t('Node Fields')][$field_name] = $field_config['label'];
    }

    foreach ($this->getMediaFields() as $field_name => $field_config) {
      $options[$this->t('Media Fields')][$field_name] = $field_config['label'];
    }

    return $options;
  }

  /**
   * Get field options for Drupal field allowed_values callback.
   *
   * Cette fonction est appelée pour valider les valeurs stockées.
   *
   * @return array
   *   Flat array of options suitable for list_string field type.
   */
  public static function getFieldOptionsForField() {
    try {
      $service = \Drupal::service('media_album_av_common.grouping_fields');
      $options = [];

      // Champs node avec préfixe.
      foreach ($service->getNodeFields() as $field_name => $config) {
        $options['node:' . $field_name] = $config['label'] . ' (Node)';
      }

      // Champs media avec préfixe.
      foreach ($service->getMediaFields() as $field_name => $config) {
        $options['media:' . $field_name] = $config['label'] . ' (Media)';
      }

      return $options;
    }
    catch (\Exception $e) {
      // Si le service n'est pas disponible (installer), retourner vide.
      return [];
    }
  }

  /**
   * Validate a grouping field configuration (handles prefixed names).
   *
   * @param string $prefixed_field_name
   *   The field name, potentially with prefix (node:field_name or media:field_name).
   *
   * @return bool
   *   TRUE if the field is valid, FALSE otherwise.
   */
  public function isValidField($prefixed_field_name) {
    // Si c'est un nom préfixé, on extrait la partie field_name pour la validation.
    if (strpos($prefixed_field_name, 'node:') === 0) {
      $field_name = substr($prefixed_field_name, 5);
      return isset($this->getNodeFields()[$field_name]);
    }
    if (strpos($prefixed_field_name, 'media:') === 0) {
      $field_name = substr($prefixed_field_name, 6);
      return isset($this->getMediaFields()[$field_name]);
    }

    // Fallback : recherche dans les deux sans préfixe (rétrocompatibilité)
    return isset($this->getNodeFields()[$prefixed_field_name]) ||
           isset($this->getMediaFields()[$prefixed_field_name]);
  }

  /**
   * Get field source from prefixed name.
   */
  public function getFieldSource($prefixed_field_name) {
    if (strpos($prefixed_field_name, 'node:') === 0) {
      return 'node';
    }
    if (strpos($prefixed_field_name, 'media:') === 0) {
      return 'media';
    }

    // Fallback legacy.
    if (isset($this->getNodeFields()[$prefixed_field_name])) {
      return 'node';
    }
    if (isset($this->getMediaFields()[$prefixed_field_name])) {
      return 'media';
    }
    return NULL;
  }

}
