<?php

namespace Drupal\media_album_av_common\Plugin\views\style;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\media_album_av_common\Traits\MediaTrait;

/**
 * A custom style plugin for rendering media album light tables.
 *
 * Renders a light table specifically for media album items.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "media_album_light_table",
 *   title = @Translation("Media Album Light Table"),
 *   help = @Translation("Renders a light table specifically for media album items."),
 *   theme = "views_view_media_album_light_table",
 *   display_types = {"normal"}
 * )
 */
class MediaAlbumLightTableStyle extends StylePluginBase {
  use MediaTrait;
  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;
  /**
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = TRUE;

  /**
   * Constructs an AlbumIsotopeGallery style plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    FileUrlGeneratorInterface $file_url_generator,
    EntityTypeManagerInterface $entity_type_manager,
    StreamWrapperManagerInterface $stream_wrapper_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileUrlGenerator = $file_url_generator;
    $this->entityTypeManager = $entity_type_manager;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * Creates an instance of the AlbumIsotopeGallery style plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
        $configuration,
        $plugin_id,
        $plugin_definition,
        $container->get('file_url_generator'),
        $container->get('entity_type.manager'),
        $container->get('stream_wrapper_manager'),
    );
  }

  /**
   * Find the field that references media entities in an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to inspect.
   *
   * @return string|null
   *   The field name that references media, or NULL if not found.
   */
  protected function getMediaReferenceField($entity) {
    if (!$entity) {
      return NULL;
    }

    // Check all fields on the entity.
    foreach ($entity->getFieldDefinitions() as $field_name => $field_definition) {
      $field_type = $field_definition->getType();

      // Check if it's an entity_reference field.
      if ($field_type === 'entity_reference') {
        $settings = $field_definition->getSettings();

        // Check if it references media.
        if (isset($settings['target_type']) && $settings['target_type'] === 'media') {
          return $field_name;
        }
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['image_thumbnail_style'] = ['default' => 'medium'];
    $options['columns'] = ['default' => 4];
    $options['gap'] = ['default' => '20px'];
    $options['justify'] = ['default' => 'flex-start'];
    $options['align'] = ['default' => 'stretch'];
    $options['responsive'] = ['default' => TRUE];
    $options['field_groups'] = ['default' => []];
    $options['show_ungrouped'] = ['default' => TRUE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Check if this is a media management view that uses field grouping.
    if ((($this->view->id() == 'media_drop_manage') && ($this->view->current_display == 'page_1')) ||
      ($this->view->id() == 'media_album_light_gallery' && in_array($this->view->current_display, ['page_1', 'page', 'default']))) {
      $manage = TRUE;
    }
    else {
      $manage = FALSE;
    }

    // Image styles for thumbnails.
    $image_styles = ImageStyle::loadMultiple();
    foreach ($image_styles as $style => $image_style) {
      $image_thumbnail_style[$image_style->id()] = $image_style->label();
    }
    $default_style = '';
    if (isset($this->options['image']['image_thumbnail_style']) && $this->options['image']['image_thumbnail_style']) {
      $default_style = $this->options['image']['image_thumbnail_style'];
    }
    elseif (isset($image_styles['image']['medium'])) {
      $default_style = 'medium';
    }
    elseif (isset($image_styles['image']['thumbnail'])) {
      $default_style = 'thumbnail';
    }
    elseif (!empty($image_styles)) {
      $default_style = array_key_first($image_styles);
    }

    $form['columns'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of columns'),
      '#default_value' => $this->options['columns'],
      '#min' => 1,
      '#max' => 12,
    ];

    $form['gap'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gap between items'),
      '#default_value' => $this->options['gap'],
      '#description' => $this->t('CSS gap value (e.g., 20px, 1rem, 2em)'),
    ];

    $form['image_thumbnail_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Thumbnail style'),
      '#options' => $image_thumbnail_style,
      '#default_value' => $this->options['image_thumbnail_style'] ?? $default_style,
      '#description' => $this->t('Select an image style to apply to the thumbnails.'),
    ];

    $form['justify'] = [
      '#type' => 'select',
      '#title' => $this->t('Justify content'),
      '#options' => [
        'flex-start' => $this->t('Flex start'),
        'flex-end' => $this->t('Flex end'),
        'center' => $this->t('Center'),
        'space-between' => $this->t('Space between'),
        'space-around' => $this->t('Space around'),
        'space-evenly' => $this->t('Space evenly'),
      ],
      '#default_value' => $this->options['justify'],
    ];

    $form['align'] = [
      '#type' => 'select',
      '#title' => $this->t('Align items'),
      '#options' => [
        'stretch' => $this->t('Stretch'),
        'flex-start' => $this->t('Flex start'),
        'flex-end' => $this->t('Flex end'),
        'center' => $this->t('Center'),
        'baseline' => $this->t('Baseline'),
      ],
      '#default_value' => $this->options['align'],
    ];

    $form['responsive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Responsive grid'),
      '#description' => $this->t('Automatically adjust columns based on screen size'),
      '#default_value' => $this->options['responsive'],
    ];

    // Récupérer tous les champs disponibles.
    $fields = $this->displayHandler->getHandlers('field');
    $field_options = ['' => $this->t('- None -')];
    foreach ($fields as $field_name => $field) {
      $field_options[$field_name] = $field->adminLabel();
    }

    // Section pour la configuration des groupes avec sélection spécifique des zones.
    $form['field_mapping'] = [
      '#type' => 'details',
      '#title' => $this->t('Field/zone mapping'),
      '#open' => TRUE,
      '#weight' => 10,
      '#tree' => TRUE,
    ];

    $form['field_mapping']['description'] = [
      '#markup' => '<p>' . $this->t('Select which field to use for each media album light table zone.') . '</p>',
    ];

    // Zone 1: Thumbnail (always active, uses thumbnail_url from media info)
    $form['field_mapping']['thumbnail'] = [
      '#type' => 'details',
      '#title' => $this->t('Thumbnail Zone'),
      '#open' => !empty($this->options['field_mapping']['thumbnail']['enabled']),
    ];

    $form['field_mapping']['thumbnail']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable thumbnail zone'),
      '#default_value' => $this->options['field_mapping']['thumbnail']['enabled'] ?? TRUE,
      '#description' => $this->t('Display the media thumbnail image'),
    ];

    // Zone 2: VBO Actions.
    $form['field_mapping']['vbo'] = [
      '#type' => 'details',
      '#title' => $this->t('VBO Actions Zone'),
      '#open' => !empty($this->options['field_mapping']['vbo']['enabled']),
    ];

    $form['field_mapping']['vbo']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable VBO actions zone'),
      '#default_value' => $this->options['field_mapping']['vbo']['enabled'] ?? FALSE,
    ];

