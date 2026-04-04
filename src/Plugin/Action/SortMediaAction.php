<?php

namespace Drupal\media_album_av_common\Plugin\Action;

use Drupal\media_album_av_common\Service\DirectoryService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\MessageCommand;

/**
 * Sort selected media by various criteria.
 *
 * @Action(
 *   id = "media_drop_sort_media",
 *   label = @Translation("Sort media"),
 *   type = "media",
 *   category = @Translation("Media Drop"),
 *   confirm = TRUE,
 *   configurable = TRUE,
 *   prepare_js_function = "prepareSortData",
 * )
 */
class SortMediaAction extends BaseAlbumAction {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, DirectoryService $taxonomy_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $taxonomy_service);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'sort_by' => 'title',
      'sort_order' => 'ASC',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['sort_config'] = [
      '#type' => 'details',
      '#title' => $this->t('Sort Configuration'),
      '#open' => TRUE,
    ];

    $form['sort_config']['sort_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort by'),
      '#options' => [
        'filename' => $this->t('Filename'),
        'title' => $this->t('Title (alphabetical)'),
        'created' => $this->t('Created date'),
        'updated' => $this->t('Updated date'),
      ],
      '#default_value' => $this->configuration['sort_by'],
      '#required' => TRUE,
    ];

    $form['sort_config']['sort_order'] = [
      '#type' => 'radios',
      '#title' => $this->t('Sort order'),
      '#options' => [
        'ASC' => $this->t('Ascending (A→Z, oldest→newest)'),
        'DESC' => $this->t('Descending (Z→A, newest→oldest)'),
      ],
      '#default_value' => $this->configuration['sort_order'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['sort_by'] = $form_state->getValue('sort_by');
    $this->configuration['sort_order'] = $form_state->getValue('sort_order');
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $data) {
    try {
      $selected_items = $this->configuration['selected_media'] ?? [];
      $all_items      = $this->configuration['all_media'] ?? [];

      if (empty($selected_items) || empty($all_items)) {
        return [
          'status' => 'warning',
          'response' => [new MessageCommand($this->t('No media to sort.'), NULL, ['type' => 'warning'])],
          'reordered_ids' => [],
        ];
      }

      // 1. Extraire les media_id des selected_items.
      $selected_ids = array_map(fn($item) => (string) $item['media_id'], $selected_items);
      $all_ids      = array_map(fn($item) => (string) $item['media_id'], $all_items);

      // 2. Charger les entités des sélectionnés.
      $media_storage = \Drupal::entityTypeManager()->getStorage('media');
      $entities = [];
      foreach ($selected_ids as $media_id) {
        $rev_id = $media_storage->getLatestRevisionId($media_id);
        if ($rev_id) {
          $entities[$media_id] = $media_storage->loadRevision($rev_id);
        }
      }

      // 3. Trier les IDs sélectionnés selon le critère.
      $sorted_selected_ids = $this->sortMediaIds($entities, $this->configuration['sort_by'], $this->configuration['sort_order']);

      // 4. Réinsérer les sélectionnés triés dans all_ids, aux positions originales.
      $reordered_ids = $this->mergeOrderedSelection($all_ids, $selected_ids, $sorted_selected_ids);

      return [
        'status'        => 'success',
        'response'      => [
          new MessageCommand(
          $this->t('@count media sorted.', ['@count' => count($sorted_selected_ids)]),
          NULL,
          ['type' => 'status']
          ),
        ],
        'reordered_ids' => $reordered_ids,
      ];
    }
    catch (\Exception $e) {
      \Drupal::logger('media_album_av_common')->error('Sort error: @msg', ['@msg' => $e->getMessage()]);
      return [
        'status'        => 'error',
        'response'      => [new MessageCommand($this->t('Sort failed.'), NULL, ['type' => 'error'])],
        'reordered_ids' => [],
      ];
    }
  }

  /**
   * Trie les entités et retourne leurs IDs dans l'ordre.
   */
  protected function sortMediaIds(array $entities, string $sort_by, string $sort_order): array {
    $sort_data = [];
    foreach ($entities as $id => $media) {
      $sort_data[$id] = $this->getMediaSortValue($media, $sort_by);
    }
    $sort_order === 'DESC' ? arsort($sort_data) : asort($sort_data);
    return array_keys($sort_data);
  }

  /**
   * Réinsère les sélectionnés triés aux positions originales dans la liste complète.
   *
   * Exemple :
   *   all_ids      = [A, B, C, D, E]  (ordre DOM actuel)
   *   selected_ids = [D, B]           (positions originales: 3, 1)
   *   sorted       = [B, D]           (après tri alphabétique)
   *   résultat     = [A, B, C, D, E]  ← B reste en pos 1, D en pos 3
   *   ... si sorted=[D,B] → [A, D, C, B, E]
   */
  protected function mergeOrderedSelection(array $all_ids, array $selected_ids, array $sorted_selected_ids): array {
    // Positions originales des sélectionnés dans all_ids.
    $positions = [];
    foreach ($all_ids as $pos => $id) {
      if (in_array($id, $selected_ids)) {
        $positions[] = $pos;
      }
    }

    // Remettre les sélectionnés triés aux mêmes positions.
    $result = $all_ids;
    foreach ($positions as $i => $pos) {
      $result[$pos] = $sorted_selected_ids[$i];
    }

    return array_values($result);
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // For single entity execution (fallback).
    return NULL;
  }

  /**
   * Get the sort value for a media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   * @param string $sort_by
   *   Sorting criteria.
   *
   * @return string|int
   *   The sort value.
   */
  protected function getMediaSortValue($media, $sort_by) {
    switch ($sort_by) {
      case 'filename':
        if ($media->hasField('field_media_image') && !$media->get('field_media_image')->isEmpty()) {
          $file = $media->get('field_media_image')->entity;
          if ($file) {
            return strtolower($file->getFilename());
          }
        }
        if ($media->hasField('field_media_document') && !$media->get('field_media_document')->isEmpty()) {
          $file = $media->get('field_media_document')->entity;
          if ($file) {
            return strtolower($file->getFilename());
          }
        }
        return strtolower($media->getName());

      case 'created':
        return $media->getCreatedTime();

      case 'updated':
        return $media->getChangedTime();

      case 'title':
      default:
        return strtolower($media->getName());
    }
  }

}
