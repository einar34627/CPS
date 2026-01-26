<?php
/**
 * Anonymous Feedback and Tip Line Card Component
 * Beautiful card component matching the design
 * 
 * Usage:
 * include 'anonymous-card-component.php';
 * echo createAnonymousCard('Tip Portal.php', 'onclick="openCreateModal()"');
 */

function createAnonymousCard($link = '#', $onclick = '', $title = 'Anonymous Feedback and Tip Line') {
    $clickAction = $onclick ? $onclick : "window.location.href='$link'";
    
    $card = "
    <div class='anonymous-card' onclick=\"$clickAction\">
        <div class='anonymous-card-border'></div>
        <div class='anonymous-card-content'>
            <div class='anonymous-card-icon'>
                <div class='anonymous-icon-circle'>
                    <i class='bx bx-file-blank'></i>
                </div>
            </div>
            <div class='anonymous-card-text'>
                $title
            </div>
        </div>
    </div>";
    
    return $card;
}

// Card Styles
$anonymousCardStyles = '
<style>
.anonymous-card {
    background: linear-gradient(135deg, #fce7f3 0%, #fdf2f8 100%);
    border-left: 6px solid #c511b3;
    border-radius: 16px;
    padding: 24px;
    margin: 16px 0;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(197, 17, 179, 0.1);
    position: relative;
    overflow: hidden;
}

.anonymous-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(197, 17, 179, 0.2);
    border-left-width: 8px;
}

.anonymous-card-border {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    background: linear-gradient(180deg, #c511b3 0%, #a855f7 100%);
    transition: width 0.3s ease;
}

.anonymous-card:hover .anonymous-card-border {
    width: 8px;
}

.anonymous-card-content {
    display: flex;
    align-items: center;
    gap: 20px;
    position: relative;
    z-index: 1;
}

.anonymous-card-icon {
    flex-shrink: 0;
}

.anonymous-icon-circle {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: linear-gradient(135deg, #fce7f3 0%, #fdf2f8 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(197, 17, 179, 0.15);
    transition: all 0.3s ease;
}

.anonymous-card:hover .anonymous-icon-circle {
    transform: scale(1.1);
    box-shadow: 0 6px 16px rgba(197, 17, 179, 0.25);
}

.anonymous-icon-circle i {
    font-size: 32px;
    color: #ec4899;
    transition: all 0.3s ease;
}

.anonymous-card:hover .anonymous-icon-circle i {
    color: #c511b3;
    transform: scale(1.1);
}

.anonymous-card-text {
    flex: 1;
    font-size: 20px;
    font-weight: 600;
    color: #c511b3;
    line-height: 1.4;
    letter-spacing: -0.02em;
    transition: color 0.3s ease;
}

.anonymous-card:hover .anonymous-card-text {
    color: #a855f7;
}

/* Responsive Design */
@media (max-width: 768px) {
    .anonymous-card {
        padding: 20px;
    }
    
    .anonymous-icon-circle {
        width: 56px;
        height: 56px;
    }
    
    .anonymous-icon-circle i {
        font-size: 28px;
    }
    
    .anonymous-card-text {
        font-size: 18px;
    }
    
    .anonymous-card-content {
        gap: 16px;
    }
}

/* Alternative Styles */
.anonymous-card-large {
    padding: 32px;
}

.anonymous-card-large .anonymous-icon-circle {
    width: 80px;
    height: 80px;
}

.anonymous-card-large .anonymous-icon-circle i {
    font-size: 40px;
}

.anonymous-card-large .anonymous-card-text {
    font-size: 24px;
}

.anonymous-card-small {
    padding: 16px;
}

.anonymous-card-small .anonymous-icon-circle {
    width: 48px;
    height: 48px;
}

.anonymous-card-small .anonymous-icon-circle i {
    font-size: 24px;
}

.anonymous-card-small .anonymous-card-text {
    font-size: 16px;
}
</style>';
?>

