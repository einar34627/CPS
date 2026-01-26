<?php
/**
 * Button Component
 * Reusable button component for CPAS Admin
 * 
 * Usage:
 * include 'button-component.php';
 * echo getButton('primary', 'Click Me', 'bx-plus', 'onclick="doSomething()"');
 */

function getButton($type = 'primary', $text = 'Button', $icon = '', $attributes = '') {
    $iconHtml = $icon ? "<i class='bx $icon'></i> " : '';
    
    $button = "<button class='btn btn-$type' $attributes>
        $iconHtml$text
    </button>";
    
    return $button;
}

// Button Styles (add to your CSS or include in page)
$buttonStyles = '
<style>
.btn {
    padding: 8px 16px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
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

