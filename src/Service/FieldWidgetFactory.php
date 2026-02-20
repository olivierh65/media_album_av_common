<?php

namespace Drupal\media_album_av_common\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Factory service for building field widgets.
 *
 * Centralizes the logic for creating form widgets based on field configurations.
 * Provides a reusable service across all modules (media_drop, media_album_av, etc.).
 */
class FieldWidgetFactory {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * FieldWidgetFactory constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, $string_translation = NULL) {
    $this->entityTypeManager = $entity_type_manager;
    if ($string_translation) {
      $this->setStringTranslation($string_translation);
    }
  }

  /**
   * Build a form widget for a field configuration.
   *
   * Automatically detects field type and creates appropriate widget.
   *
   * @param object $field_config
   *   The field config or field definition.
   * @param mixed $default_value
   *   The default value for the widget.
   * @param array $options
   *   Additional widget options (title override, etc.).
   *
   * @return array
   *   The form element array.
   */
  public function buildWidget($field_config, $default_value = NULL, array $options = []) {
    $field_type = $this->getFieldType($field_config);
    $field_label = $options['label'] ?? $this->getFieldLabel($field_config);

    $widget = $this->createBaseWidget($field_type, $field_label, $field_config, $default_value);

    // Merge additional options.
    if (!empty($options)) {
      foreach ($options as $key => $value) {
        if ($key !== 'label') {
          $widget["#$key"] = $value;
        }
      }
    }

    return $widget;
  }

  /**
   * Get field type from config.
   *
   * @param object $field_config
   *   The field config or definition.
   *
   * @return string
   *   The field type.
   */
  public function getFieldType($field_config) {
    if (method_exists($field_config, 'getType')) {
      return $field_config->getType();
    }
    if (method_exists($field_config, 'get')) {
      return $field_config->get('field_type') ?? 'string';
    }
    return 'string';
  }

  /**
   * Get field label.
   *
   * @param object $field_config
   *   The field config or definition.
   *
   * @return string
   *   The field label.
   */
  public function getFieldLabel($field_config) {
    if (method_exists($field_config, 'getLabel')) {
      return $field_config->getLabel();
    }
    if (method_exists($field_config, 'get')) {
      return $field_config->get('label') ?? 'Field';
    }
    return 'Field';
  }

  /**
   * Create the appropriate widget based on field type.
   *
   * @param string $field_type
   *   The field type.
   * @param string $field_label
   *   The field label.
   * @param object $field_config
   *   The field config (for reference field handling).
   * @param mixed $default_value
   *   The default value.
   *
   * @return array
   *   The widget array.
   */
  protected function createBaseWidget($field_type, $field_label, $field_config, $default_value = NULL) {
    $widget = [
      '#title' => $field_label,
      '#default_value' => $this->extractDefaultValue($default_value, $field_type),
    ];

    switch ($field_type) {
      case 'string':
        $widget['#type'] = 'textfield';
        break;

      case 'string_long':
        $widget['#type'] = 'textarea';
        break;

      case 'text':
      case 'text_long':
      case 'text_with_summary':
        $widget['#type'] = 'textarea';
        break;

      case 'integer':
      case 'decimal':
      case 'float':
        $widget['#type'] = 'textfield';
        break;

      case 'boolean':
        $widget['#type'] = 'checkbox';
        break;

      case 'list_string':
      case 'list_integer':
        $widget['#type'] = 'select';
        $widget['#options'] = ['' => $this->t('- None -')] + $this->getFieldAllowedValues($field_config);
        break;

      case 'entity_reference':
        return $this->buildEntityReferenceWidget($field_config, $field_label, $default_value);

      default:
        $widget['#type'] = 'textfield';
    }

    return $widget;
  }

  /**
   * Build entity reference widget.
   *
   * @param object $field_config
   *   The field configuration.
   * @param string $field_label
   *   The field label.
   * @param mixed $default_value
   *   The default value.
   *
   * @return array
   *   The widget array.
   */
  protected function buildEntityReferenceWidget($field_config, $field_label, $default_value = NULL) {
    $target_type = $this->getSetting($field_config, 'target_type', 'node');
    $handler_settings = $this->getSetting($field_config, 'handler_settings', []);
    $target_bundles = $handler_settings['target_bundles'] ?? [];

    $widget = [
      '#type' => 'entity_autocomplete',
      '#target_type' => $target_type,
      '#title' => $field_label,
      '#selection_settings' => [
        'target_bundles' => $target_bundles,
      ],
    ];

    if ($default_value) {
      $entity = $this->entityTypeManager->getStorage($target_type)->load($default_value);
      $widget['#default_value'] = $entity;
    }

    return $widget;
  }

  /**
   * Extract default value based on field type.
   *
   * @param mixed $default_value
   *   The raw default value.
   * @param string $field_type
   *   The field type.
   *
   * @return mixed
   *   The extracted default value.
   */
  protected function extractDefaultValue($default_value, $field_type) {
    if ($default_value === NULL) {
      return $field_type === 'boolean' ? FALSE : '';
    }

    if (is_array($default_value)) {
      if (isset($default_value[0]['value'])) {
        return $default_value[0]['value'];
      }
      if (isset($default_value[0]['target_id'])) {
        return $default_value[0]['target_id'];
      }
      return '';
    }

    return $default_value;
  }

  /**
   * Get allowed values for a list field.
   *
   * @param object $field_config
   *   The field configuration.
   *
   * @return array
   *   Array of allowed values.
   */
  protected function getFieldAllowedValues($field_config) {
    return $this->getSetting($field_config, 'allowed_values', []);
  }

  /**
   * Get a setting with fallback.
   *
   * @param object $field_config
   *   The field config.
   * @param string $setting_name
   *   The setting name.
   * @param mixed $default
   *   Default value.
   *
   * @return mixed
   *   The setting value.
   */
  protected function getSetting($field_config, $setting_name, $default = NULL) {
    if (empty($field_config)) {
      return $default;
    }

    if (method_exists($field_config, 'getSetting')) {
      $value = $field_config->getSetting($setting_name);
      return $value ?? $default;
    }

    if (method_exists($field_config, 'get')) {
      $value = $field_config->get($setting_name);
      return $value ?? $default;
    }

    return $default;
  }

}
