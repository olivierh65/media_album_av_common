# Media Field Representations

## Overview

**Media Field Representations** is a centralized module that provides reusable utilities for building form widgets and managing field representations across media-related modules.

This module enables **`media_drop`** and **`media_album_av`** to remain completely independent while sharing common field representation logic.

## Architecture

### Module Structure

```
media_field_representations (CORE - no dependencies on others)
├── Services/
│   └── FieldWidgetFactory.php        # Factory for creating field widgets
└── Traits/
    └── FieldWidgetBuilderTrait.php   # Trait for building field widgets

media_drop (depends on media_field_representations)
├── Plugin/Action/
│   ├── BaseAlbumAction.php          # Uses FieldWidgetBuilderTrait
│   └── BulkEditMediaAction.php      # Uses FieldWidgetBuilderTrait
└── media_drop.info.yml              # Declares dependency

media_album_av (depends on media_field_representations)
└── modules/
    └── media_attributes_manager/    # Uses FieldWidgetBuilderTrait
        └── media_attributes_manager.info.yml
```

### Key Benefits

✅ **No Interdependencies**: `media_drop` and `media_album_av` are completely independent  
✅ **Centralized Logic**: Single source of truth for field widget building  
✅ **Reusable**: Any module can depend on `media_field_representations`  
✅ **Maintainable**: Changes to field widget logic only in one place  
✅ **Testable**: Services and traits can be unit tested independently  

## Usage

### Using the Trait

If your class already has the required methods (entityTypeManager, t()), simply use the trait:

```php
use Drupal\media_field_representations\Traits\FieldWidgetBuilderTrait;

class MyAction extends ConfigurableActionBase {
  use FieldWidgetBuilderTrait;
  
  protected function buildFormElement() {
    $widget = $this->buildFieldWidget($field_config, $default_value);
    // ... use widget in your form
  }
}
```

### Using the Service

If you need more flexibility, inject the service:

```php
class MyController {
  protected $fieldWidgetFactory;
  
  public function __construct(FieldWidgetFactory $factory) {
    $this->fieldWidgetFactory = $factory;
  }
  
  public function buildForm() {
    $widget = $this->fieldWidgetFactory->buildWidget($field_config, $default_value);
    // ... use widget
  }
}
```

## Supported Field Types

The widget factory automatically handles:

- `string` → textfield
- `string_long` → textarea
- `text` → textarea
- `text_long` → textarea
- `text_with_summary` → textarea
- `integer` → textfield
- `decimal` → textfield
- `float` → textfield
- `boolean` → checkbox
- `list_string` → select
- `list_integer` → select
- `entity_reference` → entity_autocomplete

## Implementation Details

### FieldWidgetBuilderTrait

Provides a single method:

```php
protected function buildFieldWidget($field_config, $default_value = NULL, array $additional_options = [])
```

- Detects field type automatically
- Extracts default values
- Handles both FieldConfig and FieldDefinition objects
- Supports custom options override

### FieldWidgetFactory Service

Provides:

```php
public function buildWidget($field_config, $default_value = NULL, array $options = [])
public function getFieldType($field_config)
public function getFieldLabel($field_config)
```

## Migration Guide

If you were using duplicate `buildFieldWidget()` methods in different classes:

1. Add dependency to `media_field_representations` in your `module.info.yml`
2. Import the trait: `use FieldWidgetBuilderTrait;`
3. Remove your duplicate `buildFieldWidget()` method
4. Call the trait's method via inheritance

## Dependencies

- `drupal:media`
- `drupal:field`
- `drupal:taxonomy`

## See Also

- [media_drop](../media_drop) - Bulk media management actions
- [media_album_av](../media_album_av) - Audio/Video album management
