# Media Album Light Table - VBO Integration

## Overview

The `media-light-table-vbo.js` file provides comprehensive VBO (Views Bulk Operations) checkbox management for the Media Album Light Table view. It synchronizes checkbox states with counters, manages visual feedback, and dispatches custom events for external integration.

## Features

### 1. Checkbox Synchronization (`mediaLightTableVboSync`)
- Automatically attaches listeners to all VBO checkboxes within media items
- Updates the VBO counter whenever a checkbox state changes
- Dispatches custom `media-light-table-vbo-changed` event
- Handles both individual checkboxes and header "select all" checkbox

### 2. Counter Update (`updateVboCounter()`)
- Counts all checked individual item checkboxes
- Updates `.js-views-bulk-actions-status` display with count
- Applies `has-selection` CSS class for styling
- Dispatches `media-light-table-vbo-count-updated` custom event with count details

### 3. Multipage Selector Fix (`mediaLightTableVboSummaryFix`)
- Fixes the VBO multipage selector summary display
- Corrects "0 items selected" when selection is empty
- Uses MutationObserver to detect and fix incorrect summaries
- Prevents infinite loops during updates

### 4. Form Submission Handling (`mediaLightTableVboSubmit`)
- Detects VBO action submissions
- Dispatches `media-light-table-vbo-before-submit` custom event before submission
- Includes checked count and list of selected entity IDs in event detail

### 5. Visual State Updates (`mediaLightTableVboVisualUpdate`)
- Listens to `media-light-table-vbo-changed` event
- Adds/removes `is-selected` CSS class on media items based on checkbox state
- Enables CSS-based visual styling for selected items

## CSS Styling

When a media item is selected (checkbox checked), the following CSS class is applied:
- `.media-album-light-table__item.is-selected`

This class applies:
- Light blue background: `#e8f2ff`
- Blue border: `#4a90e2`
- Blue-tinted box-shadow with 20% opacity

## Custom Events

The module dispatches three custom events for external integration:

### `media-light-table-vbo-changed`
Fired whenever any VBO checkbox state changes.

```javascript
document.addEventListener('media-light-table-vbo-changed', () => {
  console.log('VBO selection changed');
});
```

### `media-light-table-vbo-count-updated`
Fired after counter is updated with selection details.

```javascript
document.addEventListener('media-light-table-vbo-count-updated', (e) => {
  console.log('Items selected:', e.detail.count);
});
```

### `media-light-table-vbo-before-submit`
Fired before VBO form submission with action and selection details.

```javascript
document.addEventListener('media-light-table-vbo-before-submit', (e) => {
  console.log('Action:', e.detail.action);
  console.log('Checked count:', e.detail.checkedCount);
  console.log('Checked IDs:', e.detail.checkedIds);
});
```

## Integration Points

### Required HTML Structure

```html
<!-- Media item with checkbox -->
<div class="media-album-light-table__item" data-entity-id="123">
  <input type="checkbox" class="js-vbo-checkbox" />
  <!-- ... item content ... -->
</div>

<!-- VBO status display -->
<div class="js-views-bulk-actions-status">No items selected</div>

<!-- VBO multipage selector (optional) -->
<details class="vbo-multipage-selector">
  <summary>0 items selected</summary>
  <!-- ... selector content ... -->
</details>
```

### JavaScript Dependencies
- Core Drupal `once()` function
- Core Drupal `Drupal` object
- Standard DOM APIs (no jQuery dependency)

## Usage Example

```javascript
// Listen for selection changes
document.addEventListener('media-light-table-vbo-changed', () => {
  const count = document.querySelectorAll(
    '.media-album-light-table__item .js-vbo-checkbox:checked'
  ).length;
  console.log(`Selected: ${count} items`);
});

// Listen for before submit
document.addEventListener('media-light-table-vbo-before-submit', (e) => {
  if (confirm(`Perform "${e.detail.action}" on ${e.detail.checkedCount} items?`)) {
    // Proceed with submission
  }
});
```

## Comparison with media_drop Implementation

While this implementation is adapted from `media_drop/js/fix_vbo_counter.js`, it includes:
- **Custom event dispatch** for better integration
- **Media item visual selection state** with CSS class
- **Enhanced form submission tracking** with selected entity details
- **Media album light table specific selectors** for compatibility

## Browser Support

- Modern browsers with ES6 support
- MutationObserver API support
- CustomEvent API support
- Works with Drupal 10+

## Troubleshooting

### Counter not updating
- Ensure checkboxes have the `.js-vbo-checkbox` class
- Verify the `.js-views-bulk-actions-status` element exists
- Check browser console for JavaScript errors

### Selection state not visible
- Verify CSS classes are applied (inspect element)
- Check that media-light-table-groups.css is loaded
- Ensure `is-selected` class styling is not overridden

### Multipage selector showing wrong count
- Clear browser cache and rebuild Drupal cache
- Verify `vbo-multipage-selector` element exists
- Check that MutationObserver is supported in your browser
