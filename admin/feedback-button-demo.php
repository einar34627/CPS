<?php
/**
 * Feedback Button Component Demo
 * Shows all available button types for Feedback page
 */

session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit();
}

// Get current user info
$user_id = $_SESSION['user_id'];
$query = "SELECT first_name, middle_name, last_name, role FROM users WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user) {
    $first_name = htmlspecialchars($user['first_name']);
    $middle_name = htmlspecialchars($user['middle_name']);
    $last_name = htmlspecialchars($user['last_name']);
    $role = htmlspecialchars($user['role']);
    
    $full_name = $first_name;
    if (!empty($middle_name)) {
        $full_name .= " " . $middle_name;
    }
    $full_name .= " " . $last_name;
} else {
    $full_name = "User";
    $role = "ADMIN";
}

$stmt = null;

// Include the button component
include 'feedback-button.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Button Demo - CPAS</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" type="image/png" sizes="32x32" href="../img/cpas-logo.png">
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        .content-wrapper {
            padding: 32px;
        }
        
        .demo-section {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: var(--glass-shadow);
        }
        
        .section-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-color);
        }
        
        .section-description {
            color: var(--text-light);
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .button-group-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 12px;
            width: 100%;
        }
        
        .code-block {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            border-left: 4px solid var(--primary-color);
        }
        
        .code-block pre {
            margin: 0;
            color: var(--text-color);
        }
        
        /* Button Styles */
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
        
        .btn-success {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
        }
        
        .btn-success:hover {
            background: rgba(16, 185, 129, 0.25);
        }
        
        .btn-info {
            background: rgba(59, 130, 246, 0.15);
            color: #2563eb;
        }
        
        .btn-info:hover {
            background: rgba(59, 130, 246, 0.25);
        }
        
        .btn-icon-only {
            width: 40px;
            height: 40px;
            padding: 0;
            justify-content: center;
            border-radius: 50%;
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
    </style>
    <?php echo $cardStyles; ?>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <div class="logo-icon">
                    <img src="../img/cpas-logo.png" alt="CPAS Logo" style="width: 40px; height: 45px;">
                </div>
                <span class="logo-text">Community Policing and Surveillance</span>
            </div>
            
            <div class="menu-section">
                <p class="menu-title">NAVIGATION</p>
                <div class="menu-items">
                    <a href="admin_dashboard.php" class="menu-item">
                        <div class="icon-box icon-bg-red">
                            <i class='bx bxs-dashboard icon-red'></i>
                        </div>
                        <span class="font-medium">Dashboard</span>
                    </a>
                    <a href="Feedback.php" class="menu-item active">
                        <div class="icon-box icon-bg-yellow">
                            <i class='bx bx-message-square-dots icon-yellow'></i>
                        </div>
                        <span class="font-medium">Feedback</span>
                    </a>
                </div>
            </div>
            
            <div class="menu-section">
                <p class="menu-title">GENERAL</p>
                <div class="menu-items">
                    <a href="../includes/logout.php" class="menu-item">
                        <div class="icon-box icon-bg-red">
                            <i class='bx bx-log-out icon-red'></i>
                        </div>
                        <span class="font-medium">Logout</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div class="header-content">
                    <div>
                        <h1 style="font-size: 24px; font-weight: 700;">Feedback Button Component</h1>
                        <p style="color: var(--text-light);">Reusable button component for Feedback page navigation</p>
                    </div>
                    <div class="user-profile">
                        <img src="../img/rei.jfif" alt="User" class="user-avatar">
                        <div class="user-info">
                            <p class="user-name"><?php echo $full_name; ?></p>
                            <p class="user-email"><?php echo $role; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="content-wrapper">
                <!-- Link Button -->
                <div class="demo-section">
                    <h2 class="section-title">Link Button (Menu Item)</h2>
                    <p class="section-description">Navigation link for sidebar menu</p>
                    
                    <div class="button-group">
                        <div class="button-group-title">Menu Link</div>
                        <?php echo getFeedbackButton('link', 'Feedback', 'bx-message-square-dots'); ?>
                    </div>
                    
                    <div class="code-block">
                        <pre>&lt;?php
include 'feedback-button.php';
echo getFeedbackButton('link', 'Feedback', 'bx-message-square-dots');
?&gt;</pre>
                    </div>
                </div>
                
                <!-- Primary Button -->
                <div class="demo-section">
                    <h2 class="section-title">Primary Button</h2>
                    <p class="section-description">Main action button with gradient background</p>
                    
                    <div class="button-group">
                        <div class="button-group-title">Primary Action</div>
                        <?php echo getFeedbackButton('primary', 'Go to Feedback', 'bx-message-square-dots'); ?>
                    </div>
                    
                    <div class="code-block">
                        <pre>&lt;?php
echo getFeedbackButton('primary', 'Go to Feedback', 'bx-message-square-dots');
?&gt;</pre>
                    </div>
                </div>
                
                <!-- Large Button -->
                <div class="demo-section">
                    <h2 class="section-title">Large Button</h2>
                    <p class="section-description">Prominent button for primary actions</p>
                    
                    <div class="button-group">
                        <div class="button-group-title">Large Create Button</div>
                        <?php echo getFeedbackButton('large', 'View Feedback', 'bx-message-square-dots'); ?>
                    </div>
                    
                    <div class="code-block">
                        <pre>&lt;?php
echo getFeedbackButton('large', 'View Feedback', 'bx-message-square-dots');
?&gt;</pre>
                    </div>
                </div>
                
                <!-- Card Button -->
                <div class="demo-section">
                    <h2 class="section-title">Card Button</h2>
                    <p class="section-description">Card-style button with icon and description</p>
                    
                    <div class="button-group">
                        <div class="button-group-title">Card Style</div>
                        <div style="width: 100%; max-width: 300px;">
                            <?php echo getFeedbackButton('card', 'Feedback', 'bx-message-square-dots'); ?>
                        </div>
                    </div>
                    
                    <div class="code-block">
                        <pre>&lt;?php
echo getFeedbackButton('card', 'Feedback', 'bx-message-square-dots');
?&gt;</pre>
                    </div>
                </div>
                
                <!-- Icon Only Button -->
                <div class="demo-section">
                    <h2 class="section-title">Icon Only Button</h2>
                    <p class="section-description">Circular icon-only button for compact UI</p>
                    
                    <div class="button-group">
                        <div class="button-group-title">Icon Buttons</div>
                        <?php echo getFeedbackButton('icon', 'Feedback', 'bx-message-square-dots'); ?>
                    </div>
                    
                    <div class="code-block">
                        <pre>&lt;?php
echo getFeedbackButton('icon', 'Feedback', 'bx-message-square-dots');
?&gt;</pre>
                    </div>
                </div>
                
                <!-- Other Button Styles -->
                <div class="demo-section">
                    <h2 class="section-title">Other Button Styles</h2>
                    <p class="section-description">Additional button style variants</p>
                    
                    <div class="button-group">
                        <div class="button-group-title">Style Variants</div>
                        <?php echo getFeedbackButton('success', 'Feedback', 'bx-message-square-dots'); ?>
                        <?php echo getFeedbackButton('info', 'Feedback', 'bx-message-square-dots'); ?>
                        <?php echo getFeedbackButton('outline', 'Feedback', 'bx-message-square-dots'); ?>
                        <?php echo getFeedbackButton('default', 'Feedback', 'bx-message-square-dots'); ?>
                    </div>
                    
                    <div class="code-block">
                        <pre>&lt;?php
echo getFeedbackButton('success', 'Feedback', 'bx-message-square-dots');
echo getFeedbackButton('info', 'Feedback', 'bx-message-square-dots');
echo getFeedbackButton('outline', 'Feedback', 'bx-message-square-dots');
echo getFeedbackButton('default', 'Feedback', 'bx-message-square-dots');
?&gt;</pre>
                    </div>
                </div>
                
                <!-- Usage Examples -->
                <div class="demo-section">
                    <h2 class="section-title">Usage Examples</h2>
                    <p class="section-description">How to use the Feedback button component in your pages</p>
                    
                    <div class="code-block">
                        <pre>&lt;!-- Example 1: In Dashboard --&gt;
&lt;?php
include 'feedback-button.php';
?&gt;
&lt;div class="dashboard-actions"&gt;
    &lt;?php echo getFeedbackButton('primary', 'View Feedback', 'bx-message-square-dots'); ?&gt;
&lt;/div&gt;

&lt;!-- Example 2: In Sidebar Menu --&gt;
&lt;?php
include 'feedback-button.php';
echo getFeedbackButton('link', 'Feedback', 'bx-message-square-dots');
?&gt;

&lt;!-- Example 3: Card Grid --&gt;
&lt;div class="cards-grid"&gt;
    &lt;?php echo getFeedbackButton('card', 'Feedback', 'bx-message-square-dots'); ?&gt;
&lt;/div&gt;

&lt;!-- Example 4: Custom Text and Icon --&gt;
&lt;?php
echo getFeedbackButton('large', 'Submit Feedback', 'bx-message-square');
echo getFeedbackButton('primary', 'Manage Feedback', 'bx-message-square-edit');
?&gt;</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

