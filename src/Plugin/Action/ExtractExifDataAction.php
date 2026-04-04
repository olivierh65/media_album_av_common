<?php

namespace Drupal\media_album_av_common\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\media_album_av_common\Service\ExifFieldManager;
use Drupal\media_album_av_common\Traits\ExifFieldDefinitionsTraitMediaAlbum;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extract and apply EXIF data to selected media.
 *
 * @Action(
 *   id = "media_drop_extract_exif",
 *   label = @Translation("Extract EXIF data"),
 *   type = "media",
 *   category = @Translation("Media Drop"),
 *   confirm = FALSE,
 *   configurable = TRUE,
 * )
 */
class ExtractExifDataAction extends BaseAlbumAction {
  use ExifFieldDefinitionsTraitMediaAlbum;

  /**
   * The EXIF field manager service.
   *
   * @var \Drupal\media_album_av_common\Service\ExifFieldManager
   */
  protected $exifFieldManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager,
    \Drupal\media_album_av_common\Service\DirectoryService $taxonomy_service,
    ExifFieldManager $exif_field_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $taxonomy_service);
    $this->exifFieldManager = $exif_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('media_drop.taxonomy_service'),
      $container->get('media_album_av_common.exif_field_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'auto_create_fields' => TRUE,
      'exif_keys' => array_keys(array_flip(static::getExifFieldKeys())),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['exif_config'] = [
      '#type' => 'details',
      '#title' => $this->t('EXIF Extraction Configuration'),
      '#open' => TRUE,
    ];

    $form['exif_config']['auto_create_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-create EXIF fields if they don\'t exist'),
      '#default_value' => $this->configuration['auto_create_fields'],
      '#description' => $this->t('If checked, missing EXIF fields will be created automatically.'),
    ];

    // EXIF field selection
    $exif_keys = array_values(static::getExifFieldKeys());
    $field_labels = static::getExifFieldLabelMap();
    
    // Build options: map each EXIF key to its label
    $options = [];
    foreach ($exif_keys as $key) {
      $options[$key] = $field_labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    $form['exif_config']['exif_keys'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('EXIF data to extract'),
      '#options' => $options,
      '#default_value' => $this->configuration['exif_keys'] ?? $exif_keys,
      '#description' => $this->t('Select which EXIF data to extract and populate.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['auto_create_fields'] = $form_state->getValue(['exif_config', 'auto_create_fields']);
    $exif_keys_values = $form_state->getValue(['exif_config', 'exif_keys']);
    $this->configuration['exif_keys'] = array_values(array_filter($exif_keys_values));
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // Single entity execution - delegate to executeMultiple
    if ($entity) {
      return $this->executeMultiple(['entities' => [$entity]]);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $data) {
    $response = [];
    $entities = $data['entities'] ?? [];
    $auto_create = $this->configuration['auto_create_fields'] ?? TRUE;
    $exif_keys = $this->configuration['exif_keys'] ?? [];
    
    // If no EXIF keys selected, use all available keys by default
    if (empty($exif_keys)) {
      $exif_keys = array_values(static::getExifFieldKeys());
      \Drupal::logger('media_drop')->info('No EXIF keys selected, using all available keys: @keys', 
        ['@keys' => implode(', ', $exif_keys)]
      );
    }

    if (empty($entities)) {
      return [
        'status' => 'warning',
        'response' => [new MessageCommand($this->t('No media provided.'), NULL, ['type' => 'warning'])],
      ];
    }

    // Collect all media types used
    $media_types = [];
    foreach ($entities as $entity) {
      if (!in_array($entity->bundle(), $media_types)) {
        $media_types[] = $entity->bundle();
      }
    }

    // Auto-create fields if needed
    if ($auto_create && !empty($media_types)) {
      foreach ($media_types as $media_type_id) {
        $created = $this->exifFieldManager->createExifFieldsForMediaType($media_type_id, $exif_keys);
        if ($created > 0) {
          \Drupal::messenger()->addStatus(
            $this->t('Created @count EXIF fields for media type @type',
              ['@count' => $created, '@type' => $media_type_id]
            )
          );
        }
      }
    }

    // For now, just confirm the action was executed
    // TODO: Implement actual EXIF extraction using ExifExtractor service
    $response['response'][] = new MessageCommand(
      $this->t('@count media prepared for EXIF data extraction. Extracting keys: @keys (Extraction implementation pending)',
        ['@count' => count($entities), '@keys' => implode(', ', $exif_keys)]
      ),
      NULL,
      ['type' => 'status']
    );
    $response['status'] = 'success';

    return $response;
  }

}
