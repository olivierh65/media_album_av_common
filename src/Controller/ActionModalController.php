<?php

namespace Drupal\media_album_av_common\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\media_album_av_common\Form\ActionConfigForm;

/**
 * Controller to open a modal window to display multi step form.
 */
class ActionModalController extends ControllerBase {

  /**
   * Opens a modal dialog for action configuration.
   *
   * @param string $action_id
   *   The action ID.
   * @param string $album_grp
   *   The album group.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response containing the modal dialog command.
   */
  public function open(string $action_id, string $album_grp) {
    $request = \Drupal::request();
    $data_json = $request->request->get('prepared_media_data', '');

    // Décoder le JSON en array associatif.
    $prepared_data = json_decode($data_json, TRUE);

    $form = \Drupal::formBuilder()->getForm(
      ActionConfigForm::class,
      $action_id,
      $album_grp,
      $prepared_data
    );

    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand('Action', $form, ['width' => '90%', 'height' => '90%']));
    return $response;
  }

}
