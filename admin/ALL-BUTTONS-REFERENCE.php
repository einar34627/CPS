<?php
/**
 * ALL BUTTONS REFERENCE - Tip Portal
 * Complete list of all available buttons you can use
 */

// Include button component
include 'tip-button-component.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Buttons Reference - Tip Portal</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../css/dashboard.css">
    <?php echo $tipButtonStyles; ?>
    <style>
        body {
            padding: 40px;
            background: var(--bg, #f5f6fb);
        }
        .section {
            background: white;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .section h2 {
            margin-top: 0;
            color: var(--primary-color, #c511b3);
        }
        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 16px 0;
        }
        .code {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            margin-top: 8px;
            border-left: 4px solid var(--primary-color, #c511b3);
        }
    </style>
</head>
<body>
    <h1>Complete Button Reference - Tip Portal</h1>
    
    <!-- Navigation Buttons -->
    <div class="section">
        <h2>Navigation Buttons</h2>
        <div class="button-group">
            <button class="btn btn-primary" onclick="window.location.href='admin_dashboard.php'">
                <i class='bx bx-home'></i> Dashboard
            </button>
            <button class="btn btn-info" onclick="window.location.href='Feedback.php'">
                <i class='bx bx-message-square-dots'></i> Go to Feedback
            </button>
            <button class="btn btn-secondary" onclick="window.location.href='Tip Portal.php'">
                <i class='bx bx-info-circle'></i> Tip Portal
            </button>
        </div>
        <div class="code">
&lt;button class="btn btn-primary" onclick="window.location.href='admin_dashboard.php'"&gt;
    &lt;i class='bx bx-home'&gt;&lt;/i&gt; Dashboard
&lt;/button&gt;
        </div>
    </div>
    
    <!-- Tip Action Buttons -->
    <div class="section">
        <h2>Tip Action Buttons</h2>
        <div class="button-group">
            <button class="btn btn-primary" onclick="alert('Submit Tip')">
                <i class='bx bx-plus'></i> Submit Tip
            </button>
            <button class="btn btn-success" onclick="alert('View Verified')">
                <i class='bx bx-check-circle'></i> View Verified
            </button>
            <button class="btn btn-warning" onclick="alert('View Pending')">
                <i class='bx bx-time'></i> View Pending
            </button>
            <button class="btn btn-danger" onclick="alert('View Urgent')">
                <i class='bx bx-error-circle'></i> View Urgent
            </button>
            <button class="btn btn-info" onclick="alert('View All')">
                <i class='bx bx-list-ul'></i> View All
            </button>
            <button class="btn btn-success" onclick="alert('View Resolved')">
                <i class='bx bx-check-double'></i> View Resolved
            </button>
            <button class="btn btn-warning" onclick="alert('Under Review')">
                <i class='bx bx-search-alt'></i> Under Review
            </button>
            <button class="btn btn-danger" onclick="alert('View Dismissed')">
                <i class='bx bx-x-circle'></i> View Dismissed
            </button>
        </div>
    </div>
    
    <!-- Category Filter Buttons -->
    <div class="section">
        <h2>Category Filter Buttons</h2>
        <div class="button-group">
            <button class="btn btn-outline" onclick="alert('Crime Tips')">
                <i class='bx bx-shield'></i> Crime Tips
            </button>
            <button class="btn btn-outline" onclick="alert('Safety Tips')">
                <i class='bx bx-check-shield'></i> Safety Tips
            </button>
            <button class="btn btn-outline" onclick="alert('Suspicious Activity')">
                <i class='bx bx-error-alt'></i> Suspicious Activity
            </button>
            <button class="btn btn-outline" onclick="alert('Community Alert')">
                <i class='bx bx-bell'></i> Community Alert
            </button>
        </div>
    </div>
    
    <!-- Utility Buttons -->
    <div class="section">
        <h2>Utility Buttons</h2>
        <div class="button-group">
            <button class="btn btn-secondary" onclick="window.location.reload()">
                <i class='bx bx-refresh'></i> Refresh
            </button>
            <button class="btn btn-outline" onclick="window.print()">
                <i class='bx bx-printer'></i> Print
            </button>
            <button class="btn btn-outline" onclick="alert('Export')">
                <i class='bx bx-download'></i> Export
            </button>
            <button class="btn btn-primary" onclick="alert('Clear Filters')">
                <i class='bx bx-filter-alt'></i> Clear Filters
            </button>
            <button class="btn btn-secondary" onclick="alert('Show Statistics')">
                <i class='bx bx-bar-chart'></i> Show Statistics
            </button>
        </div>
    </div>
    
    <!-- Button Sizes -->
    <div class="section">
        <h2>Button Sizes</h2>
        <div class="button-group">
            <button class="btn btn-primary btn-small">
                <i class='bx bx-plus'></i> Small
            </button>
            <button class="btn btn-primary">
                <i class='bx bx-plus'></i> Default
            </button>
            <button class="btn btn-primary btn-large">
                <i class='bx bx-plus'></i> Large
            </button>
            <button class="create-btn-large">
                <i class='bx bx-plus'></i> Create Button Large
            </button>
        </div>
    </div>
    
    <!-- Icon Only Buttons -->
    <div class="section">
        <h2>Icon Only Buttons</h2>
        <div class="button-group">
            <button class="btn btn-primary btn-icon-only" title="Add">
                <i class='bx bx-plus'></i>
            </button>
            <button class="btn btn-success btn-icon-only" title="Check">
                <i class='bx bx-check'></i>
            </button>
            <button class="btn btn-danger btn-icon-only" title="Delete">
                <i class='bx bx-trash'></i>
            </button>
            <button class="btn btn-warning btn-icon-only" title="Warning">
                <i class='bx bx-error'></i>
            </button>
            <button class="btn btn-info btn-icon-only" title="Info">
                <i class='bx bx-info-circle'></i>
            </button>
        </div>
    </div>
    
    <!-- All Button Types -->
    <div class="section">
        <h2>All Button Types</h2>
        <div class="button-group">
            <button class="btn btn-primary">Primary</button>
            <button class="btn btn-secondary">Secondary</button>
            <button class="btn btn-success">Success</button>
            <button class="btn btn-danger">Danger</button>
            <button class="btn btn-warning">Warning</button>
            <button class="btn btn-info">Info</button>
            <button class="btn btn-outline">Outline</button>
        </div>
    </div>
    
    <!-- Complete Button List for Tip Portal -->
    <div class="section">
        <h2>Complete Button List - Copy & Paste</h2>
        <div class="code">
&lt;!-- Submit Tip --&gt;
&lt;button class="btn btn-primary" onclick="openCreateModal()"&gt;
    &lt;i class='bx bx-plus'&gt;&lt;/i&gt; Submit Tip
&lt;/button&gt;

&lt;!-- View Verified --&gt;
&lt;button class="btn btn-success" onclick="window.location.href='?status=verified'"&gt;
    &lt;i class='bx bx-check-circle'&gt;&lt;/i&gt; View Verified
&lt;/button&gt;

&lt;!-- View Pending --&gt;
&lt;button class="btn btn-warning" onclick="window.location.href='?status=pending'"&gt;
    &lt;i class='bx bx-time'&gt;&lt;/i&gt; View Pending
&lt;/button&gt;

&lt;!-- View Urgent --&gt;
&lt;button class="btn btn-danger" onclick="window.location.href='?priority=Urgent'"&gt;
    &lt;i class='bx bx-error-circle'&gt;&lt;/i&gt; View Urgent
&lt;/button&gt;

&lt;!-- View All --&gt;
&lt;button class="btn btn-info" onclick="window.location.href='?status=all'"&gt;
    &lt;i class='bx bx-list-ul'&gt;&lt;/i&gt; View All
&lt;/button&gt;

&lt;!-- Go to Feedback --&gt;
&lt;button class="btn btn-primary" onclick="window.location.href='Feedback.php'"&gt;
    &lt;i class='bx bx-message-square-dots'&gt;&lt;/i&gt; Go to Feedback
&lt;/button&gt;

&lt;!-- Dashboard --&gt;
&lt;button class="btn btn-info" onclick="window.location.href='admin_dashboard.php'"&gt;
    &lt;i class='bx bx-home'&gt;&lt;/i&gt; Dashboard
&lt;/button&gt;

&lt;!-- Refresh --&gt;
&lt;button class="btn btn-secondary" onclick="window.location.reload()"&gt;
    &lt;i class='bx bx-refresh'&gt;&lt;/i&gt; Refresh
&lt;/button&gt;

&lt;!-- Print --&gt;
&lt;button class="btn btn-outline" onclick="window.print()"&gt;
    &lt;i class='bx bx-printer'&gt;&lt;/i&gt; Print
&lt;/button&gt;

&lt;!-- Export --&gt;
&lt;button class="btn btn-outline" onclick="exportTips()"&gt;
    &lt;i class='bx bx-download'&gt;&lt;/i&gt; Export
&lt;/button&gt;

&lt;!-- Clear Filters --&gt;
&lt;button class="btn btn-primary" onclick="clearFilters()"&gt;
    &lt;i class='bx bx-filter-alt'&gt;&lt;/i&gt; Clear Filters
&lt;/button&gt;

&lt;!-- Show Statistics --&gt;
&lt;button class="btn btn-secondary" onclick="showStats()"&gt;
    &lt;i class='bx bx-bar-chart'&gt;&lt;/i&gt; Show Statistics
&lt;/button&gt;
        </div>
    </div>
    
    <script>
        function clearFilters() {
            window.location.href = 'Tip Portal.php';
        }
        
        function showStats() {
            alert('Statistics feature');
        }
        
        function exportTips() {
            alert('Export feature');
        }
    </script>
</body>
</html>

