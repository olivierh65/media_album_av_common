<?php

namespace Drupal\media_album_av_common\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\media_album_av_common\Service\MediaOrderService;

/**
 * Controller for handling media order updates.
 */
class MediaOrderController extends ControllerBase {

  /**
   * The media order service.
   *
   * @var \Drupal\media_album_av_common\Service\MediaOrderService
   */
  protected $mediaOrderService;

  /**
   * Constructor.
   */
  public function __construct(MediaOrderService $media_order_service) {
    $this->mediaOrderService = $media_order_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('media_album_av_common.media_order_service')
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
    // Only accept POST requests.
    if ($request->getMethod() !== 'POST') {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Invalid request method',
      ], 400);
    }

    // Get JSON data.
    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    if (!$data) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Invalid JSON data',
      ], 400);
    }

    // Delegate to service for processing.
    $result = $this->mediaOrderService->saveMediaOrder($data);

    return new JsonResponse($result, $result['success'] ? 200 : 400);
  }

}
