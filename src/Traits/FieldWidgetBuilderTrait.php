<?php

namespace Drupal\media_album_av_common\Traits;

use Drupal\Core\Url;

/**
 * Trait for building field widgets for forms.
 *
 * Provides centralized logic for building form widgets based on field types.
 * Used across various actions and widgets to maintain consistency.
 */
trait FieldWidgetBuilderTrait {

  /**
   * Build a form widget for a field configuration or definition.
   *
   * Supports both FieldConfig and FieldStorageDefinitionInterface objects.
   *
   * @param object $field_config
   *   The field config or definition object.
   * @param mixed $default_value
   *   The default value for the field.
   * @param array $additional_options
   *   Additional options to override widget configuration.
   *
   * @return array
   *   Form element for the field.
   */
  protected function buildFieldWidget($field_config, $default_value = NULL, array $additional_options = []) {
    $field_type = $this->getFieldType($field_config);
    $field_label = $this->getFieldLabel($field_config);

    $widget = $this->createBaseWidget($field_type, $field_label, $default_value);

    // Apply additional options if provided.
    if (!empty($additional_options)) {
      $widget = array_merge($widget, $additional_options);
    }

    return $widget;
  }

  /**
   * Get the field type from either FieldConfig or FieldDefinition.
   *
   * @param object $field_config
   *   The field config or definition.
   *
   * @return string
   *   The field type.
   */
  protected function getFieldType($field_config) {
    if (method_exists($field_config, 'getType')) {
      return $field_config->getType();
    }
    if (method_exists($field_config, 'get') && is_callable([$field_config, 'get'])) {
      return $field_config->get('field_type');
    }
    return 'string';
  }

  /**
   * Get the field label from either FieldConfig or FieldDefinition.
   *
   * @param object $field_config
   *   The field config or definition.
   *
   * @return string
   *   The field label.
   */
  protected function getFieldLabel($field_config) {
    if (method_exists($field_config, 'getLabel')) {
      return $field_config->getLabel();
    }
    if (method_exists($field_config, 'get') && is_callable([$field_config, 'get'])) {
      return $field_config->get('label');
    }
    return 'Field';
  }

  /**
   * Create base widget structure based on field type.
   *
   * @param string $field_type
   *   The field type.
   * @param string $field_label
   *   The field label.
   * @param mixed $default_value
   *   The default value.
   *
   * @return array
   *   The base widget array.
   */
  protected function createBaseWidget($field_type, $field_label, $default_value = NULL) {
    switch ($field_type) {
      case 'string':
        return [
          '#type' => 'textfield',
          '#title' => $field_label,
          '#default_value' => $default_value ? ($default_value[0]['value'] ?? '') : '',
        ];

      case 'string_long':
        return [
          '#type' => 'textarea',
          '#title' => $field_label,
          '#default_value' => $default_value ? ($default_value[0]['value'] ?? '') : '',
        ];

      case 'text':
      case 'text_long':
      case 'text_with_summary':
        return [
          '#type' => 'textarea',
          '#title' => $field_label,
          '#default_value' => $default_value ? ($default_value[0]['value'] ?? '') : '',
        ];

      case 'integer':
      case 'decimal':
      case 'float':
        return [
          '#type' => 'textfield',
          '#title' => $field_label,
          '#default_value' => $default_value ? ($default_value[0]['value'] ?? '') : '',
        ];

      case 'boolean':
        return [
          '#type' => 'checkbox',
          '#title' => $field_label,
          '#default_value' => $default_value ? ($default_value[0]['value'] ?? FALSE) : FALSE,
        ];

      case 'list_string':
      case 'list_integer':
        $options = $this->getFieldAllowedValues($field_config ?? []);
        return [
          '#type' => 'select',
          '#title' => $field_label,
          '#options' => ['' => $this->t('- None -')] + $options,
          '#default_value' => $default_value ? ($default_value[0]['value'] ?? '') : '',
        ];

      case 'entity_reference':
        return $this->buildEntityReferenceWidget($field_config ?? [], $field_label, $default_value);

      default:
        return [
          '#type' => 'textfield',
          '#title' => $field_label,
          '#default_value' => $default_value ? ($default_value[0]['value'] ?? '') : '',
        ];
    }
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
    $target_type = $this->getSetting($field_config, 'target_type');
    $handler_settings = $this->getSetting($field_config, 'handler_settings', []);
    $target_bundles = $handler_settings['target_bundles'] ?? [];

    // For taxonomy terms, use entity_autocomplete.
    if ($target_type === 'taxonomy_term') {
      return [
        '#type' => 'entity_autocomplete',
        '#target_type' => $target_type,
        '#title' => $field_label,
        '#selection_settings' => [
          'target_bundles' => $target_bundles,
        ],
        '#default_value' => $default_value && isset($default_value[0]['target_id']) ?
          $this->getEntityTypeManager()->getStorage($target_type)->load($default_value[0]['target_id']) : NULL,
      ];
    }

    // For other entity types (nodes, media, etc.).
    return [
      '#type' => 'entity_autocomplete',
      '#target_type' => $target_type,
      '#title' => $field_label,
      '#selection_settings' => [
        'target_bundles' => $target_bundles,
      ],
      '#default_value' => $default_value && isset($default_value[0]['target_id']) ?
        $this->getEntityTypeManager()->getStorage($target_type)->load($default_value[0]['target_id']) : NULL,
    ];
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
    if (method_exists($field_config, 'getSetting')) {
      return $field_config->getSetting('allowed_values') ?? [];
    }
    if (method_exists($field_config, 'get')) {
      return $field_config->get('allowed_values') ?? [];
    }
    return [];
  }

  /**
   * Get entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function getEntityTypeManager() {
    if (method_exists($this, 'entityTypeManager')) {
      return $this->entityTypeManager;
    }
    return \Drupal::entityTypeManager();
  }

  /**
   * Translate a string.
   *
   * @param string $string
   *   The string to translate.
   * @param array $args
   *   Translation arguments.
   * @param array $options
   *   Additional translation options.
   *
   * @return string
   *   The translated string.
   */
  protected function t($string, array $args = [], array $options = []) {
    return t($string, $args, $options);
  }

}
