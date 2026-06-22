<?php

namespace Drupal\media_album_av_common\Plugin\Action;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media_album_av_common\Service\DirectoryService;
use Drupal\media_drop\Traits\MediaFieldFilterTrait;
use Drupal\media_album_av_common\Traits\FieldWidgetBuilderTrait;
use Drupal\Core\Ajax\MessageCommand;

/**
 * Bulk edit media with common field values grouping.
 *
 * @Action(
 *   id = "media_drop_bulk_edit",
 *   label = @Translation("Edit media (grouped)"),
 *   type = "media",
 *   category = @Translation("Media Drop"),
 *   confirm = TRUE,
 *   configurable = TRUE,
 *   prepare_js_function = "prepareBulkEditData",
 * )
 */
class BulkEditMediaAction extends BaseAlbumAction {

  use MediaFieldFilterTrait;
  use FieldWidgetBuilderTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Media entities to edit.
   *
   * @var array
   */
  protected $mediaEntities = [];

  /**
   * Constructs a BulkEditMediaAction object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    DirectoryService $taxonomy_service,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $taxonomy_service);
    $this->entityTypeManager = $entity_type_manager;
    $this->taxonomyService = $taxonomy_service;
  }

  /**
   * Get the entity type manager.
   */
  protected function getEntityTypeManager() {
    return $this->entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'field_values' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    // Keep hidden state handling consistent with other action plugins.
    $form = parent::buildConfigurationForm($form, $form_state);

    // =========================================================================
    // STEP 2: Build the configuration form
    // =========================================================================
    $common_fields = $this->analyzeCommonFields();

    $form['info'] = [
      '#markup' => '<div class="messages messages--status">' .
      $this->t('The fields below show values common to selected media. Media with the same values are grouped together.') .
      '</div>',
    ];

    $form['summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Selection summary (@count media)', ['@count' => count($this->mediaEntities)]),
      '#open' => TRUE,
    ];

    $form['summary']['list'] = [
      '#markup' => $this->buildSummaryList($common_fields),
    ];

    $form['fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Modify fields'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    if (!empty($this->mediaEntities)) {
      $first_media = reset($this->mediaEntities);
      $bundle = $first_media->bundle();

      // Use the trait method to get filterable fields.
      $field_definitions = $this->getFilterableCustomFields($bundle);

      foreach ($field_definitions as $field_name => $field_definition) {
        $field_label = $field_definition->getLabel();

        $common_value = $this->getCommonFieldValue($field_name);
        $value_counts = $this->getFieldValueCounts($field_name);

        $form['fields'][$field_name] = [
          '#type' => 'details',
          '#title' => $field_label,
          '#open' => FALSE,
        ];

        $form['fields'][$field_name]['info'] = [
          '#markup' => $this->buildFieldValueSummary($value_counts),
        ];

        $form['fields'][$field_name]['toggles'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['field-actions']],
        ];

        $form['fields'][$field_name]['toggles']['modify'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Modify this field'),
          '#default_value' => FALSE,
        ];

        $form['fields'][$field_name]['toggles']['clear'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Clear this field'),
          '#default_value' => FALSE,
        ];

        $widget = $this->buildFieldWidget($field_definition, $common_value);

        // Ajouter #autocreate pour les taxonomies si l'option est activée,
        // comme dans MoveMediaToAlbumAction.
        $taxonomy_autocreate_enabled = NULL;
        if (method_exists($field_definition, 'getType') &&
          $field_definition->getType() === 'entity_reference' &&
          $field_definition->getSetting('target_type') === 'taxonomy_term') {
          $taxonomy_autocreate_enabled = FALSE;
          $handler_settings = $field_definition->getSetting('handler_settings') ?? [];
          $target_bundles = !empty($handler_settings['target_bundles'])
            ? $handler_settings['target_bundles'] : NULL;
          $vocabulary_id = $target_bundles
            ? array_key_first($target_bundles)
            : (($handler_settings['auto_create_bundle'] ?? NULL) ?: NULL);

          if ($vocabulary_id && $this->isAutocreateVocabulary($vocabulary_id, $field_name)) {
            $widget['#autocreate'] = ['bundle' => $vocabulary_id];
            $taxonomy_autocreate_enabled = TRUE;
          }
        }

        // Indication visuelle de l'état d'autocreate, comme dans MoveMediaToAlbumAction.
        if ($taxonomy_autocreate_enabled === TRUE) {
          $autocreate_markup = '<p class="media-album-av-autocreate-status is-possible"><strong>'
            . $this->t('Taxonomy autocreate: possible (new terms can be created).')
            . '</strong></p>';
        }
        elseif ($taxonomy_autocreate_enabled === FALSE) {
          $autocreate_markup = '<p class="media-album-av-autocreate-status is-impossible"><strong>'
            . $this->t('Taxonomy autocreate: impossible (only existing terms can be selected).')
            . '</strong></p>';
        }
        else {
          $autocreate_markup = '';
        }

        if (!empty($autocreate_markup)) {
          $form['fields'][$field_name]['autocreate_status'] = [
            '#markup' => $autocreate_markup,
          ];
        }

        $form['fields'][$field_name]['value'] = $widget;

        $form['fields'][$field_name]['value']['#states'] = [
          'visible' => [
            ':input[name="fields[' . $field_name . '][toggles][modify]"]' => ['checked' => TRUE],
            ':input[name="fields[' . $field_name . '][toggles][clear]"]' => ['checked' => FALSE],
          ],
          'required' => [
            ':input[name="fields[' . $field_name . '][toggles][modify]"]' => ['checked' => TRUE],
          ],
        ];
      }
    }

