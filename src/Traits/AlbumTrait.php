<?php

namespace Drupal\media_album_av_common\Traits;

/**
 * Trait pour les fonctions liées aux albums.
 *
 * @package Drupal\media_album_av_common\Traits
 */
trait AlbumTrait {
  use TaxonomyTrait;

  /**
   * Get default values for an album.
   *
   * @param int $album_id
   *   The album node ID.
   *
   * @return array
   *   An array containing the default values for the album.
   */
  protected function getAlbumDefaults(int $album_id) {

    // 1️⃣ Load album node
    $album_node = $this->entityTypeManager
      ->getStorage('node')
      ->load($album_id);

    if (!$album_node) {
      return [];
    }

    // 2️⃣ Config module
    $config = \Drupal::config('media_album_av.settings');

    $event_group_field = $config->get('event_group_field');
    $event_field = $config->get('event_field');

    $album_fields = [
      'album_event_group_tid' => NULL,
      'album_event_tid' => NULL,
      'default_storage_location' => $config->get('prefered_storage_location'),
      'default_media_directory' => $config->get('prefered_media_directory'),
    ];

    if ($event_group_field && $album_node->hasField($event_group_field)) {
      $album_fields['album_event_group_tid'] =
          $album_node->get($event_group_field)->target_id;
    }

    if ($event_field && $album_node->hasField($event_field)) {
      $album_fields['album_event_tid'] =
          $album_node->get($event_field)->target_id;
    }

    $prefered_directory_field = $this->getTaxonomyReferenceFields($album_node->bundle(), $album_fields['default_media_directory']);
    if ($prefered_directory_field && $album_node->hasField($prefered_directory_field[0])) {
      $album_fields['album_prefered_directory_tid'] =
          $album_node->get($prefered_directory_field[0])->target_id ?? -1;
    }
    else {
      $album_fields['album_prefered_directory_tid'] = -1;
    }

    return $album_fields;
  }

}
