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
    $entity_type_manager = \Drupal::entityTypeManager();

    // Group items by nid.
    $items_by_nid = [];
    // Group items by termid for taxonomy updates.
    $items_by_termid = [];

    foreach ($media_items as $item) {
      if (!empty($item['nid'])) {
        $nid = $item['nid'];
        if (!isset($items_by_nid[$nid])) {
          $items_by_nid[$nid] = [];
        }
        $items_by_nid[$nid][] = $item;
      }

      if (!empty($item['termid']) && !empty($item['field_name'])) {
        $termid = $item['termid'];
        if (!isset($items_by_termid[$termid])) {
          $items_by_termid[$termid] = [];
        }
        $items_by_termid[$termid][] = $item;
      }
    }

    // Process each node.
    foreach ($items_by_nid as $nid => $items) {
      try {
        // Load the node.
        $node = $entity_type_manager->getStorage('node')->load($nid);
        if (!$node) {
          $logger->warning('Node @nid not found', ['@nid' => $nid]);
          continue;
        }

        // Get the media reference field.
        $media_field = $this->getMediaReferenceField($node);
        if (!$media_field) {
          $logger->warning('No media reference field found for node @nid', ['@nid' => $nid]);
          continue;
        }

        // Sort items by weight.
        usort($items, function ($a, $b) {
          return $a['weight'] <=> $b['weight'];
        });

        // Get current media references.
        $field_value = $node->get($media_field)->getValue();
        $current_medias = [];
        foreach ($field_value as $delta => $ref) {
          if (isset($ref['target_id'])) {
            $current_medias[$ref['target_id']] = $delta;
          }
        }

        // Build new field values with correct deltas.
        $new_field_values = [];
        foreach ($items as $index => $item) {
          $media_id = $item['media_id'];

          if (isset($current_medias[$media_id])) {
            // Media exists, update delta if needed.
            $old_delta = $current_medias[$media_id];
            $new_delta = $index;

            if ($old_delta != $new_delta) {
              // Delta changed, need to reorder.
              $new_field_values[$new_delta] = [
                'target_id' => $media_id,
              ];
              $logger->info('Reordering media @media_id from delta @old_delta to @new_delta in node @nid',
                [
                  '@media_id' => $media_id,
                  '@old_delta' => $old_delta,
                  '@new_delta' => $new_delta,
                  '@nid' => $nid,
                ]
              );
            }
            else {
              // Delta unchanged, keep as is.
              $new_field_values[$new_delta] = [
                'target_id' => $media_id,
              ];
            }
          }
        }

        // Update the field with new ordering.
        if (!empty($new_field_values)) {
          // Re-index the array to ensure proper delta sequence.
          $new_field_values = array_values($new_field_values);
          $node->set($media_field, $new_field_values);
          $node->save();

          $logger->info('Media order updated for node @nid, @count items reordered',
            [
              '@nid' => $nid,
              '@count' => count($new_field_values),
            ]
          );
        }
      }
      catch (\Exception $e) {
        $logger->error('Error processing media order for node @nid: @error',
          [
            '@nid' => $nid,
            '@error' => $e->getMessage(),
          ]
        );
      }
    }

    // Process each taxonomy term - update media references.
    foreach ($items_by_termid as $termid => $items) {
      try {
        // Get field info from the first item.
        $first_item = reset($items);
        $field_name = $first_item['field_name'] ?? NULL;
        $field_type = $first_item['field_type'] ?? NULL;

        // Verify this is an entity_reference field.
        if ($field_type !== 'entity_reference') {
          $logger->warning('Field @field_name is not an entity_reference field (type: @type), skipping media update',
            [
              '@field_name' => $field_name,
              '@type' => $field_type,
            ]
          );
          continue;
        }

        if (!$field_name) {
          $logger->warning('No field_name provided for termid @termid, skipping media update', ['@termid' => $termid]);
          continue;
        }

        // Load and update each media item.
        foreach ($items as $item) {
          $media_id = $item['media_id'] ?? NULL;
          if (!$media_id) {
            continue;
          }

          try {
            // Load the media entity.
            $media = $entity_type_manager->getStorage('media')->load($media_id);
            if (!$media) {
              $logger->warning('Media @media_id not found', ['@media_id' => $media_id]);
              continue;
            }

            // Verify the field exists on the media.
            if (!$media->hasField($field_name)) {
              $logger->warning('Field @field_name does not exist on media @media_id',
                [
                  '@field_name' => $field_name,
                  '@media_id' => $media_id,
                ]
              );
              continue;
            }

            // Get the current field value.
            $current_value = $media->get($field_name)->getValue();

            // Check if termid is already set correctly.
            $needs_update = TRUE;
            if (!empty($current_value)) {
              // Check if the first reference is already the termid.
              if (isset($current_value[0]['target_id']) && $current_value[0]['target_id'] == $termid) {
                $needs_update = FALSE;
              }
            }

            // Update the field if needed.
            if ($needs_update) {
              $media->set($field_name, [
                [
                  'target_id' => $termid,
                ]
              ]);
              $media->save();

              $logger->info('Updated media @media_id field @field_name to reference term @termid',
                [
                  '@media_id' => $media_id,
                  '@field_name' => $field_name,
                  '@termid' => $termid,
                ]
              );
            }
          }
          catch (\Exception $e) {
            $logger->error('Error updating media @media_id: @error',
              [
                '@media_id' => $media_id,
                '@error' => $e->getMessage(),
              ]
            );
          }
        }
      }
      catch (\Exception $e) {
        $logger->error('Error processing media references for termid @termid: @error',
          [
            '@termid' => $termid,
            '@error' => $e->getMessage(),
          ]
        );
      }
    }
  }

}
