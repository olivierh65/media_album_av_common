<?php

namespace Drupal\media_album_av_common\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\media_album_av_common\Service\DirectoryService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\media_album_av_common\Traits\FieldWidgetBuilderTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Action\ActionManager;
use Drupal\media_album_av_common\Traits\TaxonomyTrait;
use Drupal\Core\Ajax\SettingsCommand;

/**
 * Form for configuring media album actions.
 */
class ActionConfigForm extends FormBase {
  use FieldWidgetBuilderTrait;
  use TaxonomyTrait;
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The taxonomy service.
   *
   * @var \Drupal\media_album_av_common\Service\DirectoryService
   */
  protected $taxonomyService;

  /**
   * Media entities to add to album.
   *
   * @var array
   */
  protected $mediaEntities = [];

  /**
   * The selected album node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $albumNode;

  /**
   * Selected media from VBO action.
   *
   * @var array
   */
  protected $selectedMedia = [];

  /**
   * Cache for used directories in album.
   *
   * @var array
   */
  protected $usedDirectoriesCache = [];

  /**
   * The action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected $actionPluginManager;

  /**
   * Is media must be moved to another directory.
   *
   * @var bool
   */
  protected $move;

  /**
   * Action ID.
   *
   * @var string
   */

  protected $actionId;
  /**
   * The album group.
   *
   * @var string
   */

  protected $albumGrp;
  /**
   * The prepared data.
   *
   * @var array
   */
  protected $preparedData;

  /**
   * Constructs an ActionConfigForm object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ActionManager $action_plugin_manager, DirectoryService $taxonomy_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->actionPluginManager = $action_plugin_manager;
    $this->taxonomyService = $taxonomy_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.action'),
      $container->get('media_drop.taxonomy_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_album_action_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $action_id = NULL, $album_grp = NULL, array $prepared_data = []) {

    $this->actionId     = $action_id;
    $this->albumGrp     = $album_grp;
    $this->preparedData = $prepared_data;

    $user_id = (string) \Drupal::currentUser()->id();

    // Cache le formulaire uniquement si c'est une requête POST.
    if (\Drupal::request()->isMethod('POST')) {
      $form_state->setCached(TRUE);
    }

    // Vérifier si on est dans un callback AJAX en regardant l'élément trigger.
    // Si l'élément trigger a un callback AJAX défini (#ajax), ne pas rebuilder
    // pour laisser le callback AJAX s'exécuter.
    $trigger = $form_state->getTriggeringElement();
    $is_ajax_callback = $trigger && isset($trigger['#ajax']['callback']);

    // Rebuilder sauf si c'est un callback AJAX en cours.
    if (!$is_ajax_callback) {
      $form_state->setRebuild(TRUE);
    }

    // Récupérer les paramètres additionnels depuis l'état du formulaire.
    $build_info = $form_state->getBuildInfo();

    $user_input = $form_state->getUserInput();

    // Premier chargement : récupérer depuis les args.
    if (!$user_input['action_data_flag']) {
      $user_input['action_id'] = $build_info['args'][0] ?? NULL;
      $user_input['album_grp'] = $build_info['args'][1] ?? NULL;
      $user_input['prepared_data'] = json_encode($build_info['args'][2] ?? []);
      // Juste un flag pour indiquer que les données sont initialisées.
      $user_input['action_data_flag'] = 1;

      $form_state->setUserInput($user_input);
    }
    // Rechargements AJAX : récupérer depuis form_state.
    $action_id = $user_input['action_id'] ?? NULL;
    $album_grp = $user_input['album_grp'] ?? NULL;
    $prepared_data = json_decode($user_input['prepared_data'], TRUE) ?? [];
    $action_data_flag = $user_input['action_data_flag'] ?? NULL;

    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\Core\Action\ConfigurableActionInterface $action */
    $action = $action_manager->createInstance($action_id);

    // Extraire les media_id depuis les données préparées.
    $media_ids = array_map(function ($item) {
      return $item['media_id'] ?? NULL;
    }, $prepared_data['selected_items'] ?? []);

    // Filtrer les valeurs NULL.
    $media_ids = array_filter($media_ids);

    if (!empty($media_ids)) {
      // Load the LATEST revision of each media to get current field values.
      $media_storage = \Drupal::entityTypeManager()->getStorage('media');
      $mediaEntities = [];
      foreach ($media_ids as $media_id) {
        $latest_revision_id = $media_storage->getLatestRevisionId($media_id);
        if ($latest_revision_id) {
          $mediaEntities[$media_id] = $media_storage->loadRevision($latest_revision_id);
        }
      }
      $action->setMediaEntities($mediaEntities);
    }
    else {
      // No media IDs provided - return early with a message.
      $form['#type'] = 'container';
      $form['message'] = [
        '#markup' => '<div class="messages messages--error">' .
        $this->t('No media selected. Please select media before opening this dialog.') .
        '</div>',
      ];
      return $form;
    }

    // Ajouter les éléments de formulaire propres à l'action.
    $form = $action->buildConfigurationForm($form, $form_state);

    // Champs cachés pour persister les données essentielles à travers les reloads AJAX.
    $form['action_id_hidden'] = [
      '#type' => 'hidden',
      '#value' => $action_id,
    ];

    $form['album_grp_hidden'] = [
      '#type' => 'hidden',
      '#value' => $album_grp,
    ];

    $form['prepared_data_hidden'] = [
      '#type' => 'hidden',
      '#value' => json_encode($prepared_data),
    ];

    $form['action_data_flag_hidden'] = [
      '#type' => 'hidden',
      '#value' => 1,
    ];

    // Attach autocomplete libraries to the main form.
    $form['#attached'] = [
      'library' => [
        'core/drupal.autocomplete',
        'core/drupal.form-states',
      ],
    ];

    // Forcer l'attachement des behaviors AJAX dans la modal.
    $form['#attached']['library'][] = 'core/drupal.ajax';
    $form['#attached']['drupalSettings']['behaviors']['ajaxForm'] = TRUE;

