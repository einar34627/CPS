<?php
/**
 * Create Feedback Button Component
 * Reusable button component for creating/submitting feedback
 * 
 * Usage:
 * include 'create-feedback-button.php';
 * echo createFeedbackButton('primary', 'Submit Feedback', 'openCreateModal');
 */

function createFeedbackButton($type = 'primary', $text = 'Submit Feedback', $onclick = 'openCreateModal()', $size = '', $icon = 'bx-plus') {
    $sizeClass = $size ? " btn-$size" : '';
    $iconHtml = $icon ? "<i class='bx $icon'></i> " : '';
    
    // Different button styles
    switch($type) {
        case 'large':
            return "<button class='create-btn-large' onclick='$onclick' title='$text'>
                $iconHtml$text
            </button>";
            
        case 'floating':
            return "<button class='floating-create-btn' onclick='$onclick' title='$text'>
                <i class='bx $icon'></i>
            </button>";
            
        case 'icon':
            return "<button class='btn btn-primary btn-icon-only' onclick='$onclick' title='$text'>
                <i class='bx $icon'></i>
            </button>";
            
        case 'success':
            return "<button class='btn btn-success$sizeClass' onclick='$onclick'>
                $iconHtml$text
            </button>";
            
        case 'secondary':
            return "<button class='btn btn-secondary$sizeClass' onclick='$onclick'>
                $iconHtml$text
            </button>";
            
        case 'outline':
            return "<button class='btn btn-outline$sizeClass' onclick='$onclick'>
                $iconHtml$text
            </button>";
            
        case 'primary':
        default:
            return "<button class='btn btn-primary$sizeClass' onclick='$onclick'>
                $iconHtml$text
            </button>";
    }
}

/**
 * Create Feedback Link Button (for navigation)
 */
function createFeedbackLinkButton($type = 'primary', $text = 'Go to Feedback', $href = 'Feedback.php', $icon = 'bx-message-square-dots') {
    $iconHtml = $icon ? "<i class='bx $icon'></i> " : '';
    
    switch($type) {
        case 'large':
            return "<a href='$href' class='create-btn-large' style='text-decoration: none;'>
                $iconHtml$text
            </a>";
            
        case 'card':
            return "<a href='$href' class='feedback-card-link' style='text-decoration: none; display: block;'>
                <div class='icon-box icon-bg-yellow' style='margin-bottom: 12px;'>
                    <i class='bx $icon icon-yellow' style='font-size: 32px;'></i>
                </div>
                <h3 style='margin: 0 0 8px 0; font-size: 18px; font-weight: 600;'>$text</h3>
                <p style='margin: 0; color: var(--text-light); font-size: 14px;'>Submit or manage community feedback</p>
            </a>";
            
        default:
            return "<a href='$href' class='btn btn-$type' style='text-decoration: none;'>
                $iconHtml$text
            </a>";
    }
}

/**
 * Quick access function - most common use case
 */
function getCreateFeedbackButton($style = 'primary') {
    return createFeedbackButton($style, 'Submit Feedback', 'openCreateModal()');
}

// Button Styles (include in your page head)
$createFeedbackButtonStyles = '
<style>
/* Create Feedback Button Styles */
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

.feedback-card-link {
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 24px;
    box-shadow: var(--glass-shadow);
    transition: all 0.3s ease;
    text-align: center;
}

.feedback-card-link:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
</style>';
?>