    return $form;
  }

  /**
   * Analyze common fields among media.
   */
  protected function analyzeCommonFields() {
    $analysis = [];

    if (empty($this->mediaEntities)) {
      return $analysis;
    }

    $by_bundle = [];
    foreach ($this->mediaEntities as $media) {
      $bundle = $media->bundle();
      if (!isset($by_bundle[$bundle])) {
        $by_bundle[$bundle] = [];
      }
      $by_bundle[$bundle][] = $media;
    }

    $analysis['bundles'] = $by_bundle;

    return $analysis;
  }

  /**
   * Build a summary of selected media.
   */
  protected function buildSummaryList($common_fields) {
    $output = '<ul>';

    foreach ($common_fields['bundles'] as $bundle => $medias) {
      $bundle_label = $this->entityTypeManager
        ->getStorage('media_type')
        ->load($bundle)
        ->label();

      $output .= '<li><strong>' . $bundle_label . '</strong> : ' . count($medias) . ' media</li>';
    }

    $output .= '</ul>';

    return $output;
  }

  /**
   * Get common value for a field (or NULL if different).
   */
  protected function getCommonFieldValue($field_name) {
    $values = [];

    foreach ($this->mediaEntities as $media) {
      if ($media->hasField($field_name) && !$media->get($field_name)->isEmpty()) {
        // Serialize the field to easily compare and deduplicate complex values.
        $values[] = serialize($media->get($field_name)->getValue());
      }
    }

    $unique_values = array_unique($values);

    if (count($unique_values) === 1) {
      // Unserialize the value to get the original field value.
      return unserialize(reset($unique_values));
    }

    return NULL;
  }

  /**
   * Count different values for a field.
   */
  protected function getFieldValueCounts($field_name) {
    $counts = [];

    foreach ($this->mediaEntities as $media) {
      if ($media->hasField($field_name)) {
        if ($media->get($field_name)->isEmpty()) {
          $key = '(empty)';
        }
        else {
          $value = $media->get($field_name)->getString();
          $key = mb_strlen($value) > 50 ? mb_substr($value, 0, 50) . '...' : $value;
        }

        if (!isset($counts[$key])) {
          $counts[$key] = 0;
        }
        $counts[$key]++;
      }
    }

    return $counts;
  }

  /**
   * Build a summary of field values.
   */
  protected function buildFieldValueSummary($value_counts) {
    if (empty($value_counts)) {
      return '<em>' . $this->t('No values') . '</em>';
    }

    if (count($value_counts) === 1) {
      $value = key($value_counts);
      return '<div class="messages messages--status">' .
        $this->t('Common value: <strong>@value</strong> (@count media)', [
          '@value' => $value,
          '@count' => reset($value_counts),
        ]) . '</div>';
    }

    $output = '<div class="messages messages--warning">' .
      $this->t('Multiple values:') . '<ul>';

    foreach ($value_counts as $value => $count) {
      $output .= '<li><strong>' . $value . '</strong> : ' . $count . ' media</li>';
    }

    $output .= '</ul></div>';

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['field_values'] = [];
    $this->configuration['clear_fields'] = [];

    $fields = $form_state->getValue('fields');

    if (!is_array($fields)) {
      return;
    }

    foreach ($fields as $field_name => $field_data) {
      $actions = $field_data['toggles'] ?? [];

      if (!empty($actions['clear'])) {
        $this->configuration['clear_fields'][] = $field_name;
      }
      elseif (!empty($actions['modify'])) {
        $this->configuration['field_values'][$field_name] = $field_data['value'];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity) {
      return;
    }

    // Appliquer les valeurs de champs via la méthode type-aware de BaseAlbumAction
    // (gère les taxonomies avec autocreate, entity_reference, boolean, etc.).
    $field_values = $this->configuration['field_values'] ?? [];
    foreach ($field_values as $field_name => $value) {
      $this->applySingleFieldValueToMedia($entity, $field_name, $value);
    }

    // Vider les champs marqués "clear".
    $clear_fields = $this->configuration['clear_fields'] ?? [];
    foreach ($clear_fields as $field_name) {
      if ($entity->hasField($field_name)) {
        $entity->set($field_name, NULL);
      }
    }

    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    $this->mediaEntities = $entities;
    $done = 0;

    foreach ($entities as $entity) {
      $this->execute($entity);
      $done++;
    }

    $status = $done > 0 ? 'success' : 'warning';
    $msg_type = $done > 0 ? 'status' : 'warning';

    return [
      'status'   => $status,
      'response' => [
        new MessageCommand(
          $this->t('@count media updated.', ['@count' => $done]),
          NULL,
          ['type' => $msg_type]
        ),
      ],
      'edited_ids' => array_keys($entities),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\media\MediaInterface $object */
    $access = $object->access('update', $account, TRUE);

    return $return_as_object ? $access : $access->isAllowed();
  }

}