    // Wrapper pour les messages.
    $form['messages'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'modal-messages'],
    ];

    // Actions.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Add states to disable submit button until an album is selected.
    // Submit button is enabled only when album_id is not empty.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('OK'),
      '#button_type' => 'primary',
      '#states' => [
        'disabled' => [
          ':input[name="step_1[album_id]"]' => ['value' => ''],
        ],
      ],
      '#attributes' => [
        'class' => ['media-album-action-submit', 'button', 'js-form-submit', 'form-submit'],
        /* 'data-album-grp' => $album_grp,
        'data-unique-key' => 'submit_action_' . $album_grp,
        'data-prepare-function' => 'prepareActionData', */
      ],
      '#ajax' => [
        'callback' => [
          '\Drupal\media_album_av_common\Form\ActionConfigForm',
          'submitFormAjax',
        ],
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Processing...'),
        ],
      ],
    ];

    // Add cancel button to close the modal.
    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#attributes' => [
        'class' => ['dialog-cancel', 'button'],
      ],
      '#ajax' => [
        'callback' => [
          '\Drupal\media_album_av_common\Form\ActionConfigForm',
          'cancelFormAjax',
        ],
        'event' => 'click',
      ],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * Callback Ajax pour le bouton Annuler.
   */
  public static function cancelFormAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // Simplement fermer la modale.
    $response->addCommand(new CloseModalDialogCommand());

    return $response;
  }

  /**
   * Callback Ajax pour le bouton OK/Submit.
   */
  public static function submitFormAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    // Vérifier les erreurs de validation.
    if ($form_state->hasAnyErrors()) {
      // Afficher les erreurs dans la modale.
      $response->addCommand(new ReplaceCommand('#modal-messages', [
        '#type' => 'status_messages',
      ]));
      return $response;
    }

    // Exécuter l'action.
    try {
      $action_id = $form_state->getBuildInfo()['args'][0] ?? NULL;
      $album_grp = $form_state->getBuildInfo()['args'][1] ?? NULL;

      // Get the form object to call instance methods.
      $form_class = new self(
        \Drupal::entityTypeManager(),
        \Drupal::service('plugin.manager.action'),
        \Drupal::service('media_drop.taxonomy_service')
      );

      // Votre logique métier ici.
      $return = $form_class->executeAction($action_id, $form_state->getValues());

      if ($return) {
        foreach ($return['response'] as $command) {
          $response->addCommand($command);
        }
        if ($return['status'] !== 'error') {
          // Données communes.
          $settings_data = [
            'albumGrp' => $album_grp,
            'result'   => [
              'success' => TRUE,
            ],
          ];

          // Selon l'action : moved_ids ou reordered_ids.
          // ⚠️ IMPORTANT: Ajouter les triggers AVANT closeDialog, sinon l'élément n'existe plus dans le DOM!
          if (!empty($return['moved_ids'])) {
            $settings_data['result']['moved_ids'] = $return['moved_ids'];
            $response->addCommand(new SettingsCommand(['mediaAction' => $settings_data], TRUE));
            $response->addCommand(new InvokeCommand(
              '.media-light-table-execute-action[data-album-grp="' . $album_grp . '"]',
              'trigger', ['executeActionResponse']
            ));
          }
          elseif (!empty($return['reordered_ids'])) {
            $settings_data['result']['reordered_ids'] = $return['reordered_ids'];
            $response->addCommand(new SettingsCommand(['mediaSort' => $settings_data], TRUE));
            $response->addCommand(new InvokeCommand(
              '.media-light-table-execute-action[data-album-grp="' . $album_grp . '"]',
              'trigger', ['executeActionResponse']
            ));
          }

          // Fermer la modal EN DERNIER après tous les triggers et settings
          $response->addCommand(new CloseModalDialogCommand());
        }

      }
      else {
        // Afficher l'erreur dans la modale.
        \Drupal::messenger()->addError(t('Action execution failed. Please try again.'));
        $response->addCommand(new ReplaceCommand('#modal-messages', [
          '#type' => 'status_messages',
        ]));
      }

    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError(t('An error occurred: @error', ['@error' => $e->getMessage()]));
      $response->addCommand(new ReplaceCommand('#modal-messages', [
        '#type' => 'status_messages',
      ]));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Votre validation.
    if (empty($form_state->getValue('action_data_flag'))) {
      $form_state->setErrorByName('action_data_flag', $this->t('Action data is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Cette méthode est requise mais ne sera pas utilisée avec Ajax
    // La logique est dans submitFormAjax()
  }

  /**
   * Exécute l'action demandée.
   */
  protected function executeAction($action_id, $values) : array {

    $response = [];
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\Core\Action\ConfigurableActionInterface $action */
    $action = $action_manager->createInstance($action_id);
    // Votre logique métier.
    switch ($action_id) {
      case 'add_to_album':
        // Logique pour ajouter les médias à l'album.
        break;

      case 'media_drop_move_to_album':
        return $this->doMoveToAlbum($action_id, $values, $response, $action_manager);

      break;

      case 'media_drop_sort_media':
        // Logique pour trier les médias dans l'album.
        return $this->doSortMedia($action_id, $values, $response, $action_manager);

      break;

      default:
        $response['status'] = 'error';
        $response['response'][] = new MessageCommand($this->t('Unknown action.'), NULL, ['type' => 'error']);
    }
    return $response;
  }

  /**
   * Exécute l'action de déplacement vers un album.
   */
  private function doMoveToAlbum($action_id, $values, $response, $action_manager) {
    // 1️⃣ Récupération album
    $album_id = $values['step_1']['album_id'] ?? NULL;

    if (!$album_id) {
      $response['response'][] = new MessageCommand(
      $this->t('No album selected. Please select an album.'), NULL,
      ['type' => 'error']
      );
      $response['status'] = 'error';
      return $response;
    }

    $album_node = $this->entityTypeManager
      ->getStorage('node')
      ->load($album_id);

    if (!$album_node) {
      $response['response'][] = new MessageCommand(
      $this->t('Album node not found.'), NULL,
      ['type' => 'error']
      );
      $response['status'] = 'error';
      return $response;
    }

    // 2️⃣ Config module
    $config = \Drupal::config('media_album_av.settings');

    $event_field = $config->get('event_field');

    $album_fields = [
      'event' => NULL,
      'storage_location' => $config->get('prefered_storage_location'),
      'media_directory' => $config->get('prefered_media_directory'),
    ];

    if ($event_field && $album_node->hasField($event_field)) {
      $album_fields['event'] =
          $album_node->get($event_field)->target_id;
    }

    $prefered_directory_field = $this->getTaxonomyReferenceFields($album_node->bundle(), $album_fields['media_directory']);
    if ($prefered_directory_field && $album_node->hasField($prefered_directory_field[0])) {
      $album_fields['prefered_directory'] =
          $album_node->get($prefered_directory_field[0])->target_id;
    }

    // 3️⃣ Récupération médias
    $data = json_decode($values['prepared_data'], TRUE);

    if (empty($data['selected_items'])) {
      $response['response'][] = new MessageCommand(
      $this->t('No data to process.'), NULL,
      ['type' => 'warning']
      );
      $response['status'] = 'warning';
      return $response;
    }

    $entities = [];

    foreach ($data['selected_items'] as $item) {
      if (!empty($item['media_id'])) {
        $media = $this->entityTypeManager
          ->getStorage('media')
          ->load($item['media_id']);

        if ($media) {
          $entities[] = $media;
        }
      }
    }

    if (empty($entities)) {
      $response['response'][] = new MessageCommand(
      $this->t('No valid media found to process.'), NULL,
      ['type' => 'warning']
      );
      $response['status'] = 'warning';
      return $response;
    }

    // 4️⃣ Résolution des termes autocreate AVANT instanciation du plugin.
    $grouped_media_fields = $values['step_2_wrapper']['step_2']['grouped_media_fields'] ?? NULL;

    if (!empty($grouped_media_fields)) {
      $grouped_media_fields = $this->resolveAutocreateTerms($grouped_media_fields);
    }

    // 5️⃣ Instanciation propre du plugin AVEC config
    $action = $action_manager->createInstance($action_id, [
      'album_grp' => $values['album_grp'] ?? NULL,
      'album_id' => $album_id ?? NULL,
      'directory_tid' => $values['step_2_wrapper']['step_2']['directory_tid'] ?? NULL,
      'grouped_media_fields' => $grouped_media_fields ?? NULL,
      'album_fields' => $album_fields,
      'move' => TRUE,
      'event_id' => $album_fields['event'],
      'prefered_storage_location' => $album_fields['storage_location'],
      'prefered_media_directory' => $album_fields['prefered_directory'],
    ]);

    // 5️⃣ Exécution
    $result = $action->executeMultiple([
      'album_id' => $album_id,
      'entities' => $entities,
    ]);

    return $result;
  }

  /**
   *
   */
  private function doSortMedia($action_id, $values, $response, $action_manager) {
    $sort_by    = $values['sort_config']['sort_by'] ?? 'title';
    $sort_order = $values['sort_config']['sort_order'] ?? 'ASC';

    $data = json_decode($values['prepared_data'], TRUE);

    if (empty($data['selected_items'])) {
      return [
        'status'        => 'warning',
        'response'      => [new MessageCommand($this->t('No media selected.'), NULL, ['type' => 'warning'])],
        'reordered_ids' => [],
      ];
    }

    $action = $action_manager->createInstance($action_id, [
      'sort_by'        => $sort_by,
      'sort_order'     => $sort_order,
      'album_grp'      => $values['album_grp'] ?? NULL,
      'selected_media' => $data['selected_items'] ?? [],
      'all_media'      => $data['all_items'] ?? [],
    ]);

    return $action->executeMultiple([]);
  }

  /**
   * Get the bundles of selected media entities.
   *
   * @return array
   *   Array of media bundle names.
   */
  protected function getSelectedMediaBundles() {
    $bundles = [];

    foreach ($this->mediaEntities as $media) {
      $bundle = $media->bundle();
      if (!in_array($bundle, $bundles)) {
        $bundles[] = $bundle;
      }
    }

    return $bundles;
  }

  /**
   * Get node bundles that have media reference fields compatible with media bundles.
   *
   * @param array $media_bundles
   *   Array of media bundle names.
   *
   * @return array
   *   Array of node bundle machine names with their media field names.
   *   Structure: ['bundle_name' => ['field_name1', 'field_name2'], ...]
   */
  protected function getNodeBundlesForMedia(array $media_bundles) {
    $node_bundles = [];

    try {
      // Load all field configurations.
      $fields = FieldConfig::loadMultiple();

      foreach ($fields as $field) {
        // Only check fields on node entities.
        if ($field->getTargetEntityTypeId() !== 'node') {
          continue;
        }

        // Only check entity_reference fields targeting media.
        if (
        $field->getType() !== 'entity_reference' ||
        $field->getSetting('target_type') !== 'media'
        ) {
          continue;
        }

        $handler_settings = $field->getSetting('handler_settings') ?? [];
        $target_bundles = $handler_settings['target_bundles'] ?? [];

        // If no bundles are restricted, all media bundles are compatible.
        if (empty($target_bundles)) {
          $bundle = $field->getTargetBundle();
          if (!isset($node_bundles[$bundle])) {
            $node_bundles[$bundle] = [];
          }
          $node_bundles[$bundle][] = $field->getName();
          continue;
        }

        // Check if any of the selected media bundles match the restricted bundles.
        if (array_intersect($media_bundles, array_keys($target_bundles))) {
          $bundle = $field->getTargetBundle();
          if (!isset($node_bundles[$bundle])) {
            $node_bundles[$bundle] = [];
          }
          $node_bundles[$bundle][] = $field->getName();
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('media_drop')->warning(
      'Error getting node bundles for media: @message',
      [
        '@message' => $e->getMessage(),
      ]
      );
    }

    return $node_bundles;
  }

  /**
   * Get media entities that are incompatible with the selected album.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The album node.
   *
   * @return array
   *   Array of incompatible media entities.
   */
  protected function ___getIncompatibleMedia($node) {
    $incompatible = [];

    if (empty($this->mediaEntities)) {
      return $incompatible;
    }

    // Get all media reference fields on the album node.
    try {
      $query = $this->entityTypeManager->getStorage('field_config')
        ->getQuery()
        ->condition('entity_type', 'node')
        ->condition('bundle', $node->bundle());

      $field_ids = $query->execute();
      $field_configs = [];

      if (!empty($field_ids)) {
        $field_configs = $this->entityTypeManager->getStorage('field_config')
          ->loadMultiple($field_ids);
      }

      $compatible_bundles = [];
      foreach ($field_configs as $field_config) {
        if ($field_config->get('field_type') === 'entity_reference') {
          if ($field_config->getSetting('target_type') === 'media') {
            $target_bundles = $field_config->getSetting('handler_settings')['target_bundles'] ?? [];
            // If no bundles are restricted, all are compatible.
            if (empty($target_bundles)) {
              return [];
            }
            $compatible_bundles = array_merge($compatible_bundles, array_keys($target_bundles));
          }
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('media_drop')
        ->warning('Error getting incompatible media: @message', [
          '@message' => $e->getMessage(),
        ]);
    }

    // Check each media for compatibility.
    foreach ($this->mediaEntities as $media) {
      if (!empty($compatible_bundles) && !in_array($media->bundle(), $compatible_bundles)) {
        $incompatible[] = $media;
      }
    }

    return $incompatible;
  }

  /**
   * Check if a field is an EXIF field.
   *
   * @param string $field_name
   *   The field name to check.
   *
   * @return bool
   *   TRUE if the field is an EXIF field, FALSE otherwise.
   */
  protected function isExifField($field_name) {
    // EXIF fields typically start with 'field_exif_' or are named 'exif_data'.
    return strpos($field_name, 'field_exif_') === 0 || $field_name === 'exif_data';
  }

  /**
   * Check if two field configs are compatible for merging in union.
   *
   * For taxonomy fields, they are compatible if they point to the same vocabulary.
   *
   * @param \Drupal\field\Entity\FieldConfig $field1
   *   The first field config.
   * @param \Drupal\field\Entity\FieldConfig $field2
   *   The second field config.
   *
   * @return bool
   *   TRUE if fields are compatible, FALSE otherwise.
   */
  protected function ___areFieldsCompatible($field1, $field2) {
    // If field types don't match, they're not compatible.
    if ($field1->getType() !== $field2->getType()) {
      return FALSE;
    }

    // For entity_reference fields, check target type and bundles.
    if ($field1->getType() === 'entity_reference') {
      $target_type_1 = $field1->getSetting('target_type');
      $target_type_2 = $field2->getSetting('target_type');

      if ($target_type_1 !== $target_type_2) {
        return FALSE;
      }

      // For taxonomy terms, check if they point to the same vocabularies.
      if ($target_type_1 === 'taxonomy_term') {
        $bundles_1 = $field1->getSetting('handler_settings')['target_bundles'] ?? [];
        $bundles_2 = $field2->getSetting('handler_settings')['target_bundles'] ?? [];

        // If both have no restrictions, they're compatible.
        if (empty($bundles_1) && empty($bundles_2)) {
          return TRUE;
        }

        // If they have the same target bundles, they're compatible.
        return $bundles_1 === $bundles_2;
      }
    }

    // For other field types, consider them compatible by default.
    return TRUE;
  }

  /**
   * Get media field configurations from the media type of items in the album.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The album node.
   *
   * @return array
   *   Array of media field configs with their settings.
   */
  protected function ___getMediaFieldsConfig($node) {
    $media_fields = [];

    try {
      // Get all media from the node's media fields to find the media type.
      $media_bundles = $this->getMediaBundlesInNode($node);

      if (empty($media_bundles)) {
        return $media_fields;
      }

      // Get media fields from the first media type.
      $media_bundle = reset($media_bundles);

      // Load field configs for this media type.
      $query = $this->entityTypeManager->getStorage('field_config')
        ->getQuery()
        ->condition('entity_type', 'media')
        ->condition('bundle', $media_bundle);

      $field_ids = $query->execute();

      if (!empty($field_ids)) {
        $field_configs = $this->entityTypeManager->getStorage('field_config')
          ->loadMultiple($field_ids);

        // Types of fields to exclude (main media content and EXIF fields).
        // exif field name start with field_exif_ is handled in isExifField().
        $excluded_field_types = ['image', 'file', 'video_file', 'audio_file', 'document'];

        foreach ($field_configs as $field_config) {
          $field_type = $field_config->get('field_type');
          $field_name = $field_config->getName();
          $is_base_field = $field_config->getFieldStorageDefinition()->isBaseField();

          // Only include custom fields that are not excluded types or EXIF fields.
          if (!$is_base_field &&
          !in_array($field_type, $excluded_field_types) &&
          !$this->isExifField($field_name)) {
            $media_fields[$field_name] = [
              'config' => $field_config,
              'type' => $field_type,
            ];
          }
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('media_drop')
        ->warning('Error loading media field configuration: @message', [
          '@message' => $e->getMessage(),
        ]);
    }

    return $media_fields;
  }

  /**
   * Get media bundle types present in the node's media fields.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The album node.
   *
   * @return array
   *   Array of unique media bundle names.
   */
  protected function ___getMediaBundlesInNode($node) {
    $bundles = [];

    try {
      // Find all media reference fields on the node.
      $query = $this->entityTypeManager->getStorage('field_config')
        ->getQuery()
        ->condition('entity_type', 'node')
        ->condition('bundle', $node->bundle())
        ->condition('field_type', 'entity_reference');

      $field_ids = $query->execute();

      if (!empty($field_ids)) {
        $field_configs = $this->entityTypeManager->getStorage('field_config')
          ->loadMultiple($field_ids);

        foreach ($field_configs as $field_config) {
          if ($field_config->getSetting('target_type') === 'media') {
            $field_name = $field_config->getName();

            if ($node->hasField($field_name)) {
              // Get all media in this field (load latest revisions).
              $media_storage = $this->entityTypeManager->getStorage('media');
              foreach ($node->get($field_name) as $item) {
                $media_id = $item->target_id;
                if ($media_id) {
                  $latest_revision_id = $media_storage->getLatestRevisionId($media_id);
                  if ($latest_revision_id) {
                    $media = $media_storage->loadRevision($latest_revision_id);
                    if ($media) {
                      $bundle = $media->bundle();
                      if (!in_array($bundle, $bundles)) {
                        $bundles[] = $bundle;
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('media_drop')
        ->warning('Error getting media bundles in node: @message', [
          '@message' => $e->getMessage(),
        ]);
    }

    return $bundles;
  }

  /**
   * Get all media IDs already present in the album node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The album node.
   *
   * @return array
   *   Array of media IDs with their labels, indexed by media ID.
   */
  protected function ___getMediaIdsInAlbum($node) {
    $existing_media = [];

    try {
      // Find all entity_reference fields on the node.
      $query = $this->entityTypeManager->getStorage('field_config')
        ->getQuery()
        ->condition('entity_type', 'node')
        ->condition('bundle', $node->bundle())
        ->condition('field_type', 'entity_reference');

      $field_ids = $query->execute();

      if (!empty($field_ids)) {
        $field_configs = $this->entityTypeManager->getStorage('field_config')
          ->loadMultiple($field_ids);

        foreach ($field_configs as $field_config) {
          $target_type = $field_config->getSetting('target_type');
          $field_name = $field_config->getName();

          // Only process entity_reference fields that point to entity types
          // that could potentially contain media (skip taxonomy_term, etc).
          if (!in_array($target_type, ['media', 'node'])) {
            continue;
          }

          if (!$node->hasField($field_name)) {
            continue;
          }

          // Get all entities referenced by this field.
          $referenced_items = $node->get($field_name);

          if ($referenced_items->isEmpty()) {
            continue;
          }

          foreach ($referenced_items as $item) {
            $referenced_entity_id = $item->target_id;
            if (!$referenced_entity_id) {
              continue;
            }

            // Load the referenced entity.
            $media_item = $this->entityTypeManager->getStorage($target_type)->load($referenced_entity_id);
            if (!$media_item) {
              \Drupal::logger('media_album_av')->warning('Referenced entity ID @id in field @field could not be loaded.', [
                '@id' => $referenced_entity_id,
                '@field' => $field_name,
              ]);
              continue;
            }

            $existing_media[$referenced_entity_id] = $media_item->label();

          }
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('media_drop')
        ->warning('Error getting media IDs in album: @message', [
          '@message' => $e->getMessage(),
        ]);
    }

    return $existing_media;
  }

  /**
   * Build directory selector form element.
   *
   * @return array|null
   *   Form element for directory selection or NULL.
   */

  /**
   * Get all parent IDs for a given term, up to the root.
   *
   * @param int $term_id
   *   The term ID to get parents for.
   * @param array $terms
   *   All available terms indexed by ID.
   *
   * @return array
   *   Array of parent term IDs from immediate parent to root.
   */
  protected function ___getTermAncestors($term_id, array $terms) {
    $ancestors = [];
    $current_id = $term_id;

    while ($current_id && isset($terms[$current_id])) {
      $term = $terms[$current_id];
      $parent = $term->get('parent');
      $parent_id = !empty($parent->target_id) ? $parent->target_id : 0;

      if ($parent_id === 0 || $parent_id === '0') {
        break;
      }

      $ancestors[] = $parent_id;
      $current_id = $parent_id;
    }

    return $ancestors;
  }

  /**
   * Build hierarchical directory options with indentation (without any marking).
   *
   * @param array $terms
   *   Array of taxonomy terms.
   * @param int $parent_id
   *   Parent term ID for recursive building.
   * @param int $depth
   *   Current depth level for indentation.
   *
   * @return array
   *   Hierarchical options array without any ★ marking.
   */
  protected function ___buildHierarchicalDirectoryOptions(array $terms, $parent_id = 0, $depth = 0) {
    $options = [];
    $indent = str_repeat('– ', $depth);

    foreach ($terms as $term) {
      // Check if this term has the current parent_id.
      $parent = $term->get('parent');
      $term_parent_id = !empty($parent->target_id) ? $parent->target_id : 0;

      if ($term_parent_id != $parent_id) {
        continue;
      }

      $term_id = $term->id();
      $term_label = $term->label();

      $options[$term_id] = $indent . $term_label;

      // Recursively add children.
      $child_options = $this->buildHierarchicalDirectoryOptions($terms, $term_id, $depth + 1);
      // Use + instead of array_merge to preserve numeric keys (term IDs).
      $options = $options + $child_options;
    }

    return $options;
  }

  /**
   * Build the directory selector element.
   *
   * @return array
   *   Form element array for directory selection.
   */
  protected function ___buildDirectorySelector() {
    $config = \Drupal::config('media_directories.settings');
    $vocabulary_id = $config->get('directory_taxonomy');

    if (!$vocabulary_id) {
      return NULL;
    }

    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => $vocabulary_id]);

    // Get directories already used in the album and cache them.
    $this->usedDirectoriesCache = $this->getUsedDirectoriesInalbum($this->albumNode);

    \Drupal::logger('media_drop')->notice('DEBUG buildDirectorySelector - usedDirectoriesCache: @dirs', [
      '@dirs' => implode(', ', $this->usedDirectoriesCache),
    ]);

    // Build terms_by_id for ancestor lookup.
    $terms_by_id = [];
    foreach ($terms as $term) {
      $terms_by_id[$term->id()] = $term;
    }

    // Calculate used directories with their complete parent chains.
    // Structure: $used_chains[$used_id] = [0, parent_id, ..., used_id]
    // This preserves the hierarchy from ROOT for each used directory.
    // Directories that contain media.
    $used_direct = $this->usedDirectoriesCache;
    $used_chains = [];

    foreach ($used_direct as $used_id) {
      if ($used_id === 0) {
        // ROOT: just the root ID.
        $used_chains[0] = [0];
      }
      elseif (isset($terms_by_id[$used_id])) {
        // Term exists: build complete chain from ROOT to this term.
        $ancestors = $this->getTermAncestors($used_id, $terms_by_id);
        $used_chains[$used_id] = array_merge([0], $ancestors, [$used_id]);
      }
      // else: term doesn't exist in vocabulary, skip it.
    }

    // Flatten all IDs (direct + ancestors) for quick lookup.
    $used_all = [];
    foreach ($used_chains as $chain) {
      $used_all = array_merge($used_all, $chain);
    }
    $used_all = array_unique($used_all);

    // Build hierarchical options for used directories from their chains.
    // This displays only the chain elements with proper indentation.
    $used_options = [];
    foreach ($used_chains as $used_id => $chain) {
      foreach ($chain as $depth => $chain_tid) {
        if (!isset($used_options[$chain_tid])) {
          // Build label with indentation based on position in chain.
          $indent = str_repeat('– ', $depth);
          $term_label = ($chain_tid === 0) ? 'Root (no directory)' : $terms_by_id[$chain_tid]->label();

          // Determine if this is a direct media location or ancestor.
          if (in_array($chain_tid, $used_direct)) {
            $label = $indent . '★ ' . $term_label;
          }
          else {
            $label = $indent . $term_label;
          }

          $used_options[$chain_tid] = $label;
        }
      }
    }

    // Build hierarchical options for unused directories from full vocabulary.
    $all_options = $this->buildHierarchicalDirectoryOptions($terms, 0, 0);

    $unused_options = [];
    foreach ($all_options as $tid => $label) {
      if (!in_array($tid, $used_all)) {
        $unused_options[$tid] = $label;
      }
    }

    // Build the options array with optgroups.
    $options = [];

    // Always add ROOT (0) first as a standalone option (not in optgroups).
    $root_label = '– Root (no directory)';
    $options[0] = $root_label;

    // If ROOT is in used directories, add it to the used_options group instead.
    if (in_array(0, $used_direct)) {
      $root_label = '– ★ Root (no directory)';
      $used_options[0] = $root_label;
      // Remove from standalone.
      unset($options[0]);
    }
    elseif (in_array(0, $used_all) && count($used_chains) > 0) {
      // ROOT is ancestor but not direct: include in used group only if other things are used.
      // Remove from standalone.
      unset($options[0]);
    }

    if (!empty($used_options)) {
      $options[(string) $this->t('→ Currently used directories (★)')] = $used_options;
    }

    if (!empty($unused_options)) {
      $options[(string) $this->t('→ Other directories')] = $unused_options;
    }

    return [
      '#type' => 'select',
      '#title' => $this->t('Move to directory'),
      '#options' => $options,
      '#default_value' => $this->configuration['directory_tid'] ?? 0,
      '#description' => $this->t('Optionally move the selected media to this directory. Directories marked with ★ are currently used in this album. Indentation shows the directory hierarchy.'),
    ];
  }

  /**
   * Get directories already used by media in the album node.
   *
   * @param \Drupal\node\NodeInterface|null $node
   *   The album node.
   *
   * @return array
   *   Array of directory taxonomy term IDs.
   */
  protected function ___getUsedDirectoriesInalbum($node) {
    $directories = [];

    if (!$node) {
      return $directories;
    }

    // Find all media reference fields.
    try {
      $query = $this->entityTypeManager->getStorage('field_config')
        ->getQuery()
        ->condition('entity_type', 'node')
        ->condition('bundle', $node->bundle());

      $field_ids = $query->execute();
      $field_configs = [];

      if (!empty($field_ids)) {
        $field_configs = $this->entityTypeManager->getStorage('field_config')
          ->loadMultiple($field_ids);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('media_drop')
        ->warning('Error getting used directories: @message', [
          '@message' => $e->getMessage(),
        ]);
      return $directories;
    }

    foreach ($field_configs as $field_config) {
      if ($field_config->get('field_type') === 'entity_reference') {
        if ($field_config->getSetting('target_type') === 'media') {
          $field_name = $field_config->getName();

          if ($node->hasField($field_name)) {
            // Get all media in this field.
            $media_ids = [];
            foreach ($node->get($field_name) as $item) {
              if ($item->target_id) {
                $media_ids[] = $item->target_id;
              }
            }

            if (!empty($media_ids)) {
              // Load the LATEST revision of each media to get current directory values.
              $media_storage = $this->entityTypeManager->getStorage('media');
              $medias = [];
              foreach ($media_ids as $media_id) {
                $latest_revision_id = $media_storage->getLatestRevisionId($media_id);
                if ($latest_revision_id) {
                  $medias[$media_id] = $media_storage->loadRevision($latest_revision_id);
                }
              }

              foreach ($medias as $media) {
                // Get directory from media.
                $directory_id = NULL;

                if ($media->hasField('directory')) {
                  $field_value = $media->get('directory');
                  if ($field_value && !$field_value->isEmpty()) {
                    // Media has an explicit directory assigned.
                    if (isset($field_value->target_id)) {
                      $directory_id = (int) $field_value->target_id;
                    }
                    else {
                      $directory_id = (int) $field_value->value;
                    }
                  }
                  else {
                    // Field exists but is empty = media is in ROOT (0).
                    $directory_id = 0;
                  }
                }

                // Add the directory ID to the list only if we found an explicit value.
                if ($directory_id !== NULL && !in_array($directory_id, $directories)) {
                  $directories[] = $directory_id;
                  \Drupal::logger('media_drop')->debug('Adding directory @did to usedDirectories for media @mid', [
                    '@did' => $directory_id,
                    '@mid' => $media->id(),
                  ]);
                }
              }
            }
          }
        }
      }
    }

    \Drupal::logger('media_drop')->notice('Found used directories in album @nid: @dirs', [
      '@nid' => $node->id(),
      '@dirs' => implode(', ', $directories),
    ]);

    return $directories;
  }

  /**
   * Get union of editable fields from all acceptable media types.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The album node.
   *
   * @return array
   *   Array of field configs keyed by field name (union of all media types).
   */
  protected function ___getalbumEditableFields($node) {
    $editable_fields = [];

    try {
      // Find the first media reference field on the node.
      $query = $this->entityTypeManager->getStorage('field_config')
        ->getQuery()
        ->condition('entity_type', 'node')
        ->condition('bundle', $node->bundle())
        ->condition('field_type', 'entity_reference');

      $field_ids = $query->execute();
      $media_field = NULL;

      if (!empty($field_ids)) {
        $field_configs = $this->entityTypeManager->getStorage('field_config')
          ->loadMultiple($field_ids);

        // Find the first media reference field.
        foreach ($field_configs as $field_config) {
          if ($field_config->getSetting('target_type') === 'media') {
            $media_field = $field_config;
            break;
          }
        }
      }

      if (!$media_field) {
        \Drupal::logger('media_drop')->notice('No media field found on node @nid', ['@nid' => $node->id()]);
        return $editable_fields;
      }

      // Get the media bundles this field accepts.
      $target_bundles = $media_field->getSetting('handler_settings')['target_bundles'] ?? [];

      \Drupal::logger('media_drop')->notice('Media field target_bundles: @bundles', ['@bundles' => implode(', ', array_keys($target_bundles))]);

      // Determine which media bundles to load fields from.
      $media_bundles_to_load = [];

      if (!empty($target_bundles)) {
        // Media field restricts to specific bundles.
        $media_bundles_to_load = array_keys($target_bundles);
      }
      else {
        // If no bundles restricted, use bundles from actual media in node.
        $media_bundles_to_load = $this->getMediaBundlesInNode($node);
        \Drupal::logger('media_drop')->notice('Using bundles from node: @bundles', ['@bundles' => implode(', ', $media_bundles_to_load)]);
      }

      if (empty($media_bundles_to_load)) {
        \Drupal::logger('media_drop')->notice('No media bundles found');
        return $editable_fields;
      }

      // Show message about multiple types.
      if (count($media_bundles_to_load) > 1) {
        \Drupal::messenger()->addStatus(
        $this->t('Media field accepts multiple types: <strong>@types</strong>. Fields will be applied only where they exist.', [
          '@types' => implode(', ', $media_bundles_to_load),
        ])
        );
      }

      \Drupal::logger('media_drop')->notice('Loading fields for all media bundles: @bundles', ['@bundles' => implode(', ', $media_bundles_to_load)]);

      // Types of fields to exclude (main media content and EXIF).
      $excluded_field_types = ['image', 'file', 'video_file', 'audio_file', 'document'];

      // Load fields from ALL acceptable media bundles and create union.
      foreach ($media_bundles_to_load as $media_bundle) {
        $query = $this->entityTypeManager->getStorage('field_config')
          ->getQuery()
          ->condition('entity_type', 'media')
          ->condition('bundle', $media_bundle);

        $field_ids = $query->execute();
        \Drupal::logger('media_drop')->notice('Found @count fields for media bundle @bundle', ['@count' => count($field_ids), '@bundle' => $media_bundle]);

        if (!empty($field_ids)) {
          $field_configs = $this->entityTypeManager->getStorage('field_config')
            ->loadMultiple($field_ids);

          foreach ($field_configs as $field_config) {
            $field_name = $field_config->getName();
            $field_type = $field_config->get('field_type');
            $is_base_field = $field_config->getFieldStorageDefinition()->isBaseField();

            // Only include custom fields that are not excluded types or EXIF fields.
            if (!$is_base_field &&
            !in_array($field_type, $excluded_field_types) &&
            !$this->isExifField($field_name)) {
              // Check if field already exists in union.
              if (!isset($editable_fields[$field_name])) {
                // Add new field.
                $editable_fields[$field_name] = $field_config;
              }
              else {
                // Check if the new field is compatible with the existing one.
                if ($this->areFieldsCompatible($editable_fields[$field_name], $field_config)) {
                  // Keep the first occurrence (already there).
                  \Drupal::logger('media_drop')->notice(
                  'Field @field already in union, compatible with @bundle',
                  ['@field' => $field_name, '@bundle' => $media_bundle]
                  );
                }
                else {
                  // Log incompatibility but don't fail.
                  \Drupal::logger('media_drop')->warning(
                  'Field @field in bundle @bundle is incompatible with previous definition',
                  ['@field' => $field_name, '@bundle' => $media_bundle]
                  );
                }
              }
            }
          }
        }
      }

      \Drupal::logger('media_drop')->notice('Union of editable fields: @fields', ['@fields' => implode(', ', array_keys($editable_fields))]);
    }
    catch (\Exception $e) {
      \Drupal::logger('media_drop')
        ->warning('Error getting album editable fields: @message', [
          '@message' => $e->getMessage(),
        ]);
    }

    return $editable_fields;
  }

  /**
   * Generate a unique designation key for a field.
   *
   * This key groups fields by their type, label, and (for taxonomies) their
   * target vocabulary, so that equivalent fields across media types are merged.
   *
   * @param \Drupal\field\Entity\FieldConfig $field_config
   *   The field config.
   *
   * @return string
   *   A unique designation key.
   */
  protected function ___getFieldDesignation($field_config) {
    $field_type = $field_config->get('field_type');
    $field_label = $field_config->get('label');

    // For taxonomy fields, include the target vocabularies in the designation.
    if ($field_type === 'entity_reference' &&
    $field_config->getSetting('target_type') === 'taxonomy_term') {
      $target_bundles = $field_config->getSetting('handler_settings')['target_bundles'] ?? [];
      $vocab_key = !empty($target_bundles) ?
        implode(',', array_keys($target_bundles)) : 'all';
      return "{$field_type}|{$field_label}|{$vocab_key}";
    }

    return "{$field_type}|{$field_label}";
  }

  /**
   * Résout les valeurs autocreate des champs taxonomie avant exécution.
   *
   * Pour chaque champ taxonomy avec autocreate activé, crée le terme s'il
   * n'existe pas encore et remplace la valeur string par l'ID du terme.
   *
   * @param array $grouped_media_fields
   *   Les champs groupés issus du form_state.
   *
   * @return array
   *   Les champs avec les valeurs string remplacées par des term IDs.
   */
  protected function resolveAutocreateTerms(array $grouped_media_fields): array {
    $config = \Drupal::config('media_album_av.settings');
    $media_types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();

    foreach ($grouped_media_fields as $designation_key => &$field_data) {
      if (!is_array($field_data) || empty($field_data['field_names'])) {
        continue;
      }

      $raw_value = $field_data['value'] ?? NULL;
      if ($raw_value === NULL || $raw_value === '') {
        continue;
      }

      foreach ($field_data['field_names'] as $field_name) {
        // Chercher si ce field_name est un champ catégorie avec autocreate.
        foreach ($media_types as $media_type_id => $media_type) {
          $category_config = $config->get('category_fields.' . $media_type_id);
          if (!$category_config || ($category_config['field_name'] ?? '') !== $field_name) {
            continue;
          }
          if (empty($category_config['autocreate'])) {
            break;
          }

          // Charger la field_config pour obtenir le vocabulary_id.
          $field_config = $this->entityTypeManager
            ->getStorage('field_config')
            ->load('media.' . $media_type_id . '.' . $field_name);

          if (!$field_config) {
            break;
          }

          $handler_settings = $field_config->getSetting('handler_settings') ?? [];
          $target_bundles   = $handler_settings['target_bundles'] ?? [];
          $vocabulary_id    = !empty($target_bundles) ? array_key_first($target_bundles) : NULL;

          if (!$vocabulary_id) {
            break;
          }

          // Résoudre la valeur selon son format.
          $resolved_id = $this->resolveTermValue($raw_value, $vocabulary_id);

          if ($resolved_id) {
            $field_data['value'] = $resolved_id;
            \Drupal::logger('media_drop')->info(
            'Autocreate: terme résolu "@val" → ID @id (vocab: @vocab)',
            [
              '@val' => is_string($raw_value) ? $raw_value : json_encode($raw_value),
              '@id' => $resolved_id,
              '@vocab' => $vocabulary_id,
            ]
            );
          }
          // Un seul media type peut correspondre à ce field_name.
          break;
        }
      }
    }

    return $grouped_media_fields;
  }

  /**
   * Résout une valeur de widget entity_reference vers un term ID entier.
   *
   * Gère les formats produits par le widget autocomplete Drupal :
   *   - Entité non sauvegardée : ['entity' => TermObject] (autocreate natif)
   *   - Format autocomplete   : "Label (123)" ou "123|Label"
   *   - ID numérique pur      : "42" ou 42
   *   - String libre          : "Nouveau terme" → findOrCreateTaxonomyTerm()
   *
   * @param mixed $value
   *   La valeur brute issue du form_state.
   * @param string $vocabulary_id
   *   Le vocabulaire cible.
   *
   * @return int|null
   *   L'ID du terme, ou NULL si non résolu.
   */
  public function resolveTermValue($value, string $vocabulary_id): ?int {

    // Cas 0 : objet Term directement (autocreate déjà sauvegardé par Drupal).
    if ($value instanceof EntityInterface) {
      if ($value->isNew()) {
        $value->save();
      }
      return (int) $value->id();
    }

    // Cas 1 : tableau avec entité non sauvegardée (autocreate Drupal natif).
    if (is_array($value)) {
      $item = reset($value);
      // Sous-cas : l'item lui-même est un objet Term.
      if ($item instanceof EntityInterface) {
        if ($item->isNew()) {
          $item->save();
        }
        return (int) $item->id();
      }
      if (isset($item['entity']) && $item['entity'] instanceof EntityInterface) {
        if ($item['entity']->isNew()) {
          $item['entity']->save();
        }
        return (int) $item['entity']->id();
      }
      if (!empty($item['target_id']) && is_numeric($item['target_id'])) {
        return (int) $item['target_id'];
      }
      return NULL;
    }

    if (!is_string($value) && !is_numeric($value)) {
      return NULL;
    }

    $value = trim((string) $value);

    // Format "Label (123)".
    if (preg_match('/\((\d+)\)\s*$/', $value, $matches)) {
      return (int) $matches[1];
    }
    // Format "123|Label".
    if (preg_match('/^(\d+)\|/', $value, $matches)) {
      return (int) $matches[1];
    }
    // ID numérique pur.
    if (is_numeric($value)) {
      return (int) $value;
    }
    // Label libre → findOrCreate.
    if (!empty($value)) {
      return $this->findOrCreateTaxonomyTerm($value, $vocabulary_id);
    }
    return NULL;
  }

  /**
   * Trouve ou crée un terme de taxonomie par son label.
   *
   * @param string $term_label
   *   Le label du terme.
   * @param string $vocabulary_id
   *   Le vocabulaire cible.
   *
   * @return int|null
   *   L'ID du terme trouvé ou créé.
   */
  protected function ___findOrCreateTaxonomyTerm(string $term_label, string $vocabulary_id): ?int {
    if (empty($term_label) || empty($vocabulary_id)) {
      return NULL;
    }

    try {
      $storage = $this->entityTypeManager->getStorage('taxonomy_term');

      $ids = $storage->getQuery()
        ->condition('name', $term_label)
        ->condition('vid', $vocabulary_id)
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($ids)) {
        return (int) reset($ids);
      }

      $term = $storage->create(['name' => $term_label, 'vid' => $vocabulary_id]);
      $term->save();
      return (int) $term->id();
    }
    catch (\Exception $e) {
      \Drupal::logger('media_drop')->error(
      'findOrCreateTaxonomyTerm "@label" (@vid): @err',
      ['@label' => $term_label, '@vid' => $vocabulary_id, '@err' => $e->getMessage()]
      );
      return NULL;
    }
  }

}
