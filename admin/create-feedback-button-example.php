<?php
/**
 * Create Feedback Button - Examples and Usage
 * Shows how to use the create feedback button component
 */

session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit();
}

// Include the button component
include 'create-feedback-button.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Feedback Button Examples - CPAS</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" type="image/png" sizes="32x32" href="../img/cpas-logo.png">
    <link rel="stylesheet" href="../css/dashboard.css">
    <?php echo $createFeedbackButtonStyles; ?>
    <style>
        .content-wrapper {
            padding: 32px;
        }
        
        .example-section {
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
        }
        
        .section-description {
            color: var(--text-light);
            margin-bottom: 24px;
        }
        
        .button-showcase {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .code-block {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            padding: 16px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            border-left: 4px solid var(--primary-color);
        }
        
        .code-block pre {
            margin: 0;
            color: var(--text-color);
        }
        
        /* Base button styles */
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
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(197, 17, 179, 0.3);
        }
        
        .btn-success {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: var(--text-color);
            border: 1px solid rgba(0,0,0,0.1);
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
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-content">
            <div class="header">
                <div class="header-content">
                    <div>
                        <h1 style="font-size: 24px; font-weight: 700;">Create Feedback Button Examples</h1>
                        <p style="color: var(--text-light);">Reusable button component for creating feedback</p>
                    </div>
                </div>
            </div>
            
            <div class="content-wrapper">
                <!-- Primary Button -->
                <div class="example-section">
                    <h2 class="section-title">Primary Create Button</h2>
                    <p class="section-description">Main button for creating feedback</p>
                    <div class="button-showcase">
                        <?php echo createFeedbackButton('primary', 'Submit Feedback', 'openCreateModal()'); ?>
                    </div>
                    <div class="code-block">
                        <pre>&lt;?php
include 'create-feedback-button.php';
echo createFeedbackButton('primary', 'Submit Feedback', 'openCreateModal()');
?&gt;</pre>
                    </div>
                </div>
                
                <!-- Large Button -->
                <div class="example-section">
                    <h2 class="section-title">Large Create Button</h2>
                    <p class="section-description">Prominent button for primary actions</p>
                    <div class="button-showcase">
                        <?php echo createFeedbackButton('large', 'Submit Feedback', 'openCreateModal()'); ?>
                    </div>
                    <div class="code-block">
                        <pre>&lt;?php
echo createFeedbackButton('large', 'Submit Feedback', 'openCreateModal()');
?&gt;</pre>
                    </div>
                </div>
                
                <!-- Floating Button -->
                <div class="example-section">
                    <h2 class="section-title">Floating Create Button</h2>
                    <p class="section-description">Fixed position floating button</p>
                    <div class="button-showcase">
                        <?php echo createFeedbackButton('floating', 'Submit Feedback', 'openCreateModal()'); ?>
                    </div>
                    <div class="code-block">
                        <pre>&lt;?php
echo createFeedbackButton('floating', 'Submit Feedback', 'openCreateModal()');
?&gt;</pre>
                    </div>
                </div>
                
                <!-- Icon Only Button -->
                <div class="example-section">
                    <h2 class="section-title">Icon Only Button</h2>
                    <p class="section-description">Circular icon-only button</p>
                    <div class="button-showcase">
                        <?php echo createFeedbackButton('icon', 'Submit Feedback', 'openCreateModal()'); ?>
                    </div>
                    <div class="code-block">
                        <pre>&lt;?php
echo createFeedbackButton('icon', 'Submit Feedback', 'openCreateModal()');
?&gt;</pre>
                    </div>
                </div>
                
                <!-- Other Styles -->
                <div class="example-section">
                    <h2 class="section-title">Other Button Styles</h2>
                    <p class="section-description">Different button style variants</p>
                    <div class="button-showcase">
                        <?php echo createFeedbackButton('success', 'Submit Feedback', 'openCreateModal()'); ?>
                        <?php echo createFeedbackButton('secondary', 'Submit Feedback', 'openCreateModal()'); ?>
                        <?php echo createFeedbackButton('outline', 'Submit Feedback', 'openCreateModal()'); ?>
                        <?php echo createFeedbackButton('primary', 'Submit Feedback', 'openCreateModal()', 'small'); ?>
                    </div>
                    <div class="code-block">
                        <pre>&lt;?php
echo createFeedbackButton('success', 'Submit Feedback', 'openCreateModal()');
echo createFeedbackButton('secondary', 'Submit Feedback', 'openCreateModal()');
echo createFeedbackButton('outline', 'Submit Feedback', 'openCreateModal()');
echo createFeedbackButton('primary', 'Submit Feedback', 'openCreateModal()', 'small');
?&gt;</pre>
                    </div>
                </div>
                
                <!-- Link Button -->
                <div class="example-section">
                    <h2 class="section-title">Navigation Link Button</h2>
                    <p class="section-description">Button that links to Feedback page</p>
                    <div class="button-showcase">
                        <?php echo createFeedbackLinkButton('primary', 'Go to Feedback', 'Feedback.php'); ?>
                        <?php echo createFeedbackLinkButton('large', 'Go to Feedback', 'Feedback.php'); ?>
                    </div>
                    <div class="code-block">
                        <pre>&lt;?php
echo createFeedbackLinkButton('primary', 'Go to Feedback', 'Feedback.php');
echo createFeedbackLinkButton('large', 'Go to Feedback', 'Feedback.php');
?&gt;</pre>
                    </div>
                </div>
                
                <!-- Quick Access -->
                <div class="example-section">
                    <h2 class="section-title">Quick Access Function</h2>
                    <p class="section-description">Simplest way to create a feedback button</p>
                    <div class="button-showcase">
                        <?php echo getCreateFeedbackButton('primary'); ?>
                        <?php echo getCreateFeedbackButton('large'); ?>
                    </div>
                    <div class="code-block">
                        <pre>&lt;?php
// Simplest way to use
echo getCreateFeedbackButton('primary');
echo getCreateFeedbackButton('large');
?&gt;</pre>
                    </div>
                </div>
                
                <!-- Usage Examples -->
                <div class="example-section">
                    <h2 class="section-title">Usage Examples</h2>
                    <p class="section-description">How to use in different scenarios</p>
                    <div class="code-block">
                        <pre>&lt;!-- Example 1: In Dashboard --&gt;
&lt;?php
include 'create-feedback-button.php';
?&gt;
&lt;div class="dashboard-actions"&gt;
    &lt;?php echo getCreateFeedbackButton('primary'); ?&gt;
&lt;/div&gt;

&lt;!-- Example 2: In Header --&gt;
&lt;?php
include 'create-feedback-button.php';
echo createFeedbackButton('primary', 'Submit Feedback', 'openCreateModal()');
?&gt;

&lt;!-- Example 3: Floating Button --&gt;
&lt;?php
include 'create-feedback-button.php';
echo createFeedbackButton('floating', 'Submit Feedback', 'openCreateModal()');
?&gt;

&lt;!-- Example 4: Custom Icon and Text --&gt;
&lt;?php
echo createFeedbackButton('primary', 'Add New Feedback', 'openCreateModal()', '', 'bx-message-square-add');
?&gt;</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Placeholder function for modal (should exist in Feedback.php)
        function openCreateModal() {
            alert('Create Feedback Modal - This function should be defined in your Feedback.php page');
            // In actual usage, this would open the feedback submission modal
        }
    </script>
</body>
</html>

