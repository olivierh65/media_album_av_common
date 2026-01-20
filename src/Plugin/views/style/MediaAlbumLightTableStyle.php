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
use Drupal\media_album_av_common\Traits\CustomFieldsTrait;

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
  use CustomFieldsTrait;
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

    if (($this->view->id() == 'media_drop_manage') && ($this->view->current_display == 'page_1')) {
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
    $field_options = [];
    foreach ($fields as $field_name => $field) {
      $field_options[$field_name] = $field->adminLabel();
    }

    // Section pour la configuration des groupes.
    $form['field_groups'] = [
      '#type' => 'details',
      '#title' => $this->t('Field Groups Configuration'),
      '#open' => TRUE,
      '#weight' => 10,
      '#tree' => TRUE,
    ];

    $form['field_groups']['description'] = [
      '#markup' => '<p>' . $this->t('Organize your fields into groups. Each group will be rendered in a separate container with its own CSS class.') . '</p>',
    ];

    // Créer 10 groupes configurables.
    $num_groups = 10;
    for ($i = 1; $i <= $num_groups; $i++) {
      $group_key = 'group_' . $i;

      if ($manage) {
        switch ($i) {
          case 1:
            $title = '1-' . $this->t('Thumbnail Field');
            break;

          case 2:
            $title = '2-' . $this->t('VBO Actions Field');
            break;

          case 3:
            $title = '3-' . $this->t('Name Field');
            break;

          case 4:
            $title = '4-' . $this->t('Media Details Fields');
            break;

          case 5:
            $title = '5-' . $this->t('Action Field');
            break;

          case 6:
            $title = '6-' . $this->t('Image Preview Field');
            break;

          default:
            $title = $this->t('Group @num', ['@num' => $i]);
            break;
        }
      }
      else {
        $title = $this->t('Group @num', ['@num' => $i]);
      }

      $form['field_groups'][$group_key] = [
        '#type' => 'details',
        '#title' => $title,
        '#open' => !empty($this->options['field_groups'][$group_key]['enabled']),
      ];

      $form['field_groups'][$group_key]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable this group'),
        '#default_value' => $this->options['field_groups'][$group_key]['enabled'] ?? FALSE,
      ];

      $form['field_groups'][$group_key]['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Group label (optional)'),
        '#default_value' => $this->options['field_groups'][$group_key]['label'] ?? '',
        '#description' => $this->t('Leave empty for no label'),
        '#states' => [
          'visible' => [
            ':input[name="style_options[field_groups][' . $group_key . '][enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['field_groups'][$group_key]['css_class'] = [
        '#type' => 'textfield',
        '#title' => $this->t('CSS class'),
        '#default_value' => $this->options['field_groups'][$group_key]['css_class'] ?? 'zone-' . $i,
        '#description' => $this->t('CSS class for this group container'),
        '#states' => [
          'visible' => [
            ':input[name="style_options[field_groups][' . $group_key . '][enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['field_groups'][$group_key]['wrapper_element'] = [
        '#type' => 'select',
        '#title' => $this->t('Wrapper element'),
        '#options' => [
          'div' => 'div',
          'section' => 'section',
          'aside' => 'aside',
          'header' => 'header',
          'footer' => 'footer',
          'nav' => 'nav',
        ],
        '#default_value' => $this->options['field_groups'][$group_key]['wrapper_element'] ?? 'div',
        '#states' => [
          'visible' => [
            ':input[name="style_options[field_groups][' . $group_key . '][enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      if (!empty($field_options)) {
        $form['field_groups'][$group_key]['fields'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Fields in this group'),
          '#options' => $field_options,
          '#default_value' => $this->options['field_groups'][$group_key]['fields'] ?? [],
          '#description' => $this->t('Select the fields to include in this group. Fields can only belong to one group.'),
          '#states' => [
            'visible' => [
              ':input[name="style_options[field_groups][' . $group_key . '][enabled]"]' => ['checked' => TRUE],
            ],
          ],
        ];
      }
    }

    $form['show_ungrouped'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show ungrouped fields'),
      '#default_value' => $this->options['show_ungrouped'] ?? TRUE,
      '#description' => $this->t('If checked, fields not assigned to any group will be displayed in an "ungrouped" container at the end.'),
      '#weight' => 100,
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    // Vérifier qu'un champ n'est pas dans plusieurs groupes.
    $field_groups = $form_state->getValue(['style_options', 'field_groups']);
    $assigned_fields = [];
    $duplicates = [];

    if ($field_groups) {
      foreach ($field_groups as $group_id => $group_config) {
        if (!empty($group_config['enabled']) && !empty($group_config['fields'])) {
          foreach ($group_config['fields'] as $field_id => $checked) {
            if ($checked) {
              if (isset($assigned_fields[$field_id])) {
                $duplicates[$field_id] = $field_id;
              }
              $assigned_fields[$field_id] = $group_id;
            }
          }
        }
      }
    }

    if (!empty($duplicates)) {
      $form_state->setError(
        $form['field_groups'],
        $this->t('The following fields are assigned to multiple groups: @fields. Each field can only belong to one group.',
          ['@fields' => implode(', ', $duplicates)]
        )
      );
    }
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

    // Media field should not have an mid == null.
    // Hide empty results in views itself.
    // Get grouped results from Views.
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

    // Ajouter les librairies.
    $build['#attached']['library'][] = 'media_drop/draggable_flexgrid';
    $build['#attached']['library'][] = 'media_album_av_common/dragula';
    $build['#attached']['library'][] = 'media_album_av_common/media-light-table';

    // Ajouter les settings pour JavaScript.
    $build['#attached']['drupalSettings']['draggableFlexGrid'] = [
      'view_id' => $this->view->id(),
      'display_id' => $this->view->current_display,
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
  private function processGroupRecursive(array $groups, array &$build, int $depth = 0, $idx = 0) {

    $processed = [];

    foreach ($groups as $group_key => $group_data) {
      $idx = rand();
      $group_item = [
        'title' => $group_data['group'] ?? '',
        'level' => $group_data['level'] ?? $depth,
        'albums' => [],
        'subgroups' => [],
        'termid' => $group_key,
        'groupid' => 'album-group-' . $idx,
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
            $idx
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
