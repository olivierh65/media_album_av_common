<?php

namespace Drupal\media_album_av_common\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for handling media order updates.
 */
class MediaOrderController extends ControllerBase {

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
        '@count' => count($data['media'] ?? []),
      ]
    );

    // Allow other modules to process this data via hooks.
    \Drupal::moduleHandler()->invokeAll('media_album_av_common_media_order_save', [$data]);

    return new JsonResponse([
      'success' => TRUE,
      'message' => 'Media order saved successfully',
      'data_received' => count($data['media'] ?? []) . ' items processed',
    ]);
  }

}
