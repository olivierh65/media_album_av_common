<?php

namespace Drupal\media_album_av_common\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\media_album_av_common\Traits\MediaTrait;

/**
 * Controller for handling media order updates.
 */
class MediaOrderController extends ControllerBase {
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
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with status.
   */
  public function saveMediaOrder(Request $request) {
    $logger = $this->loggerFactory->get('media_album_av_common');

    // Only accept POST requests.
    if ($request->getMethod() !== 'POST') {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Invalid request method',
      ], 400);
    }

    // Verify it's an AJAX request.
    if ($request->headers->get('X-Requested-With') !== 'XMLHttpRequest') {
      $logger->warning('Media order save request without XMLHttpRequest header');
    }

    // Get JSON data.
    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    if (!$data) {
      $logger->error('Invalid JSON data received');
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Invalid JSON data',
      ], 400);
    }

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

    $this->orderMediaItems($data['media_order'] ?? []);
    return new JsonResponse([
      'success' => TRUE,
      'message' => 'Media order saved successfully',
      'data_received' => count($data['media_order'] ?? []) . ' items processed',
    ]);
  }

  /**
   * Order media items within a node based on weight.
   *
   * Groups media items by node and reorders them.
   * Also updates taxonomy terms if orig_field_name is provided.
   */
  private function orderMediaItems(array $media_items) {
    if (empty($media_items)) {
      return;
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

    // ================= NODE REORDERING =================
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

        $logger->info('Updated media ordering for node @nid', ['@nid' => $nid]);
      }
      catch (\Throwable $e) {
        $logger->error('Error reordering media for node @nid: @msg', [
          '@nid' => $nid,
          '@msg' => $e->getMessage(),
        ]);
      }
    }

    // ================= MEDIA TAXONOMY UPDATE =================
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
      }
      catch (\Throwable $e) {
        $logger->error('Error updating media @mid: @msg', [
          '@mid' => $media_id,
          '@msg' => $e->getMessage(),
        ]);
      }
    }
  }

}
