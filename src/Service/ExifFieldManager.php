<?php

namespace Drupal\media_album_av_common\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media_album_av_common\Traits\ExifFieldDefinitionsTraitMediaAlbum;

/**
 * Service for managing EXIF-related fields on media entities.
 */
class ExifFieldManager {
  use StringTranslationTrait;
  use ExifFieldDefinitionsTraitMediaAlbum;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new ExifFieldManager object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    MessengerInterface $messenger
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('media_album_av_common');
    $this->messenger = $messenger;
  }

  /**
   * Create EXIF fields on a specific media type.
   *
   * @param string $media_type_id
   *   The media type ID.
   * @param array $exif_keys
   *   Array of EXIF keys to create fields for. If empty, creates all.
   *
   * @return int
   *   Number of fields created.
   */
  public function createExifFieldsForMediaType($media_type_id, array $exif_keys = []) {
    $created_count = 0;

    // If no specific keys, create all
    if (empty($exif_keys)) {
      $exif_keys = static::getExifFieldKeys();
    }

    foreach ($exif_keys as $exif_key) {
      if (!static::isValidExifFieldKey($exif_key)) {
        continue;
      }

      $field_name = static::generateExifFieldName($exif_key);

      // Check if field storage already exists
      $field_storage = FieldStorageConfig::loadByName('media', $field_name);
      if (!$field_storage) {
        // Create field storage
        $field_storage = FieldStorageConfig::create([
          'field_name' => $field_name,
          'entity_type' => 'media',
          'type' => static::getExifFieldTypeMap()[$exif_key]['type'] ?? 'string',
          'cardinality' => 1,
          'settings' => static::getExifFieldTypeMap()[$exif_key]['settings'] ?? [],
        ]);
        $field_storage->save();
        $this->logger->info('Created field storage for @field', ['@field' => $field_name]);
      }

      // Check if field config exists for this media type
      $field_config = FieldConfig::loadByName('media', $media_type_id, $field_name);
      if (!$field_config) {
        // Create field config
        $field_labels = static::getExifFieldLabelMap();
        $field_config = FieldConfig::create([
          'field_storage' => $field_storage,
          'bundle' => $media_type_id,
          'label' => $field_labels[$exif_key] ?? $exif_key,
          'required' => FALSE,
          'translatable' => FALSE,
        ]);
        $field_config->save();
        $created_count++;
        $this->logger->info('Created field config @field for media type @type', [
          '@field' => $field_name,
          '@type' => $media_type_id,
        ]);
      }
    }

    return $created_count;
  }

  /**
   * Create EXIF fields for all media types.
   *
   * @param array $exif_keys
   *   Array of EXIF keys to create fields for.
   *
   * @return int
   *   Total number of field configs created.
   */
  public function createExifFieldsForAllMediaTypes(array $exif_keys = []) {
    $created_count = 0;

    // Get all media types
    $media_types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();

    foreach ($media_types as $media_type) {
      $created_count += $this->createExifFieldsForMediaType($media_type->id(), $exif_keys);
    }

    return $created_count;
  }

  /**
   * Delete EXIF fields from a media type.
   *
   * @param string $media_type_id
   *   The media type ID.
   * @param bool $delete_storage
   *   Whether to also delete the field storage (removes from all types).
   *
   * @return int
   *   Number of fields deleted.
   */
  public function deleteExifFieldsForMediaType($media_type_id, $delete_storage = TRUE) {
    $deleted_count = 0;
    $exif_field_names = static::getExifFieldNames();

    foreach ($exif_field_names as $field_name) {
      // Delete field config for this media type
      $field_config = FieldConfig::loadByName('media', $media_type_id, $field_name);
      if ($field_config) {
        $field_config->delete();
        $deleted_count++;
        $this->logger->info('Deleted field config @field for media type @type', [
          '@field' => $field_name,
          '@type' => $media_type_id,
        ]);
      }

      // Delete field storage if requested and no other bundles use it
      if ($delete_storage) {
        $field_storage = FieldStorageConfig::loadByName('media', $field_name);
        if ($field_storage) {
          // Check if any other media type uses this field
          $query = $this->entityTypeManager->getStorage('field_config')
            ->getQuery()
            ->condition('entity_type', 'media')
            ->condition('field_name', $field_name)
            ->condition('bundle', $media_type_id, '<>');

          if ($query->count()->execute() == 0) {
            $field_storage->delete();
            $this->logger->info('Deleted field storage @field', ['@field' => $field_name]);
          }
        }
      }
    }

    return $deleted_count;
  }

  /**
   * Delete EXIF fields from all media types.
   *
   * @return int
   *   Total number of field configs deleted.
   */
  public function deleteExifFieldsForAllMediaTypes() {
    $deleted_count = 0;

    // Get all media types
    $media_types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();

    foreach ($media_types as $media_type) {
      $deleted_count += $this->deleteExifFieldsForMediaType($media_type->id(), $media_type === end($media_types));
    }

    return $deleted_count;
  }

  /**
   * Check if EXIF fields exist for a media type.
   *
   * @param string $media_type_id
   *   The media type ID.
   *
   * @return array
   *   Array of EXIF field names that exist.
   */
  public function getExistingExifFieldsForMediaType($media_type_id) {
    $existing_fields = [];
    $exif_field_names = static::getExifFieldNames();

    foreach ($exif_field_names as $field_name) {
      $field_config = FieldConfig::loadByName('media', $media_type_id, $field_name);
      if ($field_config) {
        $existing_fields[] = $field_name;
      }
    }

    return $existing_fields;
  }

  /**
   * Get all media types in an album with their bundles.
   *
   * @param \Drupal\node\NodeInterface $album_node
   *   The album node.
   *
   * @return array
   *   Array of unique media type IDs used in the album.
   */
  public function getMediaTypesInAlbum($album_node) {
    $bundles = [];

    try {
      // Find all entity_reference fields on the node that reference media
      $query = $this->entityTypeManager->getStorage('field_config')
        ->getQuery()
        ->condition('entity_type', 'node')
        ->condition('bundle', $album_node->bundle())
        ->condition('field_type', 'entity_reference');

      $field_ids = $query->execute();

      if (!empty($field_ids)) {
        $field_configs = $this->entityTypeManager->getStorage('field_config')
          ->loadMultiple($field_ids);

        foreach ($field_configs as $field_config) {
          if ($field_config->getSetting('target_type') === 'media') {
            $field_name = $field_config->getName();

            if ($album_node->hasField($field_name)) {
              // Get all media in this field to find their types
              $media_storage = $this->entityTypeManager->getStorage('media');
              foreach ($album_node->get($field_name) as $item) {
                if ($item->target_id) {
                  $media = $media_storage->load($item->target_id);
                  if ($media) {
                    $bundle = $media->bundle();
                    if (!in_array($bundle, $bundles)) {
                      $bundles[] = $bundle;
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting media types in album: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $bundles;
  }

}
