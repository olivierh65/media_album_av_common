<?php

namespace Drupal\media_album_av_common\Traits;

use Drupal\Core\Entity\EntityInterface;
use Drupal\views\ResultRow;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;

/**
 *
 */
trait MediaTrait {

  /**
   * Récupère les métadonnées d'un média.
   *
   * @param \Drupal\Core\Entity\EntityInterface $media
   *   L'entité média.
   * @param string $style_name
   *   (optionnel) Le nom du style d'image pour la vignette. Par défaut 'medium'.
   *
   * @return array
   *   Un tableau associatif contenant les métadonnées du média :
   *   - 'thumbnail_url': URL de la vignette.
   *   - 'thumbnail_alt': Texte alternatif de la vignette.
   *   - 'thumbnail_title': Titre de la vignette.
   *   - 'width': Largeur de la vignette.
   *   - 'height': Hauteur de la vignette.
   */
  public function getMediaThumbnail(EntityInterface $media, $style_name = 'medium') {
    $media_type = $media->bundle() ? $this->entityTypeManager->getStorage('media_type')->load($media->bundle()) : NULL;

    $thumbnail_url = NULL;
    $data = [];

    $source = $media->getSource();
    $source_field_name = $source->getSourceFieldDefinition($media->bundle->entity)->getName() ?? NULL;
    $field_item = $media->get($source_field_name)->first();

    if ($media_type) {
      $thumbnail_field = $media->get('thumbnail');
      if (!$thumbnail_field->isEmpty()) {
        /** @var \Drupal\file\FileInterface $thumbnail */
        $thumbnail = $thumbnail_field->entity;

        if ($thumbnail) {
          $thumbnail_uri = $thumbnail->getFileUri();
          $image_style = ImageStyle::load($style_name);
          if ($image_style) {
            // URL de l'image avec le style appliqué.
            $thumbnail_url = $image_style->buildUrl($thumbnail_uri);
            $data += $this->getThumbnailSize($style_name);
          }
          else {
            $thumbnail_url = $this->getFileUrl($thumbnail_uri);
          }
        }
      }

      if ($media_type && $media_type->getSource()->getPluginId() === 'image') {
        // If specific image handling is needed, add here.
      }
      elseif ($media_type && $media_type->getSource()->getPluginId() === 'video_file') {
        // If specific video handling is needed, add here.
      }
    }

    return [
      'thumbnail_url' => $thumbnail_url,
      'thumbnail_alt' => $field_item->alt ?? '',
      'thumbnail_title' => $field_item->title ?? $field_item->description ?? '',
      'thumbnail_width' => $data['thumbnail_width'] ?? NULL,
      'thumbnail_height' => $data['thumbnail_height'] ?? NULL,
    ];
  }

  /**
   * Get the thumbnail size from an image style.
   *
   * @param string $style_name
   *   The image style name.
   *
   * @return array
   *   An array with 'width' and 'height' keys.
   */
  public function getThumbnailSize($style_name = 'medium') {
    $image_style = ImageStyle::load($style_name);
    if ($image_style) {
      $effects = $image_style->getEffects();
      foreach ($effects as $effect) {
        $config = $effect->getConfiguration();
        if (in_array($effect->getPluginId(), ['image_scale_and_crop', 'image_scale'])) {
          return [
            'thumbnail_width' => $config['data']['width'],
            'thumbnail_height' => $config['data']['height'],
          ];
        }
      }
    }
    return [
      'thumbnail_width' => NULL,
      'thumbnail_height' => NULL,
    ];
  }

  /**
   * Get the media entity from a row by traversing the reference field.
   *
   * Automatically detects which field references media entities and retrieves it.
   *
   * @param \Drupal\views\ResultRow $row
   *   The row result.
   *
   * @return \Drupal\media\MediaInterface|null
   *   The media entity, or NULL if not found.
   */
  public function getMediaEntity(ResultRow $row) {

    // Get the base entity from the row.
    if (!isset($row->_entity)) {
      return NULL;
    }

    $entity = $row->_entity;

    // Find the field that references media.
    $media_field = $this->getMediaReferenceField($entity);
    if (!$media_field) {
      return NULL;
    }

    // Get the media entity from the field.
    if ($entity->hasField($media_field)) {
      $field = $entity->get($media_field);

      // Get the first referenced media entity.
      if (!$field->isEmpty()) {
        $media = $field->entity;
        return $media;
      }
    }

    return NULL;
  }

  /**
   * Get the media entity from relation.
   *
   * Automatically detects which field references media entities and retrieves it.
   *
   * @param \Drupal\views\ResultRow $row
   *   The row result.
   *
   * @return \Drupal\media\MediaInterface|null
   *   The media entity, or NULL if not found.
   */
  public function getReferencedMediaEntity(ResultRow $row) {

    // Vérifier d'abord si le média est dans _relationship_entities.
    if (isset($row->_relationship_entities) && is_array($row->_relationship_entities)) {
      // Chercher le champ de relationship (ex: field_media_album_av_media)
      foreach ($row->_relationship_entities as $rel_field => $rel_entity) {
        if ($rel_entity instanceof MediaInterface) {
          $media = $rel_entity;
          break;
        }
      }
    }
    if ($media) {
      return $media;
    }
    else {
      return NULL;
    }
  }