    $form['field_mapping']['vbo']['field'] = [
      '#type' => 'select',
      '#title' => $this->t('VBO field'),
      '#options' => $field_options,
      '#default_value' => $this->options['field_mapping']['vbo']['field'] ?? '',
      '#description' => $this->t('Select the VBO actions field'),
      '#states' => [
        'visible' => [
          ':input[name="style_options[field_mapping][vbo][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Zone 3: Name.
    $form['field_mapping']['name'] = [
      '#type' => 'details',
      '#title' => $this->t('Name Zone'),
      '#open' => !empty($this->options['field_mapping']['name']['enabled']),
    ];

    $form['field_mapping']['name']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable name zone'),
      '#default_value' => $this->options['field_mapping']['name']['enabled'] ?? TRUE,
    ];

    $form['field_mapping']['name']['field'] = [
      '#type' => 'select',
      '#title' => $this->t('Name field (text field)'),
      '#options' => $field_options,
      '#default_value' => $this->options['field_mapping']['name']['field'] ?? '',
      '#description' => $this->t('Select a text field for the media name'),
      '#states' => [
        'visible' => [
          ':input[name="style_options[field_mapping][name][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Zone 4: Media Details (uses media info: file_name, file_path, size_bytes, mime_type, width, height, bundle)
    $form['field_mapping']['details'] = [
      '#type' => 'details',
      '#title' => $this->t('Media Details Zone'),
      '#open' => !empty($this->options['field_mapping']['details']['enabled']),
    ];

    $form['field_mapping']['details']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable media details zone'),
      '#default_value' => $this->options['field_mapping']['details']['enabled'] ?? TRUE,
      '#description' => $this->t('Display media details popup (filename, size, MIME type, dimensions, media type)'),
    ];

    // Zone 5: Action.
    $form['field_mapping']['action'] = [
      '#type' => 'details',
      '#title' => $this->t('Action Zone'),
      '#open' => !empty($this->options['field_mapping']['action']['enabled']),
    ];

    $form['field_mapping']['action']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable action zone'),
      '#default_value' => $this->options['field_mapping']['action']['enabled'] ?? TRUE,
    ];

    $form['field_mapping']['action']['field'] = [
      '#type' => 'select',
      '#title' => $this->t('Action field'),
      '#options' => $field_options,
      '#default_value' => $this->options['field_mapping']['action']['field'] ?? '',
      '#description' => $this->t('Select a field that points to media actions'),
      '#states' => [
        'visible' => [
          ':input[name="style_options[field_mapping][action][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Zone 6: Preview (uses media URL automatically)
    $form['field_mapping']['preview'] = [
      '#type' => 'details',
      '#title' => $this->t('Preview Zone'),
      '#open' => !empty($this->options['field_mapping']['preview']['enabled']),
    ];

    $form['field_mapping']['preview']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable preview zone'),
      '#default_value' => $this->options['field_mapping']['preview']['enabled'] ?? TRUE,
      '#description' => $this->t('Display a zoom button to preview the media'),
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    // Validation of field selections if needed
    // Currently no specific validation required.
  }

  /**
   * Get all grouped fields.
   *
   * @return array
   *   Array of field IDs that are assigned to groups.
   */
  protected function getGroupedFields() {
    $grouped = [];
    if (!empty($this->options['field_groups'])) {
      foreach ($this->options['field_groups'] as $group_config) {
        if (!empty($group_config['enabled']) && !empty($group_config['fields'])) {
          foreach ($group_config['fields'] as $field_id => $checked) {
            if ($checked) {
              $grouped[] = $field_id;
            }
          }
        }
      }
    }
    return $grouped;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    // Récupérer les rows rendues par le row plugin.
    $rows = [];
    $media_data = [];

    $build = [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#rows' => [],
      '#attributes' => [
        'class' => ['album-light-table'],
      ],
    ];

    $grouped_rows = $this->renderGrouping(
    $this->view->result,
    $this->options['grouping'],
    TRUE
    );

    // Recursively process the grouped structure.
    $build['#groups'] = $this->processGroupRecursive($grouped_rows,
      $build,
      );

    // Filter out empty groups recursively (groups without albums and without subgroups).
    // $build['#groups'] = $this->filterEmptyGroups($build['#groups']);.
    unset($this->view->row_index);

    // Add grouped fields for template.
    $build['#grouped_fields'] = $this->getGroupedFields();

    // Ajouter les librairies.
    // Dragula must be loaded FIRST before draggable-flexgrid can use it.
    $build['#attached']['library'][] = 'media_album_av_common/dragula';
    $build['#attached']['library'][] = 'media_album_av_common/sortablejs';
    $build['#attached']['library'][] = 'media_album_av_common/draggable-flexgrid';
    $build['#attached']['library'][] = 'media_album_av_common/draggable-flexgrid-light-table-groups';

    // Load VBO libraries if the view uses Views Bulk Operations.
    // This ensures proper styling of checkboxes and bulk actions dropbutton.
    if (!empty($this->view->getHandlers('field')['views_bulk_operations_bulk_form'])) {
      // Load the official dropbutton library (maps to correct theme CSS).
      $build['#attached']['library'][] = 'core/drupal.dropbutton';
      // Load VBO specific libraries and behaviors.
      $build['#attached']['library'][] = 'views_bulk_operations/vbo';
    }

    // Ajouter les settings pour JavaScript.
    $build['#attached']['drupalSettings']['draggableFlexGrid'] = [
      'view_id' => $this->view->id(),
      'display_id' => $this->view->current_display,
    ];
    foreach ($build['#groups'] as $group) {
      // Passer l'album group pour chaque groupe.
      $album_grp[] = $group['album_group'] ?? NULL;
    }
    $build['#attached']['drupalSettings']['dragtool'] = [
      'dragtool' => 'sortable',
      'dragula' => [
        'options' => [
          'revertOnSpill' => TRUE,
          'removeOnSpill' => FALSE,
          'direction' => "horizontal",
          'delay' => 300,
          'delayOnTouchOnly' => TRUE,
          'mirrorContainer' => 'document.body',
          'scroll' => FALSE,
          'albumsGroup' => $album_grp ?? [],
        ],
        'dragitems' => '.js-draggable-item',
        'handler' => '.draggable-flexgrid__handle',
        'containers' => '.js-draggable-flexgrid',
        'excludeSelector' => '.media-drop-info-wrapper',
        'callbacks' => [
          'saveorder' => 'media-drop/draggable-flexgrid/save-order',
        ],
      ],
      'sortable' => [
        'options' => [
          'animation' => 150,
          'delayOnTouchOnly' => TRUE,
          'swapThreshold' => 0.85,
          'touchStartThreshold' => 4,
          'handle' => '.draggable-flexgrid__handle',
          'draggable' => '.js-draggable-item',
          'ghostClass' => 'sortable-ghost',
          'chosenClass' => 'sortable-chosen',
          'scroll' => TRUE,
          'forceAutoScrollFallback' => TRUE,
          'bubbleScroll' => FALSE,
        ],
        'albumsGroup' => $album_grp ?? [],
        'containers' => '.js-draggable-flexgrid',
        'callbacks' => [
          'saveorder' => 'media-drop/draggable-flexgrid/save-order',
        ],
      ],
    ];

    return $build;
  }

  /**
   * Get the media image URL for a given row index and field ID.
   *
   * Used in the Twig template.
   *
   * @param int $index
   *   The row index.
   * @param string $field_id
   *   The field ID containing the image.
   * @param string|null $image_style
   *   (optional) The image style to apply.
   */
  public function getMediaImageSize($index, $field_id, $image_style = NULL) {
    if (!isset($this->view->result[$index])) {
      return [0, 0];
    }

    $row = $this->view->result[$index];
    $entity = $row->_entity;

    // Vérifier que c'est bien une entité media.
    if ($entity->getEntityTypeId() !== 'media') {
      return [0, 0];
    }

    // Récupérer le champ source (généralement field_media_image ou thumbnail)
    $source_field = $entity->getSource()->getSourceFieldDefinition($entity->bundle->entity);
    $field_name = $source_field->getName();

    if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
      $file = $entity->get($field_name)->entity;

      if ($file) {
        $rpath = \Drupal::service('file_system')->realpath($file->getFileUri());
        if (!empty($rpath) && file_exists($rpath)) {
          $image_info = getimagesize($rpath);
          return [
            'width' => $image_info[0],
            'height' => $image_info[1],
          ];
        }
        else {
          return [0, 0];
        }
      }
      else {
        return [0, 0];
      }
    }
    return [0, 0];
  }

  /**
   * Retourne toutes les informations pertinentes d'un media.
   *
   * @param int $index
   *   L'index de la ligne dans la vue.
   *
   * @return array
   *   Tableau contenant les informations du media, ou vide si inexistant.
   */
  public function getMediaFullInfo($index) {
    if (!isset($this->view->result[$index])) {
      return [];
    }

    $row = $this->view->result[$index];
    return $this->getMediaRowFullInfo($row);
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
      // Keep albums as-is (including empty ones) - the template will handle empty states.
      $filtered_albums = $group['albums'] ?? [];

      // Recursively filter subgroups (but keep empty subgroups structure).
      $filtered_subgroups = [];
      if (!empty($group['subgroups'])) {
        $filtered_subgroups = $this->filterEmptyGroups($group['subgroups']);
      }

      // Always keep the group to maintain structure for drag-and-drop.
      // Even if it's empty, it needs a container for dropping media.
      $group['albums'] = $filtered_albums;
      $group['subgroups'] = $filtered_subgroups;
      $filtered[] = $group;
    }

    return $filtered;
  }

  /**
   * Recursively process the grouping structure from Views.
   *
   * @param array $groups
   *   The grouping structure returned by renderGrouping().
   * @param array &$build
   *   The build array (passed by reference to add settings).
   * @param int $depth
   *   Current depth (for debug/styling).
   *
   * @return array
   *   Normalized structure for Twig.
   */
  private function processGroupRecursive(array $groups, array &$build, int $depth = 0, $idx = 0, $album_grp = NULL) {

    $processed = [];

    foreach ($groups as $group_key => $group_data) {
      $idx = rand();
      if ($album_grp == NULL || $depth == 0) {
        $album_grp = $idx;
      }

      // Get the field type for this grouping level.
      $field_type = NULL;
      $field_target_type = NULL;
      if (!empty($this->options['grouping'][$depth])) {
        $grouping_field = $this->options['grouping'][$depth]['field'] ?? NULL;
        if ($grouping_field && !empty($this->view->result)) {
          // Get the first row to determine the entity type.
          $row = reset($this->view->result);
          $entity = $row->_entity ?? NULL;

          if ($entity) {
            $entity_type_id = $entity->getEntityTypeId();

            // Use entity_field.manager service to get field definitions.
            $field_manager = \Drupal::service('entity_field.manager');
            $field_definitions = $field_manager->getFieldDefinitions($entity_type_id, $entity->bundle());

            // Check if the field exists on the main entity.
            if (isset($field_definitions[$grouping_field])) {
              $field_def = $field_definitions[$grouping_field];
              $field_type = $field_def->getType();

              // If it's an entity_reference, get the target type.
              if ($field_type === 'entity_reference') {
                $field_settings = $field_def->getSettings();
                $field_target_type = $field_settings['target_type'] ?? NULL;
              }
            }
            else {
              // Try to check if it's a field on a related entity (via relationship).
              $handlers = $this->view->getHandlers('field');
              if (!empty($handlers[$grouping_field])) {
                $handler = $handlers[$grouping_field];
                // Check if this field is linked to a relationship.
                if (!empty($handler['relationship']) && $handler['relationship'] !== 'none') {
                  $rel_handlers = $this->view->getHandlers('relationship');
                  if (!empty($rel_handlers[$handler['relationship']])) {
                    $rel_handler = $rel_handlers[$handler['relationship']];
                    // Get the relationship field name from the handler.
                    $rel_field_name = $rel_handler['field'] ?? NULL;

                    if ($rel_field_name) {
                      // Get the relationship field definition to extract target type.
                      $rel_field_definitions = $field_manager->getFieldDefinitions($entity_type_id, $entity->bundle());
                      if (isset($rel_field_definitions[$rel_field_name])) {
                        $rel_field_def = $rel_field_definitions[$rel_field_name];
                        $rel_settings = $rel_field_def->getSettings();
                        $target_type = $rel_settings['target_type'] ?? NULL;

                        if ($target_type) {
                          // Get field storage definition for the grouping field on the target entity.
                          $target_field_storage_defs = $field_manager->getFieldStorageDefinitions($target_type);
                          if (isset($target_field_storage_defs[$handler['field']])) {
                            $target_field_storage_def = $target_field_storage_defs[$handler['field']];
                            $field_type = $target_field_storage_def->getType();

                            // If it's an entity_reference, get the target type.
                            if ($field_type === 'entity_reference') {
                              $target_field_settings = $target_field_storage_def->getSettings();
                              $field_target_type = $target_field_settings['target_type'] ?? NULL;
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }

      $group_item = [
        'title' => $group_data['group'] ?? '',
        'level' => $group_data['level'] ?? $depth,
        'albums' => [],
        'subgroups' => [],
        'termid' => $group_key,
        'album_group' => $album_grp,
        'groupid' => 'album-group-' . $idx,
        'field_type' => $field_type,
        'field_target_type' => $field_target_type,
      ];

      // Check if this group contains rows (final results)
      if (isset($group_data['rows']) && is_array($group_data['rows']) && !empty($group_data['rows'])) {

        // Determine if the "rows" are actually other groups or real rows.
        $first_row = reset($group_data['rows']);

        if (is_array($first_row) && isset($first_row['group']) && isset($first_row['level'])) {
          // These are subgroups, process recursively.
          $group_item['subgroups'] = $this->processGroupRecursive(
            $group_data['rows'],
            $build,
            $depth + 1,
            $idx,
            $album_grp
          );
        }
        else {
          $r = $this->buildAlbumDataFromGroup($group_data['rows'], $idx);
          if ($r) {
            $group_item['albums'][] = $r;
          }
        }
      }

      $processed[] = $group_item;
    }

    return $processed;
  }

  /**
   * Build album data from grouped rows.
   *
   * @param array $rows
   *   Array of rows in the same group.
   * @param int $group_index
   *   The group index.
   *
   * @return array|null
   *   The album data or NULL on error.
   */
  private function buildAlbumDataFromGroup($rows, $group_index) {
    $medias = [];

    foreach ($rows as $index => $row) {
      $this->view->row_index = $index;

      // Get the media entity from the row.
      $media = NULL;

      $media = $this->getReferencedMediaEntity($row);

      if (!$media) {
        continue;
      }

      $media_thumbnail = $this->getMediaThumbnail($media, $this->options['image_thumbnail_style'] ?? NULL);
      $media_info = $this->getMediaRowFullInfo($row, $this->options['image_thumbnail_style'] ?? NULL);
      if ($media_thumbnail || $media_info) {
        $medias[] = [
          'thumbnail' => $media_thumbnail,
          'media' => $media_info,
        // To avoid to compute it again in Twig.
          'row_index' => $index,
        ];
      }
    }

    if (empty($medias)) {
      return NULL;
    }

    $album_id = 'album-group-' . $group_index;

    return [
      'id' => $album_id,
      'group_index' => $group_index,
      'medias' => $medias,
    ];
  }

}
