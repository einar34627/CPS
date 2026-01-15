<?php
/**
 * Simple Button Component
 * Easy-to-use button generator for CPAS
 * 
 * Usage:
 * include 'simple-button.php';
 * echo createButton('primary', 'Click Me', 'bx-plus', 'onclick="alert()"');
 * echo createButton('success', 'Save', 'bx-save');
 */

function createButton($type = 'primary', $text = 'Button', $icon = '', $attributes = '', $size = '') {
    $iconHtml = $icon ? "<i class='bx $icon'></i> " : '';
    $sizeClass = $size ? " btn-$size" : '';
    $iconOnlyClass = ($text === '' || $text === null) ? ' btn-icon-only' : '';
    
    $button = "<button class='btn btn-$type$sizeClass$iconOnlyClass' $attributes>
        $iconHtml$text
    </button>";
    
    return $button;
}

function createLinkButton($href, $type = 'primary', $text = 'Link', $icon = '', $size = '') {
    $iconHtml = $icon ? "<i class='bx $icon'></i> " : '';
    $sizeClass = $size ? " btn-$size" : '';
    
    $button = "<a href='$href' class='btn btn-$type$sizeClass' style='text-decoration: none;'>
        $iconHtml$text
    </a>";
    
    return $button;
}

// Button Styles
$buttonStyles = '
<style>
.btn {
    padding: 8px 16px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: inherit;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(197, 17, 179, 0.3);
}

.btn-secondary {
    background: rgba(255,255,255,0.2);
    color: var(--text-color);
    border: 1px solid rgba(0,0,0,0.1);
}

.btn-secondary:hover {
    background: rgba(255,255,255,0.3);
}

.btn-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #b91c1c;
}

.btn-danger:hover {
    background: rgba(239, 68, 68, 0.25);
}

.btn-success {
    background: rgba(16, 185, 129, 0.15);
    color: #047857;
}

.btn-success:hover {
    background: rgba(16, 185, 129, 0.25);
}

.btn-warning {
    background: rgba(245, 158, 11, 0.15);
    color: #92400e;
}

.btn-warning:hover {
    background: rgba(245, 158, 11, 0.25);
}

.btn-info {
    background: rgba(59, 130, 246, 0.15);
    color: #2563eb;
}

.btn-info:hover {
    background: rgba(59, 130, 246, 0.25);
}

.btn-outline {
    background: transparent;
    border: 2px solid var(--primary-color);
    color: var(--primary-color);
}

.btn-outline:hover {
    background: var(--primary-color);
    color: white;
}

.btn-small {
    padding: 6px 12px;
    font-size: 12px;
}

.btn-large {
    padding: 16px 32px;
    font-size: 18px;
}

.btn-icon-only {
    width: 40px;
    height: 40px;
    padding: 0;
    justify-content: center;
    border-radius: 50%;
}

.btn-disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.create-btn-large {
    padding: 14px 28px;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    box-shadow: 0 4px 15px rgba(197, 17, 179, 0.3);
}

.create-btn-large:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(197, 17, 179, 0.4);
}
</style>';
?>

