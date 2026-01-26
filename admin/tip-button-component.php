<?php
/**
 * Tip Portal Button Component
 * Easy-to-use button generator for Tip Portal and other CPAS pages
 * 
 * Usage:
 * include 'tip-button-component.php';
 * echo createTipButton('primary', 'Submit Tip', 'bx-plus', 'onclick="openCreateModal()"');
 * echo createTipButton('success', 'View Verified', 'bx-check-circle', 'onclick="window.location.href=\'?status=verified\'"');
 */

/**
 * Create a button with icon and text
 * 
 * @param string $type Button type: primary, secondary, success, danger, warning, info, outline
 * @param string $text Button text
 * @param string $icon Boxicon class (e.g., 'bx-plus', 'bx-check-circle')
 * @param string $attributes Additional HTML attributes (onclick, id, class, etc.)
 * @param string $size Button size: small, large, or empty for default
 * @return string HTML button code
 */
function createTipButton($type = 'primary', $text = 'Button', $icon = '', $attributes = '', $size = '') {
    $iconHtml = $icon ? "<i class='bx $icon'></i> " : '';
    $sizeClass = $size ? " btn-$size" : '';
    $iconOnlyClass = (empty($text) || $text === null) ? ' btn-icon-only' : '';
    
    $button = "<button class='btn btn-$type$sizeClass$iconOnlyClass' $attributes>
        $iconHtml$text
    </button>";
    
    return $button;
}

/**
 * Create a link button (styled as button but acts as link)
 * 
 * @param string $href Link URL
 * @param string $type Button type
 * @param string $text Button text
 * @param string $icon Boxicon class
 * @param string $size Button size
 * @return string HTML anchor button code
 */
function createTipLinkButton($href, $type = 'primary', $text = 'Link', $icon = '', $size = '') {
    $iconHtml = $icon ? "<i class='bx $icon'></i> " : '';
    $sizeClass = $size ? " btn-$size" : '';
    
    $button = "<a href='$href' class='btn btn-$type$sizeClass' style='text-decoration: none;'>
        $iconHtml$text
    </a>";
    
    return $button;
}

/**
 * Create a submit button for forms
 * 
 * @param string $type Button type
 * @param string $text Button text
 * @param string $icon Boxicon class
 * @param string $size Button size
 * @return string HTML submit button code
 */
function createTipSubmitButton($type = 'primary', $text = 'Submit', $icon = '', $size = '') {
    $iconHtml = $icon ? "<i class='bx $icon'></i> " : '';
    $sizeClass = $size ? " btn-$size" : '';
    
    $button = "<button type='submit' class='btn btn-$type$sizeClass'>
        $iconHtml$text
    </button>";
    
    return $button;
}

/**
 * Create a large create button (special style)
 * 
 * @param string $text Button text
 * @param string $icon Boxicon class
 * @param string $attributes Additional HTML attributes
 * @return string HTML large button code
 */
function createTipLargeButton($text = 'Submit Tip', $icon = 'bx-plus', $attributes = '') {
    $iconHtml = $icon ? "<i class='bx $icon'></i> " : '';
    
    $button = "<button class='create-btn-large' $attributes>
        $iconHtml$text
    </button>";
    
    return $button;
}

/**
 * Create a floating action button
 * 
 * @param string $icon Boxicon class
 * @param string $attributes Additional HTML attributes
 * @param string $title Tooltip text
 * @return string HTML floating button code
 */
function createTipFloatingButton($icon = 'bx-plus', $attributes = '', $title = 'Submit Tip') {
    $button = "<button class='floating-create-btn' title='$title' $attributes>
        <i class='bx $icon'></i>
    </button>";
    
    return $button;
}

function renderTipPortalToolbarButtons() {
    $html = '';
    $html .= createTipButton('primary', 'Create Tip', 'bx-plus', 'onclick="openCreateModal()"', 'small');
    $html .= createTipButton('success', 'Verified', 'bx-check-circle', 'onclick="window.location.href=\'?status=verified\'"', 'small');
    $html .= createTipButton('warning', 'Pending', 'bx-time', 'onclick="window.location.href=\'?status=pending\'"', 'small');
    $html .= createTipButton('danger', 'Urgent', 'bx-error-circle', 'onclick="window.location.href=\'?priority=Urgent\'"', 'small');
    $html .= createTipButton('info', 'All', 'bx-list-ul', 'onclick="window.location.href=\'?status=all\'"', 'small');
    $html .= createTipButton('outline', 'Print', 'bx-printer', 'onclick="window.print()"', 'small');
    $html .= createTipButton('outline', 'Export', 'bx-download', 'onclick="exportTips()"', 'small');
    $html .= createTipButton('secondary', '', 'bx-refresh', 'onclick="window.location.reload()" title="Refresh"');
    return $html;
}

// Button Styles (include this in your page or add to main CSS file)
$tipButtonStyles = '
<style>
/* Base Button Styles */
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

/* Button Types */
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

.btn-success {
    background: rgba(16, 185, 129, 0.15);
    color: #047857;
}

.btn-success:hover {
    background: rgba(16, 185, 129, 0.25);
}

.btn-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #b91c1c;
}

.btn-danger:hover {
    background: rgba(239, 68, 68, 0.25);
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

/* Button Sizes */
.btn-small {
    padding: 6px 12px;
    font-size: 12px;
}

.btn-small i.bx {
    font-size: 14px;
}

.btn-large {
    padding: 16px 32px;
    font-size: 18px;
}

.btn-large i.bx {
    font-size: 20px;
}

.btn-icon-only {
    width: 40px;
    height: 40px;
    padding: 0;
    justify-content: center;
    border-radius: 50%;
}

.btn-icon-only i.bx {
    font-size: 20px;
}

/* Large Create Button */
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

.create-btn-large i {
    font-size: 20px;
}

/* Floating Action Button */
.floating-create-btn {
    position: fixed;
    bottom: 32px;
    right: 32px;
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    border: none;
    cursor: pointer;
    box-shadow: 0 8px 24px rgba(197, 17, 179, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    z-index: 999;
    transition: all 0.3s ease;
}

.floating-create-btn:hover {
    transform: scale(1.1) rotate(90deg);
    box-shadow: 0 12px 32px rgba(197, 17, 179, 0.5);
}

/* Disabled State */
.btn-disabled,
.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

@media (max-width: 768px) {
    .floating-create-btn {
        bottom: 24px;
        right: 24px;
        width: 56px;
        height: 56px;
        font-size: 24px;
    }
}
</style>';
?>

