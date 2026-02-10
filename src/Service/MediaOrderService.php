<?php

namespace Drupal\media_album_av_common\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\media_album_av_common\Traits\MediaTrait;

/**
 * Service for handling media order updates.
 */
class MediaOrderService {
  use MediaTrait;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructor.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory')
    );
  }

  /**
   * Save media order and grouping information.
   *
   * @param array $data
   *   The data array containing media order information.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with status.
   */
  public function saveMediaOrder(array $data) {
    $logger = $this->loggerFactory->get('media_album_av_common');

    // Log the received data.
    $logger->info('Media order save request received for view: @view_id, display: @display_id, items: @count',
      [
        '@view_id' => $data['view_id'] ?? 'unknown',
        '@display_id' => $data['display_id'] ?? 'unknown',
        '@count' => count($data['media_order'] ?? []),
      ]
    );

    // Allow other modules to process this data via hooks.
    \Drupal::moduleHandler()->invokeAll('media_album_av_common_media_order_save', [$data]);

    $ret = $this->orderMediaItems($data['media_order'] ?? []);
    return $ret;
  }

  /**
   * Order media items within a node based on weight.
   *
   * Groups media items by node and reorders them.
   * Also updates taxonomy terms if orig_field_name is provided.
   */
  private function orderMediaItems(array $media_items) {
    if (empty($media_items)) {
      return [
        'success' => TRUE,
        'message' => 'No media items to process.',
        'media_received' => count($media_items),
      ];
    }

    $logger = $this->loggerFactory->get('media_album_av_common');
    $etm = \Drupal::entityTypeManager();

    $node_updates = [];
    $media_updates = [];

    foreach ($media_items as $item) {
      $weight = (int) $item['weight'];
      $orig_weight = isset($item['orig_weight']) ? (int) $item['orig_weight'] : NULL;
      $termid = (int) ($item['termid'] ?? 0);
      $orig_termid = isset($item['orig_termid']) ? (int) $item['orig_termid'] : NULL;
      $field_name = $item['field_name'] ?? NULL;

      // ---------- NODE ORDERING ----------
      if (!empty($item['nid']) && !empty($item['media_id']) && $weight !== $orig_weight) {
        $node_updates[$item['nid']][] = [
          'media_id' => (int) $item['media_id'],
          'weight' => $weight,
        ];
      }

      // ---------- MEDIA TAXONOMY UPDATE ----------
      if ($orig_termid !== NULL && $termid !== (int) $orig_termid && !empty($item['field_name'])) {
        if (str_starts_with($item['field_name'], 'media:')) {
          $media_updates[(int) $item['media_id']][] = [
            'field_name' => substr($item['field_name'], 6),
            'termid' => $termid,
          ];
        }
      }
    }

    // ================= MEDIA REORDERING =================
    $processed_media = 0;
    foreach ($node_updates as $nid => $items) {
      try {
        $node = $etm->getStorage('node')->load($nid);
        if (!$node) {
          $logger->warning('Node @nid not found', ['@nid' => $nid]);
          continue;
        }

        $media_field = $this->getMediaReferenceField($node);
        if (!$media_field) {
          $logger->warning('No media reference field found for node @nid', ['@nid' => $nid]);
          continue;
        }

        usort($items, fn($a, $b) => $a['weight'] <=> $b['weight']);

        $new_values = [];
        foreach ($items as $item) {
          $new_values[] = ['target_id' => $item['media_id']];
        }

        $node->set($media_field, $new_values);
        $node->save();
        $processed_media++;

        $logger->info('Updated media ordering for node @nid', ['@nid' => $nid]);
      }
      catch (\Throwable $e) {
        $logger->error('Error reordering media for node @nid: @msg', [
          '@nid' => $nid,
          '@msg' => $e->getMessage(),
        ]);
        return [
          'success' => FALSE,
          'message' => 'Error reordering media: ' . $e->getMessage(),
          'processed_media' => count($processed_media),
        ];
      }
    }

    // ================= MEDIA TAXONOMY UPDATE =================
    $processed_media = 0;
    foreach ($media_updates as $media_id => $updates) {
      try {
        $media = $etm->getStorage('media')->load($media_id);
        if (!$media) {
          $logger->warning('Media @mid not found', ['@mid' => $media_id]);
          continue;
        }

        foreach ($updates as $update) {
          $field_name = $update['field_name'];
          $termid = $update['termid'];

          if (!$media->hasField($field_name)) {
            $logger->warning('Field @field missing on media @mid', [
              '@field' => $field_name,
              '@mid' => $media_id,
            ]);
            continue;
          }

          $current = $media->get($field_name)->target_id ?? NULL;
          if ($current != $termid) {
            $media->set($field_name, ['target_id' => $termid]);
          }
        }

        $media->save();
        $logger->info('Updated taxonomy fields for media @mid', ['@mid' => $media_id]);
        $processed_media++;
      }
      catch (\Throwable $e) {
        $logger->error('Error updating media @mid: @msg', [
          '@mid' => $media_id,
          '@msg' => $e->getMessage(),
        ]);
        return [
          'success' => FALSE,
          'message' => 'Error updating media: ' . $e->getMessage(),
          'processed_media' => count($processed_media),
          'processed_taxonomy' => $processed_media,
        ];
      }
    }
    return [
      'success' => TRUE,
      'message' => 'Media order and taxonomy fields updated successfully.',
      'media_received' => count($media_items),
      'processed_media' => count($processed_media),
      'processed_taxonomy' => $processed_media,
    ];
  }

}
