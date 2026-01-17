<?php

namespace Drupal\media_album_av_common\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\views\Views;

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
   * Constructs a MediaViewRendererService object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Get media items structured for rendering in forms.
   *
   * @param string $view_id
   *   The view ID.
   * @param string $display_id
   *   The display ID within the view.
   * @param array $arguments
   *   Optional view arguments.
   * @param array $media_ids
   *   Optional array of media IDs to filter.
   *
   * @return array
   *   Structured data ready for template rendering with:
   *   - items: Array of media items
   *   - groups: Array of grouped items (if grouping is enabled)
   *   - settings: View display settings
   */
  public function getMediaItemsData($view_id, $display_id, array $arguments = [], array $media_ids = []) {
    $view = Views::getView($view_id);

    if (!$view) {
      return ['items' => [], 'groups' => [], 'settings' => []];
    }

    $view->setDisplay($display_id);

    if (!empty($arguments)) {
      $view->setArguments($arguments);
    }

    // Filtrer par media_ids si fournis.
    if (!empty($media_ids)) {
      // Ajouter un filtre programmatique sur les IDs de média
      // Note: Ceci dépend de la configuration de votre View
      // Vous pourriez avoir besoin d'ajuster le nom du filtre.
      if (isset($view->filter['mid'])) {
        $view->filter['mid']->value = $media_ids;
      }
    }

    $view->preExecute();
    $view->execute();

    // Récupérer les paramètres de la vue.
    $settings = [
      'grid_columns' => $view->style_plugin->options['columns'] ?? 4,
      'has_grouping' => !empty($view->style_plugin->options['grouping']),
      'grouping_field' => $view->style_plugin->options['grouping'][0]['field'] ?? NULL,
    ];

    $items = [];
    $groups = [];

    // Si la vue utilise des regroupements.
    if ($settings['has_grouping']) {
      $rendered_items = $view->style_plugin->renderGrouping(
        $view->result,
        $view->style_plugin->options['grouping'],
        TRUE
      );

      foreach ($rendered_items as $group_key => $group) {
        $group_items = [];

        foreach ($group['rows'] as $row_index => $row) {
          if (isset($view->result[$row_index]->_entity)) {
            $media = $view->result[$row_index]->_entity;
            $group_items[] = $this->buildMediaItemData($media, $view, $row_index);
          }
        }

        $groups[] = [
          'label' => $group['group'] ?? '',
          'items' => $group_items,
        ];
      }
    }
    else {
      // Pas de regroupement, juste une liste d'items.
      foreach ($view->result as $row_index => $row) {
        if (isset($row->_entity)) {
          $media = $row->_entity;
          $items[] = $this->buildMediaItemData($media, $view, $row_index);
        }
      }
    }

    return [
      'items' => $items,
      'groups' => $groups,
      'settings' => $settings,
    ];
  }

  /**
   * Build structured data for a single media item.
   *
   * @param \Drupal\media\Entity\Media $media
   *   The media entity.
   * @param \Drupal\views\ViewExecutable $view
   *   The view executable.
   * @param int $row_index
   *   The row index.
   *
   * @return array
   *   Structured media item data.
   */
  protected function buildMediaItemData($media, $view, $row_index) {
    $row_render = $view->rowPlugin->render($view->result[$row_index]);

    return [
      'id' => $media->id(),
      'label' => $media->label(),
      'bundle' => $media->bundle(),
      'rendered' => \Drupal::service('renderer')->render($row_render),
      'entity' => $media,
      'created' => $media->getCreatedTime(),
      'changed' => $media->getChangedTime(),
    ];
  }

  /**
   * Render a media view for embedding in forms.
   *
   * @param string $view_id
   *   The view ID to render.
   * @param string $display_id
   *   The display ID within the view.
   * @param array $arguments
   *   Optional view arguments.
   * @param array $media_ids
   *   Optional array of media IDs to display.
   * @param array $libraries
   *   Optional additional libraries to attach.
   *
   * @return array
   *   A render array with theme and data.
   */
  public function renderEmbeddedMediaView($view_id, $display_id, array $arguments = [], array $media_ids = [], array $libraries = []) {

    // Récupérer les données structurées.
    $data = $this->getMediaItemsData($view_id, $display_id, $arguments, $media_ids);

    $build = [
      '#theme' => 'media_field_view_embedded',
      '#data' => $data,
      '#view_id' => $view_id,
      '#display_id' => $display_id,
    ];

    // Attach libraries.
    $build['#attached']['library'][] = 'media_drop/media_drop_manage_image';
    $build['#attached']['library'][] = 'media_drop/draggable_flexgrid';
    $build['#attached']['library'][] = 'media_drop/admin_grid';

    foreach ($libraries as $library) {
      $build['#attached']['library'][] = $library;
    }

    return $build;
  }

  /**
   * Get executed view data for custom rendering.
   *
   * @param string $view_id
   *   The view ID.
   * @param string $display_id
   *   The display ID within the view.
   * @param array $arguments
   *   Optional view arguments.
   *
   * @return \Drupal\views\ViewExecutable|null
   *   The executed view or NULL if error.
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

    $view->preExecute();
    $view->execute();

    return $view;
  }

  /**
   * Render a media view with common styling and libraries.
   *
   * @param string $view_id
   *   The view ID to render.
   * @param string $display_id
   *   The display ID within the view.
   * @param array $arguments
   *   Optional view arguments.
   * @param array $libraries
   *   Optional additional libraries to attach.
   *
   * @return array
   *   A render array.
   */
  public function renderMediaView($view_id, $display_id, array $arguments = [], array $libraries = []) {

    $view = $this->getExecutedView($view_id, $display_id, $arguments);

    if (!$view) {
      return [
        '#markup' => '<div class="messages messages--error">' . t('Error loading the view.') . '</div>',
      ];
    }

    // Utiliser le build() de la vue pour obtenir le rendu complet.
    $build = $view->buildRenderable($display_id, $arguments);

    // Attach common libraries.
    $build['#attached']['library'][] = 'media_drop/media_drop_manage_image';
    $build['#attached']['library'][] = 'media_drop/draggable_flexgrid';
    $build['#attached']['library'][] = 'media_drop/admin_grid';

    foreach ($libraries as $library) {
      $build['#attached']['library'][] = $library;
    }

    return $build;
  }

}
