<?php
/**
 * Button Examples for Tip Portal
 * Copy and paste these examples into your page
 */

// Include the button component
include 'tip-button-component.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Button Examples - Tip Portal</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../css/dashboard.css">
    <?php echo $tipButtonStyles; ?>
    <style>
        body {
            padding: 40px;
            background: var(--bg, #f5f6fb);
        }
        .example-section {
            background: white;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .example-section h2 {
            margin-top: 0;
            margin-bottom: 16px;
            color: var(--text-color, #0f172a);
        }
        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }
        .code-block {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color, #c511b3);
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin-top: 12px;
        }
    </style>
</head>
<body>
    <h1 style="margin-bottom: 32px;">Button Examples for Tip Portal</h1>
    
    <!-- Basic Buttons -->
    <div class="example-section">
        <h2>Basic Button Types</h2>
        <div class="button-group">
            <?php echo createTipButton('primary', 'Submit Tip', 'bx-plus'); ?>
            <?php echo createTipButton('secondary', 'Edit', 'bx-edit'); ?>
            <?php echo createTipButton('success', 'Verified', 'bx-check-circle'); ?>
            <?php echo createTipButton('danger', 'Delete', 'bx-trash'); ?>
            <?php echo createTipButton('warning', 'Pending', 'bx-time'); ?>
            <?php echo createTipButton('info', 'Info', 'bx-info-circle'); ?>
            <?php echo createTipButton('outline', 'Export', 'bx-download'); ?>
        </div>
        <div class="code-block">
&lt;?php include 'tip-button-component.php'; ?&gt;<br>
&lt;?php echo createTipButton('primary', 'Submit Tip', 'bx-plus'); ?&gt;<br>
&lt;?php echo createTipButton('success', 'Verified', 'bx-check-circle'); ?&gt;
        </div>
    </div>
    
    <!-- Buttons with Actions -->
    <div class="example-section">
        <h2>Buttons with JavaScript Actions</h2>
        <div class="button-group">
            <?php echo createTipButton('primary', 'Open Modal', 'bx-plus', 'onclick="alert(\'Modal opened!\')"'); ?>
            <?php echo createTipButton('success', 'Refresh', 'bx-refresh', 'onclick="window.location.reload()"'); ?>
            <?php echo createTipButton('info', 'View All', 'bx-list-ul', 'onclick="window.location.href=\'?status=all\'"'); ?>
            <?php echo createTipButton('outline', 'Print', 'bx-printer', 'onclick="window.print()"'); ?>
        </div>
        <div class="code-block">
&lt;?php echo createTipButton('primary', 'Open Modal', 'bx-plus', 'onclick="openCreateModal()"'); ?&gt;
        </div>
    </div>
    
    <!-- Button Sizes -->
    <div class="example-section">
        <h2>Button Sizes</h2>
        <div class="button-group">
            <?php echo createTipButton('primary', 'Small', 'bx-plus', '', 'small'); ?>
            <?php echo createTipButton('primary', 'Default', 'bx-plus'); ?>
            <?php echo createTipButton('primary', 'Large', 'bx-plus', '', 'large'); ?>
        </div>
        <div class="code-block">
&lt;?php echo createTipButton('primary', 'Small', 'bx-plus', '', 'small'); ?&gt;<br>
&lt;?php echo createTipButton('primary', 'Default', 'bx-plus'); ?&gt;<br>
&lt;?php echo createTipButton('primary', 'Large', 'bx-plus', '', 'large'); ?&gt;
        </div>
    </div>
    
    <!-- Icon Only Buttons -->
    <div class="example-section">
        <h2>Icon Only Buttons</h2>
        <div class="button-group">
            <?php echo createTipButton('primary', '', 'bx-plus', 'title="Add"'); ?>
            <?php echo createTipButton('success', '', 'bx-check', 'title="Verify"'); ?>
            <?php echo createTipButton('danger', '', 'bx-trash', 'title="Delete"'); ?>
            <?php echo createTipButton('warning', '', 'bx-time', 'title="Pending"'); ?>
            <?php echo createTipButton('info', '', 'bx-info-circle', 'title="Info"'); ?>
        </div>
        <div class="code-block">
&lt;?php echo createTipButton('primary', '', 'bx-plus', 'title="Add"'); ?&gt;
        </div>
    </div>
    
    <!-- Link Buttons -->
    <div class="example-section">
        <h2>Link Buttons (Styled as Buttons)</h2>
        <div class="button-group">
            <?php echo createTipLinkButton('Tip Portal.php', 'primary', 'Go to Tip Portal', 'bx-info-circle'); ?>
            <?php echo createTipLinkButton('Feedback.php', 'success', 'Go to Feedback', 'bx-message-square-dots'); ?>
            <?php echo createTipLinkButton('admin_dashboard.php', 'info', 'Dashboard', 'bx-dashboard'); ?>
        </div>
        <div class="code-block">
&lt;?php echo createTipLinkButton('Tip Portal.php', 'primary', 'Go to Tip Portal', 'bx-info-circle'); ?&gt;
        </div>
    </div>
    
    <!-- Form Submit Buttons -->
    <div class="example-section">
        <h2>Form Submit Buttons</h2>
        <form method="post" style="display: inline;">
            <div class="button-group">
                <?php echo createTipSubmitButton('primary', 'Submit Tip', 'bx-save'); ?>
                <?php echo createTipSubmitButton('success', 'Save Changes', 'bx-check'); ?>
                <?php echo createTipSubmitButton('danger', 'Delete', 'bx-trash'); ?>
            </div>
        </form>
        <div class="code-block">
&lt;form method="post"&gt;<br>
&nbsp;&nbsp;&lt;?php echo createTipSubmitButton('primary', 'Submit Tip', 'bx-save'); ?&gt;<br>
&lt;/form&gt;
        </div>
    </div>
    
    <!-- Large Create Button -->
    <div class="example-section">
        <h2>Large Create Button</h2>
        <div class="button-group">
            <?php echo createTipLargeButton('Submit Tip', 'bx-plus', 'onclick="alert(\'Create modal!\')"'); ?>
            <?php echo createTipLargeButton('Create New', 'bx-plus-circle'); ?>
        </div>
        <div class="code-block">
&lt;?php echo createTipLargeButton('Submit Tip', 'bx-plus', 'onclick="openCreateModal()"'); ?&gt;
        </div>
    </div>
    
    <!-- Floating Button -->
    <div class="example-section">
        <h2>Floating Action Button</h2>
        <p>This button appears fixed at the bottom right of the page.</p>
        <div class="code-block">
&lt;?php echo createTipFloatingButton('bx-plus', 'onclick="openCreateModal()"', 'Submit Tip'); ?&gt;
        </div>
    </div>
    
    <!-- Common Tip Portal Buttons -->
    <div class="example-section">
        <h2>Common Tip Portal Buttons</h2>
        <div class="button-group">
            <?php echo createTipButton('primary', 'Submit Tip', 'bx-plus', 'onclick="openCreateModal()"'); ?>
            <?php echo createTipButton('success', 'View Verified', 'bx-check-circle', 'onclick="window.location.href=\'?status=verified\'"'); ?>
            <?php echo createTipButton('warning', 'View Pending', 'bx-time', 'onclick="window.location.href=\'?status=pending\'"'); ?>
            <?php echo createTipButton('danger', 'View Urgent', 'bx-error-circle', 'onclick="window.location.href=\'?priority=Urgent\'"'); ?>
            <?php echo createTipButton('info', 'View All', 'bx-list-ul', 'onclick="window.location.href=\'?status=all\'"'); ?>
            <?php echo createTipButton('secondary', 'Refresh', 'bx-refresh', 'onclick="window.location.reload()"'); ?>
            <?php echo createTipButton('outline', 'Print', 'bx-printer', 'onclick="window.print()"'); ?>
            <?php echo createTipButton('outline', 'Export', 'bx-download', 'onclick="exportTips()"'); ?>
        </div>
        <div class="code-block">
// Quick Actions Section<br>
&lt;?php include 'tip-button-component.php'; ?&gt;<br>
&lt;?php echo createTipButton('primary', 'Submit Tip', 'bx-plus', 'onclick="openCreateModal()"'); ?&gt;<br>
&lt;?php echo createTipButton('success', 'View Verified', 'bx-check-circle', 'onclick="window.location.href=\'?status=verified\'"'); ?&gt;
        </div>
    </div>
    
    <!-- All Available Icons -->
    <div class="example-section">
        <h2>Common Boxicons for Tip Portal</h2>
        <p>Use these icon classes with buttons:</p>
        <div class="code-block">
'bx-plus' - Add/Submit<br>
'bx-check-circle' - Verified/Success<br>
'bx-time' - Pending/Time<br>
'bx-error-circle' - Urgent/Error<br>
'bx-trash' - Delete<br>
'bx-edit' - Edit<br>
'bx-info-circle' - Info<br>
'bx-list-ul' - List/All<br>
'bx-refresh' - Refresh<br>
'bx-printer' - Print<br>
'bx-download' - Export/Download<br>
'bx-upload' - Upload<br>
'bx-search' - Search<br>
'bx-map' - Location<br>
'bx-user' - User<br>
'bx-user-x' - Anonymous<br>
'bx-phone' - Contact<br>
'bx-calendar' - Date<br>
'bx-save' - Save<br>
'bx-check' - Check<br>
'bx-x' - Close/Cancel
        </div>
    </div>
    
    <?php echo createTipFloatingButton('bx-plus', 'onclick="alert(\'Floating button clicked!\')"', 'Submit Tip'); ?>
</body>
</html>