  /**
   * Get the media file URL for a media entity.
   *
   * @param \Drupal\media\MediaInterface $media_entity
   *   The media entity.
   *
   * @return string|null
   *   The media URL, or NULL if not found.
   */
  protected function getMediaFileUrl($media_entity) {
    if (!$media_entity) {
      return NULL;
    }

    // Get the media source.
    $source = $media_entity->getSource();

    // Récupérer le champ source (image, video, document, etc.)
    $source_field = $source->getSourceFieldDefinition($media_entity->bundle->entity)->getName();

    // Obtenir l'entité File.
    /** @var \Drupal\file\FileInterface $file */
    $file = $media_entity->get($source_field)->entity;

    if ($file) {
      return $file->createFileUrl();
    }
    else {
      return NULL;
    }
  }

  /**
   * Convert a file URI to a URL.
   *
   * @param string $uri
   *   The file URI.
   *
   * @return string
   *   The file URL.
   */
  protected function getFileUrl($uri) {
    if (!$uri) {
      return NULL;
    }
    return $this->file_url_generator->generateAbsoluteString($uri);
  }

  /**
   * Get full media info from a ResultRow.
   *
   * @param \Drupal\views\ResultRow $row
   *   The views result row.
   *
   * @return array
   *   An array with full media info.
   */
  public function getMediaRowFullInfo(ResultRow $row) {
    $entity = $this->getReferencedMediaEntity($row);

    // Vérifier que c'est bien une entité media.
    if ($entity->getEntityTypeId() !== 'media') {
      return [];
    }

    $info = [];
    $info['bundle'] = $entity->bundle();
    $info['id'] = $entity->id();
    $info['label'] = $entity->label();

    // Source field (généralement field_media_image ou thumbnail)
    $source_field_def = $entity->getSource()->getSourceFieldDefinition($entity->bundle->entity);
    $field_name = $source_field_def->getName();

    // Récupérer l'entité fichier si existante.
    if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
      $file = $entity->get($field_name)->entity;

      if ($file) {
        // Chemin réel sur le disque.
        $real_path = \Drupal::service('file_system')->realpath($file->getFileUri());
        $info['file_path'] = $real_path;

        $info['file_uri'] = $file->getFileUri();

        // URL publique.
        // URL publique si le fichier est dans public://.
        if (\Drupal::service('stream_wrapper_manager')->getScheme($info['file_uri']) === 'public') {
          $info['url'] = $this->fileUrlGenerator->generateString($info['file_uri']);
        }
        // Fichier privé : URL via le route system/files.
        elseif ($this->streamWrapperManager->getScheme($info['file_uri']) === 'private') {
          $info['url'] = Url::fromRoute('system.files', ['file' => $file->getFilename()], ['absolute' => TRUE])->toString();
        }

        // Taille de l'image si applicable.
        if (file_exists($real_path)) {
          $image_info = getimagesize($real_path);
          if (is_array($image_info)) {
            $info['width'] = $image_info[0];
            $info['height'] = $image_info[1];
          }
          else {
            // Video dont't have getimagesize info.
            $info['width'] = 0;
            $info['height'] = 0;
          }
        }
        else {
          $info['width'] = 0;
          $info['height'] = 0;
        }

        // Infos supplémentaires du fichier.
        $info['file_name'] = $file->getFilename();
        $info['mime_type'] = $file->getMimeType();
        $info['size_bytes'] = $file->getSize();

        // ALT et description si disponible.
        if ($entity->hasField('field_media_image') && !$entity->get('field_media_image')->isEmpty()) {
          $media_field = $entity->get('field_media_image')->first();
          $info['alt'] = $media_field->alt ?? '';
          $info['description'] = $media_field->title ?? '';
        }
      }
    }

    // Tous les champs textuels disponibles.
    foreach ($entity->getFields() as $field_name => $field) {
      if ($field->getFieldDefinition()->getType() === 'string' || $field->getFieldDefinition()->getType() === 'text_long') {
        $info['fields'][$field_name] = $entity->get($field_name)->value;
      }
      if ($field->getFieldDefinition()->getType() === 'entity_reference' && $field->getFieldDefinition()->getSetting('target_type') === 'taxonomy_term') {
        $terms = [];
        foreach ($entity->get($field_name)->referencedEntities() as $term) {
          $terms[] = [
            'id' => $term->id(),
            'name' => $term->label(),
          ];
        }
        $info['terms'][$field_name] = $terms;
      }
    }

    return $info;
  }

}
