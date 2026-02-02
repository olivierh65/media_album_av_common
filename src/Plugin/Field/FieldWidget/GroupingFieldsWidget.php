<?php

namespace Drupal\media_album_av_common\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\media_album_av_common\Service\AlbumGroupingFieldsService;
use Drupal\media_album_av_common\Service\DirectoryService;
use Drupal\Component\Utility\NestedArray;

/**
 * Plugin implementation of the 'grouping_fields_widget'.
 *
 * @FieldWidget(
 * id = "grouping_fields_widget",
 * label = @Translation("Grouping Fields Selector"),
 * field_types = {
 * "list_string"
 * },
 * multiple_values = TRUE
 * )
 */
class GroupingFieldsWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The grouping fields service.
   *
   * @var \Drupal\media_album_av_common\Service\AlbumGroupingFieldsService
   */
  protected $groupingFieldsService;

  /**
   * The directory service.
   *
   * @var \Drupal\media_album_av_common\Service\DirectoryService
   */
  protected $directoryService;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, AlbumGroupingFieldsService $grouping_fields_service, DirectoryService $directory_service) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->groupingFieldsService = $grouping_fields_service;
    $this->directoryService = $directory_service;
  }

  /**
   *
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('media_album_av_common.grouping_fields'),
      $container->get('media_album_av_common.directory_service')
    );
  }

  /**
   *
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['#attached']['library'][] = 'core/drupal.tabledrag';
    $element['#attached']['library'][] = 'core/drupal.ajax';

    $field_options = $this->getFieldOptions();
    if (empty($field_options)) {
      $element['warning'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--warning">' . $this->t('No grouping fields available.') . '</div>',
      ];
      return $element;
    }

    $element['grouping_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Define grouping hierarchy'),
    ];

    $element['grouping_fieldset']['grouping_table'] = [
      '#type' => 'table',
      '#header' => [$this->t('Level'), $this->t('Field configuration'), $this->t('Weight')],
      '#attributes' => ['id' => 'grouping-fields-table'],
      '#tabledrag' => [['action' => 'order', 'relationship' => 'sibling', 'group' => 'grouping-order-weight']],
    ];

    // Calcul des valeurs pour chaque ligne.
    $stored_values = [];
    foreach ($items as $item) {
      if (!empty($item->value)) {
        $stored_values[] = $item->value;
      }
    }
    // Ligne pour nouvel ajout.
    $stored_values[] = '';

    foreach ($stored_values as $d => $value) {
      $wrapper_id = 'terms-wrapper-' . $d;

      // --- DÉTECTION DE LA VALEUR SÉLECTIONNÉE (AJAX ou DB) ---
      $user_input = $form_state->getUserInput();
      $field_name = $this->fieldDefinition->getName();
      $input_path = [$field_name, 'grouping_fieldset', 'grouping_table', $d, 'field_container', 'field'];
      $current_selection = NestedArray::getValue($user_input, $input_path);

      if ($current_selection !== NULL) {
        $selected_field = $current_selection;
        $data = ['field' => $selected_field, 'terms' => []];
      }
      else {
        $data = json_decode($value, TRUE) ?: ['field' => $value, 'terms' => []];
        $selected_field = $data['field'] ?? '';
      }

      $element['grouping_fieldset']['grouping_table'][$d]['#attributes']['class'] = ['draggable'];

      $element['grouping_fieldset']['grouping_table'][$d]['level'] = [
        '#markup' => '<strong>' . $this->t('Level @level', ['@level' => $d + 1]) . '</strong>',
      ];

      // Conteneur de configuration dans la cellule centrale.
      $element['grouping_fieldset']['grouping_table'][$d]['field_container'] = [
        '#type' => 'container',
      ];

      $element['grouping_fieldset']['grouping_table'][$d]['field_container']['field'] = [
        '#type' => 'select',
        '#options' => ['' => $this->t('- Select field -')] + $field_options,
        '#default_value' => $selected_field,
        '#ajax' => [
          'callback' => [static::class, 'ajaxRefreshTerms'],
          'wrapper' => $wrapper_id,
          'effect' => 'fade',
        ],
      ];

      // Le wrapper AJAX.
      $element['grouping_fieldset']['grouping_table'][$d]['field_container']['terms_config'] = [
        '#type' => 'container',
        '#attributes' => ['id' => $wrapper_id],
      ];

      // Affichage des termes si sélectionné.
      if (!empty($selected_field)) {
        // Extract entity type and field name from the selected field.
        $field_parts = explode(':', $selected_field);
        $entity_type = count($field_parts) === 2 ? $field_parts[0] : 'media';
        $clean_field_name = end($field_parts);

        // Get the vocabulary ID based on entity type and node context.
        $vocab_id = $this->getVocabularyForField($clean_field_name, $entity_type, $form_state);

        $terms = $this->getTermsForField($clean_field_name, $entity_type, $vocab_id);

        if (!empty($terms)) {
          $element['grouping_fieldset']['grouping_table'][$d]['field_container']['terms_config']['details'] = [
            '#type' => 'details',
            '#title' => $this->t('Ordre des termes pour %field', ['%field' => $clean_field_name]),
            '#open' => TRUE,
          ];

          $element['grouping_fieldset']['grouping_table'][$d]['field_container']['terms_config']['details']['term_order'] = [
            '#type' => 'table',
            '#header' => [$this->t('Terme'), $this->t('Poids')],
            '#tabledrag' => [['action' => 'order', 'relationship' => 'sibling', 'group' => 'term-weight-' . $d]],
          ];

          foreach ($terms as $tid => $term_label) {
            $weight = $data['terms'][$tid] ?? 0;
            $element['grouping_fieldset']['grouping_table'][$d]['field_container']['terms_config']['details']['term_order'][$tid] = [
              '#attributes' => ['class' => ['draggable']],
              'label' => ['#markup' => $term_label],
              'weight' => [
                '#type' => 'weight',
                '#default_value' => $weight,
                '#attributes' => ['class' => ['term-weight-' . $d]],
                '#delta' => 50,
              ],
            ];
          }
        }
      }

      $element['grouping_fieldset']['grouping_table'][$d]['weight'] = [
        '#type' => 'weight',
        '#default_value' => $d,
        '#attributes' => ['class' => ['grouping-order-weight']],
      ];
    }

    return $element;
  }

  /**
   *
   */
  public static function ajaxRefreshTerms(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    // On remonte de 'field' vers 'terms_config'.
    array_pop($array_parents);
    $array_parents[] = 'terms_config';

    return NestedArray::getValue($form, $array_parents);
  }

  /**
   *
   */
  protected function getFieldOptions() {
    $options = [];
    $node_fields = $this->groupingFieldsService->getNodeFields();
    foreach ($node_fields as $fn => $cfg) {
      $options['node:' . $fn] = $cfg['label'] . ' (Album)';
    }
    $media_fields = $this->groupingFieldsService->getMediaFields();
    foreach ($media_fields as $fn => $cfg) {
      $options['media:' . $fn] = $cfg['label'] . ' (Media)';
    }
    return $options;
  }

  /**
   *
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $result = [];
    $rows = $values['grouping_fieldset']['grouping_table'] ?? [];
    uasort($rows, function ($a, $b) {
      return (int) $a['weight'] <=> (int) $b['weight'];
    });

    foreach ($rows as $row) {
      if (!empty($row['field_container']['field'])) {
        $storage = [
          'field' => $row['field_container']['field'],
          'terms' => [],
        ];
        if (!empty($row['field_container']['terms_config']['details']['term_order'])) {
          foreach ($row['field_container']['terms_config']['details']['term_order'] as $tid => $term_row) {
            $storage['terms'][$tid] = $term_row['weight'];
          }
          asort($storage['terms']);
        }
        $result[] = ['value' => json_encode($storage)];
      }
    }
    return $result;
  }

  /**
   * Get taxonomy terms for a field reference.
   *
   * Simple version: uses DirectoryService to load terms from vocabulary.
   *
   * @param string $field_name
   *   The field name (without entity type prefix).
   * @param string $entity_type
   *   The entity type (e.g., 'media', 'node'). Defaults to 'media'.
   * @param string|null $vocab_id
   *   The vocabulary ID. If provided, only terms from this vocab are loaded.
   *
   * @return array
   *   Array of terms keyed by tid with name as value, or empty array.
   */
  protected function getTermsForField($field_name, $entity_type = 'media', $vocab_id = NULL) {
    try {
      if (!$vocab_id) {
        return [];
      }

      // Use DirectoryService to load terms properly.
      $tree_data = $this->directoryService->getDirectoryTreeData($vocab_id);

      // Convert tree structure to flat options array.
      $options = [];
      $this->flattenTreeToOptions($tree_data, $options);

      return $options;
    }
    catch (\Exception $e) {
      \Drupal::logger('media_album_av_common')->warning(
        'Error getting terms for field @field on @type: @message',
        [
          '@field' => $field_name,
          '@type' => $entity_type,
          '@message' => $e->getMessage(),
        ]
          );
      return [];
    }
  }

  /**
   * Helper to flatten jstree data to options array.
   *
   * @param array $tree_data
   *   Tree data from DirectoryService.
   * @param array $options
   *   Options array to populate (by reference).
   * @param int $depth
   *   Current depth for indentation.
   */
  protected function flattenTreeToOptions(array $tree_data, array &$options, $depth = 0) {
    $prefix = str_repeat('-- ', $depth);

    foreach ($tree_data as $node) {
      $options[$node['id']] = $prefix . $node['text'];

      if (!empty($node['children'])) {
        $this->flattenTreeToOptions($node['children'], $options, $depth + 1);
      }
    }
  }

  /**
   * Get the vocabulary ID for a field based on entity context.
   *
   * @param string $field_name
   *   The field name (without entity type prefix).
   * @param string $entity_type
   *   The entity type (e.g., 'media', 'node').
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state to extract entity context.
   *
   * @return string|null
   *   The vocabulary ID, or NULL if not determined.
   */
  protected function getVocabularyForField($field_name, $entity_type, FormStateInterface $form_state) {
    try {
      $entity = $form_state->getFormObject()->getEntity();

      if ($entity_type === 'node' && $entity->getEntityTypeId() === 'node') {
        // For node fields, get vocabulary from the node field config.
        $field_manager = \Drupal::service('entity_field.manager');
        $fields = $field_manager->getFieldDefinitions('node', $entity->bundle());

        if (isset($fields[$field_name])) {
          $settings = $fields[$field_name]->getSettings();
          if ($settings['target_type'] === 'taxonomy_term') {
            $target_bundles = $settings['handler_settings']['target_bundles'] ?? NULL;
            if (!empty($target_bundles) && is_array($target_bundles)) {
              return reset($target_bundles);
            }
          }
        }
      }
      elseif ($entity_type === 'media') {
        // For media fields, get vocabulary directly from media_type field config.
        // The field is attached to the media type itself, not through node reference.
        if ($entity->getEntityTypeId() === 'node') {
          $field_manager = \Drupal::service('entity_field.manager');
          $node_fields = $field_manager->getFieldDefinitions('node', $entity->bundle());

          // Look for media reference field (e.g., field_media_album_av_media).
          foreach ($node_fields as $fname => $fdef) {
            if ($fdef->getType() === 'entity_reference' && $fdef->getSetting('target_type') === 'media') {
              $handler_settings = $fdef->getSetting('handler_settings');
              $media_types = $handler_settings['target_bundles'] ?? [];

              if (!empty($media_types)) {
                // Loop through all media types to find the field with its taxonomy.
                // use the first matching one.
                foreach ($media_types as $media_type) {
                  $media_fields = $field_manager->getFieldDefinitions('media', $media_type);

                  if (isset($media_fields[$field_name])) {
                    $settings = $media_fields[$field_name]->getSettings();
                    if ($settings['target_type'] === 'taxonomy_term') {
                      $target_bundles = $settings['handler_settings']['target_bundles'] ?? NULL;
                      if (!empty($target_bundles) && is_array($target_bundles)) {
                        return reset($target_bundles);
                      }
                    }
                  }
                }
              }
              break;
            }
          }
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('media_album_av_common')->debug(
        'Could not determine vocabulary for field @field: @message',
        [
          '@field' => $field_name,
          '@message' => $e->getMessage(),
        ]
          );
    }

    return NULL;
  }

}
