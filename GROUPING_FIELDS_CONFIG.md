# Media Album AV - Grouping Fields Configuration

This document describes how to configure and use grouping fields for media album views.

## Overview

The Media Album AV module provides a centralized configuration system for defining which fields can be used for grouping media items in album views.

## Configuration

### Available Fields

The module pre-configures the following fields for grouping:

#### Node Fields (from media_drop node)
- **Title**: Node title (string)
- **Event**: Event taxonomy reference (av_events bundle)
- **Season**: Season taxonomy reference (av_season bundle)
- **Category**: Category taxonomy reference (av_categorie bundle)

#### Media Fields (from Media entity)
- **Media Name**: Media entity name (string)
- **Media Type**: Media bundle type (string)
- **Media Category**: Media category taxonomy reference (av_categorie bundle)

### Customizing Fields

To customize the available grouping fields:

1. Go to **Administration > Configuration > Media > Album Grouping Fields**
   - Path: `/admin/config/media/album-av/grouping-fields`

2. Each field configuration allows you to:
   - Update the display label
   - Update the description
   - For taxonomy references: modify the target bundle

3. Adjust the maximum nesting levels allowed

4. Click **Save** to apply changes

## Using Grouping in Views

To create a view with grouping:

1. Create or edit a Views display
2. Set the **Format** to "Media Album Light Table"
3. Under **Advanced > Other > Grouping**, configure your grouping levels:
   - Level 0 (first grouping): Select a primary field (e.g., Event)
   - Level 1 (second grouping): Select a secondary field (e.g., Category)
   - Continue as needed up to the maximum allowed levels

4. The selected fields must be in the available fields list

## Service Usage

The `AlbumGroupingFieldsService` provides programmatic access to field configurations:

```php
/** @var \Drupal\media_album_av_common\Service\AlbumGroupingFieldsService $service */
$service = \Drupal::service('media_album_av_common.grouping_fields');

// Get all available fields
$all_fields = $service->getAllAvailableFields();

// Get only node fields
$node_fields = $service->getNodeFields();

// Get only media fields
$media_fields = $service->getMediaFields();

// Get a specific field configuration
$field_config = $service->getField('field_media_album_av_event', 'node');

// Get field options for form elements
$options = $service->getFieldOptions();

// Validate a field
$is_valid = $service->isValidField('field_media_album_av_category');

// Get field source (node or media)
$source = $service->getFieldSource('field_media_album_av_category');
```

## Configuration File

The configuration is stored in:
```
config/install/media_album_av_common.grouping_fields.yml
```

This file contains:
- `node_fields`: Available fields from the node entity
- `media_fields`: Available fields from the media entity
- `max_grouping_levels`: Maximum nesting depth (default: 5)
- `default_grouping`: Default grouping configuration

## ViewsStyle Integration

The `MediaAlbumLightTableStyle` plugin automatically displays available grouping fields in the style configuration UI under "Available Grouping Fields" section.

This helps administrators understand which fields they can use when configuring grouping in their Views.

## Adding New Fields

To add a new field for grouping:

1. Edit `/config/install/media_album_av_common.grouping_fields.yml`
2. Add the field under either `node_fields` or `media_fields`:

```yaml
node_fields:
  your_new_field:
    label: "Your Field Label"
    description: "Field description"
    type: "field_type"  # e.g., entity_reference, string
    target_type: null   # For references: entity type (e.g., taxonomy_term)
    icon: "optional"    # For future icon display
```

3. Clear the site cache
4. The field will be available in Views grouping configuration

## Notes

- Grouping fields must be present in the node or media entities
- The display order in Views is determined by the grouping configuration
- Maximum 5 levels of nesting by default (configurable)
- All fields support hierarchical grouping and display
