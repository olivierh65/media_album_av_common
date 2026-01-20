<?php

namespace Drupal\media_album_av_common\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\media_album_av_common\Service\MediaViewRendererService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for media grouping operations.
 */
class GroupingController extends ControllerBase {

  /**
   * The media view renderer service.
   *
   * @var \Drupal\media_album_av_common\Service\MediaViewRendererService
   */
  protected $mediaViewRenderer;

  /**
   * Constructs a GroupingController object.
   *
   * @param \Drupal\media_album_av_common\Service\MediaViewRendererService $mediaViewRenderer
   *   The media view renderer service.
   */
  public function __construct(MediaViewRendererService $mediaViewRenderer) {
    $this->mediaViewRenderer = $mediaViewRenderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('media_album_av_common.media_view_renderer')
    );
  }

  /**
   * Apply grouping to media view and return updated content.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with rendered HTML.
   */
  public function applyGrouping(Request $request) {
    // Only POST requests allowed.
    if ($request->getMethod() !== 'POST') {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'POST method required',
      ], 405);
    }

    // Get JSON content.
    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    if (!$data) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Invalid JSON data',
      ], 400);
    }

    // Validate required fields.
    if (empty($data['view_id']) || empty($data['display_id'])) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Missing view_id or display_id',
      ], 400);
    }

    try {
      // Build grouping override from criteria.
      $groupingOverride = [];
      if (!empty($data['grouping_criteria']) && is_array($data['grouping_criteria'])) {
        foreach ($data['grouping_criteria'] as $criterion) {
          $groupingOverride[] = [
            'field' => $criterion['field'] ?? '',
            'order' => 'asc',
          ];
        }
      }

      // Render the view with the new grouping criteria.
      $render = $this->mediaViewRenderer->renderEmbeddedMediaView(
        $data['view_id'],
        $data['display_id'],
      // Arguments.
        [],
      // Libraries.
        [],
        $groupingOverride
      );

      // Invoke hook for other modules.
      $this->moduleHandler()->invokeAll('media_album_av_common_grouping_applied', [
        $data['view_id'],
        $data['display_id'],
        $data['grouping_criteria'],
      ]);

      // Extract and render just the content part (not headers/controls).
      $renderer = \Drupal::service('renderer');

      // The render array structure includes groups data - render just that.
      $contentRender = [
        '#theme' => 'media_light_table_content',
        '#groups' => $render['#groups'] ?? [],
        '#view' => $render['#view'] ?? NULL,
      ];

      $html = $renderer->render($contentRender);

      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Grouping applied successfully',
        'html' => $html,
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Error applying grouping: ' . $e->getMessage(),
      ], 500);
    }
  }

}
