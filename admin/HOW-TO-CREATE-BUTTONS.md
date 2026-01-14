# How to Create Buttons - Complete Guide

## Method 1: Simple HTML Button (Easiest)

Just copy and paste this code:

```html
<!-- Primary Button -->
<button class="btn btn-primary">
    <i class='bx bx-plus'></i> Submit Feedback
</button>

<!-- Secondary Button -->
<button class="btn btn-secondary">
    <i class='bx bx-edit'></i> Edit
</button>

<!-- Success Button -->
<button class="btn btn-success">
    <i class='bx bx-check-circle'></i> Save
</button>

<!-- Danger Button -->
<button class="btn btn-danger">
    <i class='bx bx-trash'></i> Delete
</button>
```

## Method 2: Using PHP Component

```php
<?php
// Include the button component
include 'simple-button.php';

// Create a button
echo createButton('primary', 'Submit Feedback', 'bx-plus', 'onclick="submit()"');

// Create a link button
echo createLinkButton('Feedback.php', 'primary', 'Go to Feedback', 'bx-message-square-dots');
?>
```

## Method 3: Button with JavaScript Action

```html
<button class="btn btn-primary" onclick="openModal()">
    <i class='bx bx-plus'></i> Open Modal
</button>

<script>
function openModal() {
    alert('Button clicked!');
    // Your code here
}
</script>
```

## Method 4: Button in a Form

```html
<form method="post">
    <input type="hidden" name="action" value="submit_feedback">
    <button type="submit" class="btn btn-primary">
        <i class='bx bx-save'></i> Submit
    </button>
</form>
```

## All Button Types Available

### 1. Primary Button (Main Action)
```html
<button class="btn btn-primary">
    <i class='bx bx-plus'></i> Primary Button
</button>
```

### 2. Secondary Button
```html
<button class="btn btn-secondary">
    <i class='bx bx-edit'></i> Secondary Button
</button>
```

### 3. Success Button (Green)
```html
<button class="btn btn-success">
    <i class='bx bx-check-circle'></i> Success
</button>
```

### 4. Danger Button (Red)
```html
<button class="btn btn-danger">
    <i class='bx bx-trash'></i> Delete
</button>
```

### 5. Warning Button (Orange)
```html
<button class="btn btn-warning">
    <i class='bx bx-error'></i> Warning
</button>
```

### 6. Info Button (Blue)
```html
<button class="btn btn-info">
    <i class='bx bx-info-circle'></i> Info
</button>
```

### 7. Outline Button
```html
<button class="btn btn-outline">
    <i class='bx bx-download'></i> Download
</button>
```

## Button Sizes

### Small Button
```html
<button class="btn btn-primary btn-small">
    <i class='bx bx-plus'></i> Small
</button>
```

### Default Size (No class needed)
```html
<button class="btn btn-primary">
    <i class='bx bx-plus'></i> Default
</button>
```

### Large Button
```html
<button class="btn btn-primary btn-large">
    <i class='bx bx-plus'></i> Large
</button>
```

### Extra Large Button
```html
<button class="create-btn-large">
    <i class='bx bx-plus'></i> Extra Large
</button>
```

## Icon-Only Buttons

```html
<button class="btn btn-primary btn-icon-only" title="Add">
    <i class='bx bx-plus'></i>
</button>
```

## Link Buttons (Navigation)

```html
<a href="Feedback.php" class="btn btn-primary" style="text-decoration: none;">
    <i class='bx bx-message-square-dots'></i> Go to Feedback
</a>
```

## Complete Example: Feedback Page Button

```html
<!-- In your PHP file -->
<button class="create-btn-large" onclick="openCreateModal()">
    <i class='bx bx-plus'></i> Submit Feedback
</button>

<script>
function openCreateModal() {
    // Your modal opening code
    document.getElementById('feedbackModal').classList.add('active');
}
</script>
```

## Step-by-Step: Create Your First Button

1. **Choose button type** (primary, secondary, success, etc.)
2. **Add the HTML:**
   ```html
   <button class="btn btn-primary">
       Click Me
   </button>
   ```
3. **Add an icon (optional):**
   ```html
   <button class="btn btn-primary">
       <i class='bx bx-plus'></i> Click Me
   </button>
   ```
4. **Add JavaScript action (optional):**
   ```html
   <button class="btn btn-primary" onclick="myFunction()">
       <i class='bx bx-plus'></i> Click Me
   </button>
   ```

## Common Button Actions

### Refresh Page
```html
<button class="btn btn-secondary" onclick="window.location.reload()">
    <i class='bx bx-refresh'></i> Refresh
</button>
```

### Print Page
```html
<button class="btn btn-secondary" onclick="window.print()">
    <i class='bx bx-printer'></i> Print
</button>
```

### Navigate to Page
```html
<button class="btn btn-primary" onclick="window.location.href='Feedback.php'">
    <i class='bx bx-message-square-dots'></i> Go to Feedback
</button>
```

### Show Alert
```html
<button class="btn btn-info" onclick="alert('Hello!')">
    <i class='bx bx-bell'></i> Show Alert
</button>
```

## Required CSS

Make sure you have the button CSS styles in your page. You can find them in:
- `Feedback.php` (already included)
- `buttons-ready-to-use.html` (copy the CSS section)

## Quick Reference

| Button Type | Class | Use For |
|------------|-------|---------|
| Primary | `btn btn-primary` | Main actions |
| Secondary | `btn btn-secondary` | Secondary actions |
| Success | `btn btn-success` | Success/Approve |
| Danger | `btn btn-danger` | Delete/Remove |
| Warning | `btn btn-warning` | Warnings |
| Info | `btn btn-info` | Information |
| Outline | `btn btn-outline` | Alternative style |

## Tips

1. **Always include icons** - Makes buttons more recognizable
2. **Use appropriate colors** - Red for delete, green for save, etc.
3. **Add tooltips** - Use `title="Description"` for icon-only buttons
4. **Test your buttons** - Make sure JavaScript functions work
5. **Keep it simple** - Don't use too many button types on one page

## Need Help?

- Check `button-examples.html` for live examples
- Check `buttons-ready-to-use.html` for copy-paste code
- Check `simple-button.php` for PHP component usage

