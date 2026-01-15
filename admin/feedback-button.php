<?php
/**
 * Feedback Button Component
 * Reusable button component for Feedback page
 * 
 * Usage:
 * include 'feedback-button.php';
 * echo getFeedbackButton();
 */

function getFeedbackButton($type = 'link', $text = 'Feedback', $icon = 'bx-message-square-dots') {
    $button = '';
    
    switch($type) {
        case 'link':
            // Navigation link button
            $button = '<a href="Feedback.php" class="menu-item">
                <div class="icon-box icon-bg-yellow">
                    <i class="bx ' . $icon . ' icon-yellow"></i>
                </div>
                <span class="font-medium">' . htmlspecialchars($text) . '</span>
            </a>';
            break;
            
        case 'primary':
            // Primary action button
            $button = '<a href="Feedback.php" class="btn btn-primary" style="text-decoration: none;">
                <i class="bx ' . $icon . '"></i> ' . htmlspecialchars($text) . '
            </a>';
            break;
            
        case 'large':
            // Large create button style
            $button = '<a href="Feedback.php" class="create-btn-large" style="text-decoration: none;">
                <i class="bx ' . $icon . '"></i> ' . htmlspecialchars($text) . '
            </a>';
            break;
            
        case 'card':
            // Card style button
            $button = '<a href="Feedback.php" class="feedback-card" style="text-decoration: none; display: block;">
                <div class="icon-box icon-bg-yellow" style="margin-bottom: 12px;">
                    <i class="bx ' . $icon . ' icon-yellow" style="font-size: 32px;"></i>
                </div>
                <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 600;">' . htmlspecialchars($text) . '</h3>
                <p style="margin: 0; color: var(--text-light); font-size: 14px;">Manage community feedback</p>
            </a>';
            break;
            
        case 'icon':
            // Icon only button
            $button = '<a href="Feedback.php" class="btn btn-primary btn-icon-only" title="' . htmlspecialchars($text) . '">
                <i class="bx ' . $icon . '"></i>
            </a>';
            break;
            
        case 'success':
            // Success style button
            $button = '<a href="Feedback.php" class="btn btn-success" style="text-decoration: none;">
                <i class="bx ' . $icon . '"></i> ' . htmlspecialchars($text) . '
            </a>';
            break;
            
        case 'info':
            // Info style button
            $button = '<a href="Feedback.php" class="btn btn-info" style="text-decoration: none;">
                <i class="bx ' . $icon . '"></i> ' . htmlspecialchars($text) . '
            </a>';
            break;
            
        case 'outline':
            // Outline style button
            $button = '<a href="Feedback.php" class="btn btn-outline" style="text-decoration: none;">
                <i class="bx ' . $icon . '"></i> ' . htmlspecialchars($text) . '
            </a>';
            break;
            
        default:
            // Default button
            $button = '<a href="Feedback.php" class="btn btn-secondary">
                <i class="bx ' . $icon . '"></i> ' . htmlspecialchars($text) . '
            </a>';
    }
    
    return $button;
}

// Inline styles for card button
$cardStyles = '
<style>
.feedback-card {
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 24px;
    box-shadow: var(--glass-shadow);
    transition: all 0.3s ease;
    text-align: center;
}

.feedback-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
</style>';
?>

