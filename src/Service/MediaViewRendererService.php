<?php

namespace Drupal\media_album_av_common\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\media\MediaInterface;
use Drupal\views\Views;
use Drupal\file\FileInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service to render media views with common formatting.
 */
class MediaViewRendererService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The entity type manager for image styles.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $imageStyleStorage;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a MediaViewRendererService object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler,
    RendererInterface $renderer,
    FileUrlGeneratorInterface $file_url_generator,
    EntityFieldManagerInterface $entity_field_manager,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->renderer = $renderer;
    $this->fileUrlGenerator = $file_url_generator;
    $this->entityFieldManager = $entity_field_manager;
    $this->loggerFactory = $logger_factory;
    $this->imageStyleStorage = $entity_type_manager->getStorage('image_style');
  }

  /**
   * Render a light table with media using exact Views logic.
   */
  public function renderEmbeddedMediaView($view_id, $display_id, array $arguments = [], array $libraries = []) {
    $build = [];

    // Check if VBO is installed and enabled.
    if (!$this->moduleHandler->moduleExists('views_bulk_operations')) {
      $build['warning'] = [
        '#markup' => '<div class="messages messages--warning">' .
        t('The <a href="@url" target="_blank">Views Bulk Operations</a> module is required.', [
          '@url' => 'https://www.drupal.org/project/views_bulk_operations',
        ]) . '</div>',
      ];
      return $build;
    }

    // Load and execute the view using Views::getView().
    $view = Views::getView($view_id);

    if (!$view) {
      $build['error'] = [
        '#markup' => '<div class="messages messages--warning">' .
        t('View @view_id not found.', ['@view_id' => $view_id]) .
        '</div>',
      ];
      return $build;
    }

    try {
      // Set display.
      $view->setDisplay($display_id);

      // Set arguments.
      if (!empty($arguments)) {
        $view->setArguments($arguments);
      }

      // Pre-execute and execute.
      $view->preExecute();
      $view->execute();

      // Get the display handler to access grouping configuration.
      $display_handler = $view->getDisplay();
      $style_plugin = $display_handler->getPlugin('style');
      if ($style_plugin) {
        $style_options = $style_plugin->options;
        $grouping_config = $style_options['grouping'] ?? [];
      }
      else {
        $grouping_config = [];
      }
      // Use renderGrouping to get properly grouped results (Drupal standard method).
      $grouped_rows = $style_plugin->renderGrouping($view->result, $grouping_config, TRUE);

      $lg_settings = [];
      // Prepare and enrich the grouped data with media entities and thumbnails.
      // Recursively process the grouped structure.
      $build['#groups'] = $this->processGroupRecursive($grouped_rows,
        $build,
        $lg_settings, $view);

      // Filter out empty groups recursively (groups without albums and without subgroups).
      $build['#groups'] = $this->filterEmptyGroups($build['#groups']);

      // Créer le render array avec les variables EXACTES attendues par le template.
      $build += [
        '#theme' => 'media_light_table',
      // <-- IMPORTANT: L'objet View complet
        '#view' => $view,
        '#options' => $this->getViewOptions($view),
      ];

      // Extract grouping criteria for frontend (use the already retrieved $grouping_config)
      $grouping_criteria = [];
      if (!empty($grouping_config)) {
        foreach ($grouping_config as $level => $grouping) {
          if (!empty($grouping['field'])) {
            $grouping_criteria[] = [
              'level' => $level,
              'field' => $grouping['field'],
            ];
          }
        }
      }
      $build['#grouping_criteria'] = $grouping_criteria;

      // Attacher les bibliothèques.
      $build['#attached']['library'][] = 'media_album_av_common/dragula';
      $build['#attached']['library'][] = 'media_album_av_common/media-light-table';
      $build['#attached']['library'][] = 'core/drupal.dialog.ajax';

      // Bibliothèques supplémentaires.
      foreach ($libraries as $library) {
        $build['#attached']['library'][] = $library;
      }

      $build['#theme'] = 'media_light_table';
      // Configuration par défaut - passez-la comme variable séparée.
      $build['#config'] = [
        'columns' => 6,
        'gap' => '10px',
        'thumbnail_style' => 'thumbnail',
        'show_metadata' => TRUE,
        'draggable' => TRUE,
        'selectable' => TRUE,
      ];

    }
    catch (\Exception $e) {
      $build['error'] = [
        '#markup' => '<div class="messages messages--error">' .
        t('Error rendering light table: @error', ['@error' => $e->getMessage()]) .
        '</div>',
      ];
    }

    return $build;
  }

  /**
   * Get view options similar to ViewsStyle.
   */
  protected function getViewOptions($view) {
    $display_handler = $view->getDisplay();

    // Options minimales nécessaires.
    return [
      'image' => [
        'image_field' => 'thumbnail',
        'title_field' => 'name',
        'description_field' => '',
        'author_field' => '',
        'image_thumbnail_style' => 'thumbnail',
      ],
      'grouping' => $display_handler->getOption('grouping') ?? [],
      'style' => $display_handler->getOption('style') ?? [],
    ];
  }

  /**
   * Méthode originale pour compatibilité.
   */
  public function renderMediaView($view_id, $display_id, array $arguments = [], array $libraries = []) {
    return $this->renderEmbeddedMediaView($view_id, $display_id, $arguments, $libraries);
  }

  /**
   * Get executed view data for custom rendering.
   */
  public function getExecutedView($view_id, $display_id, array $arguments = []) {
    $view = Views::getView($view_id);

    if (!$view) {
      return NULL;
    }

    $view->setDisplay($display_id);

    if (!empty($arguments)) {
      $view->setArguments($arguments);
    }

    return $view;
  }

  /**
   * Recursively process the grouping structure from Views.
   *
   * @param array $groups
   *   The grouping structure returned by renderGrouping().
   * @param array &$build
   *   The build array (passed by reference to add settings).
   * @param array &$lightgallery_settings
   *   The lightgallery settings (passed by reference to collect).
   * @param int $depth
   *   Current depth (for debug/styling).
   *
   * @return array
   *   Normalized structure for Twig.
   */
  private function processGroupRecursive(array $groups, array &$build, array &$lightgallery_settings, $view, int $depth = 0, $idx = 0) {

    $processed = [];

    foreach ($groups as $group_key => $group_data) {
      $idx = rand();
      $group_item = [
        'title' => trim(strip_tags($group_data['group'])) ?? '',
        'level' => $group_data['level'] ?? $depth,
        'albums' => [],
        'subgroups' => [],
        'groupid' => 'album-group-' . $idx,
      ];

      // Check if this group contains rows (final results)
      if (isset($group_data['rows']) && is_array($group_data['rows'])) {

        // DDetermine if the "rows" are actually other groups or real rows.
        $first_row = reset($group_data['rows']);

        if (is_array($first_row) && isset($first_row['group']) && isset($first_row['level'])) {
          // These are subgroups, process recursively.
          $group_item['subgroups'] = $this->processGroupRecursive(
          $group_data['rows'],
          $build,
          $lightgallery_settings,
          $view,
          $depth + 1,
          $idx
          );
        }
        else {
          $r = $this->buildAlbumDataFromGroup($group_data['rows'], $idx, $lightgallery_settings, $view);
          if ($r) {
            $group_item['albums'][] = $r;
          }
          /* // These are real rows (ResultRow), process albums.
          foreach ($group_data['rows'] as $index => $row) {
          $album_data = $this->buildAlbumData($row, $index, $lightgallery_settings);
          if ($album_data) {
          $group_item['albums'][] = $album_data;
          }
          } */
        }
      }
      $processed[] = $group_item;
    }

    return $processed;
  }

  /**
   * Recursively filter out empty groups and albums without medias.
   *
   * Removes groups that have no albums with medias and no non-empty subgroups.
   * Also filters albums that have no medias.
   *
   * @param array $groups
   *   The groups array to filter.
   *
   * @return array
   *   Filtered groups array with empty groups/albums removed.
   */
  private function filterEmptyGroups(array $groups) {
    $filtered = [];

    foreach ($groups as $group) {
      // Filter albums: keep only those with medias.
      $filtered_albums = [];
      if (!empty($group['albums'])) {
        foreach ($group['albums'] as $album) {
          if (!empty($album['medias'])) {
            $filtered_albums[] = $album;
          }
        }
      }

      // Recursively filter subgroups.
      $filtered_subgroups = [];
      if (!empty($group['subgroups'])) {
        $filtered_subgroups = $this->filterEmptyGroups($group['subgroups']);
      }

      // Only keep the group if it has albums with medias OR non-empty subgroups.
      if (!empty($filtered_albums) || !empty($filtered_subgroups)) {
        $group['albums'] = $filtered_albums;
        $group['subgroups'] = $filtered_subgroups;
        $filtered[] = $group;
      }
    }

    return $filtered;
  }

  /**
   * Build album data from grouped rows.
   *
   * @param array $rows
   *   Array of rows in the same group.
   * @param int $group_index
   *   The group index.
   * @param array $lightgallery_album_settings
   *   The lightgallery settings (passed by reference to collect).
   *
   * @return array|null
   *   The album data or NULL on error.
   */
  private function buildAlbumDataFromGroup($rows, $group_index, array &$lightgallery_album_settings, $view) {
    $medias = [];
    $first_media = NULL;
    $title = '';
    $author = '';
    $description = '';

    foreach ($rows as $index => $row) {

      // Get the media entity from the row.
      $media = NULL;

      // Vérifier d'abord si le média est dans _relationship_entities (nouvelle structure)
      if (isset($row->_relationship_entities) && is_array($row->_relationship_entities)) {
        // Chercher le champ de relationship (ex: field_media_album_av_media)
        foreach ($row->_relationship_entities as $rel_field => $rel_entity) {
          if ($rel_entity instanceof MediaInterface) {
            $media = $rel_entity;
            break;
          }
        }
      }

      // Fallback: ancienne structure où le média était dans _entity.
      if (!$media && isset($row->_entity) && $row->_entity instanceof MediaInterface) {
        $media = $row->_entity;
      }

      if (!$media) {
        continue;
      }

      // Get source field and build media data.
      $source_field = $this->getSourceField($media, $view);
      if (!$source_field) {
        continue;
      }

      $media_data = $this->buildMediaItemData($media, $source_field);
      if ($media_data) {
        $medias[] = $media_data;
      }
    }

    if (empty($medias)) {
      return NULL;
    }

    // Use first media's thumbnail as album image.
    $image_url = $medias[0]['thumbnail'] ?? $medias[0]['url'] ?? '';

    $album_id = 'album-group-' . $group_index;

    return [
      'id' => $album_id,
      'image_url' => $image_url,
      'title' => $title,
      'author' => $author,
      'description' => $description,
      'url' => '',
      'medias' => $medias,
    ];
  }

  /**
   * Retrieves the value of a field for a specific row in the view.
   *
   * @param int $index
   *   The row index.
   * @param string $field
   *   The field name.
   *
   * @return mixed
   *   The field value.
   */
  public function getFieldValue($index, $field, $view) {
    $filters = $view->display_handler->getOption('filters');

    $bundle = NULL;
    foreach (['type', 'bundle', 'media_bundle'] as $key) {
      if (!empty($filters[$key]['value'])) {
        $bundle = $filters[$key]['value'];
        if (is_array($bundle)) {
          $bundle = reset($bundle);
        }
        break;
      }
    }

    // 1. Retrieve base table.
    $base_table = $view->storage->get('base_table');

    // 2. table to entity type mapping.
    $table_to_entity = [
      'node_field_data' => 'node',
      'media_field_data' => 'media',
      'user_field_data' => 'user',
      'taxonomy_term_field_data' => 'taxonomy_term',
      // Add other mappings as needed.
    ];
    $entity_type_id = $table_to_entity[$base_table] ?? $base_table;

    // 3. Get the row entity directly.
    $row_entity = $this->view->result[$index]->_entity ?? NULL;
    if (!$row_entity || !$row_entity->hasField($field)) {
      return '';
    }

    // 4. Get field definition.
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
    $field_definition = $field_definitions[$field] ?? NULL;

    if (!$field_definition) {
      return '';
    }

    $type = $field_definition->getType();

    // Text field - render via field handler if exists, otherwise get raw value.
    if (in_array($type, ['string', 'text', 'text_long', 'text_with_summary'])) {
      // Check if field is in view's field handlers.
      if (isset($view->field[$field])) {
        $view->row_index = $index;
        $value = $view->field[$field]->getValue($view->result[$index]);
        unset($view->row_index);
        return $value;
      }
      // Fallback: get raw value from entity.
      else {
        $field_value = $row_entity->get($field);
        if (!$field_value->isEmpty()) {
          return $field_value->first()->getValue()['value'] ?? '';
        }
      }
    }
    // Taxonomy term reference field.
    elseif ($type === 'entity_reference' && $field_definition->getSetting('target_type') === 'taxonomy_term') {
      $labels = [];
      foreach ($row_entity->get($field) as $item) {
        if ($item->entity) {
          $labels[] = $item->entity->label();
        }
      }
      // Array of labels to comma-separated string.
      return implode(', ', $labels);
    }

    return '';
  }

  /**
   * Retrieves the source field configuration from the media entity's source plugin.
   *
   * Logs a warning and skips processing if the source_field
   * configuration is missing.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity being processed.
   *
   * @return string|null
   *   The source field name or NULL if not found.
   */
  private function getSourceField($media) {
    try {
      if (!$media instanceof MediaInterface) {
        $this->loggerFactory->get('Album_editor')->warning('Invalid media entity provided.');
        return NULL;
      }
      $source = $media->getSource();
      if (!$source) {
        $this->loggerFactory->get('Album_editor')->warning('Media entity @id has no source plugin.', ['@id' => $media->id()]);
        return NULL;
      }
      $source_config = $media->getSource()->getConfiguration();
      $source_field = $source_config['source_field'] ?? NULL;
      if (!$source_field) {
        $this->loggerFactory->get('Album_editor')->warning('Media entity @id is missing a source_field configuration.', ['@id' => $media->id()]);
      }
      return $source_field;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('Album_editor')->error('Error retrieving source field for media @id: @error', ['@id' => $media->id(), '@error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Build media item data for a single media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   * @param string $source_field
   *   The source field name.
   *
   * @return array|null
   *   The media item data or NULL on error.
   */
  private function buildMediaItemData(MediaInterface $media, $source_field) {
    switch ($media->getSource()->getPluginId()) {
      case 'image':
        $file = $media->get($source_field)->entity;
        if ($file instanceof FileInterface) {
          $original_url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
          $thumbnail_url = $original_url;

          if (!empty($this->options['image']['image_thumbnail_style'])) {
            try {
              $image_style = $this->imageStyleStorage->load($this->getSetting('image_thumbnail_style'));
              if ($image_style) {
                $thumbnail_url = $image_style->buildUrl($file->getFileUri());
              }
            }
            catch (\Exception $e) {
              $this->loggerFactory->get('album_gallery')->error('Error loading image style: @error',
              ['@error' => $e->getMessage()]);
            }
          }

          return [
            'url' => $original_url,
            'mime_type' => $file->getMimeType(),
            'alt' => $media->get($source_field)->first()->get('alt')->getValue() ?? '',
            'title' => $media->get($source_field)->first()->get('title')->getValue() ?? '',
            'thumbnail' => $thumbnail_url,
          ];
        }
        break;

      case 'video_file':
        $file = $media->get($source_field)->entity;
        $thumbnail = $media->get('thumbnail')->entity;
        if ($file instanceof FileInterface) {
          $thumbnail_url = '';
          if ($thumbnail) {
            $thumbnail_url = $this->fileUrlGenerator->generateAbsoluteString($thumbnail->getFileUri());

            if (!empty($this->options['image']['image_thumbnail_style'])) {
              try {
                $image_style = $this->imageStyleStorage->load($this->getSetting('image_thumbnail_style'));
                if ($image_style) {
                  $thumbnail_url = $image_style->buildUrl($thumbnail->getFileUri());
                }
              }
              catch (\Exception $e) {
                $this->loggerFactory->get('album_gallery')->error('Error loading image style for video thumbnail: @error',
                ['@error' => $e->getMessage()]);
              }
            }
          }

          return [
            'url' => $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()),
            'mime_type' => $file->getMimeType(),
            'thumbnail' => $thumbnail_url,
            'title' => $media->get($source_field)->first()->get('description')->getValue() ?? '',
          ];
        }
        break;
    }

    return NULL;
  }

}
