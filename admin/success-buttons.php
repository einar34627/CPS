<?php
/**
 * Success Buttons Collection
 * All success button examples for Tip Portal
 */
session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success Buttons - Tip Portal</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        body {
            padding: 40px;
            background: var(--bg, #f5f6fb);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .section {
            background: white;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .section h2 {
            margin-top: 0;
            color: #047857;
        }
        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 16px 0;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        .btn-success {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .btn-success:hover {
            background: rgba(16, 185, 129, 0.25);
            color: #065f46;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
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
        .code-block {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 13px;
            margin-top: 12px;
            border-left: 4px solid #10b981;
            overflow-x: auto;
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            z-index: 10000;
            min-width: 300px;
            border-left: 4px solid #10b981;
            transform: translateX(400px);
            opacity: 0;
            transition: all 0.3s ease;
        }
        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }
        .notification-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .notification-content i {
            font-size: 24px;
            color: #10b981;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="color: #047857; margin-bottom: 32px;">Success Buttons Collection</h1>
        
        <!-- Basic Success Buttons -->
        <div class="section">
            <h2>Basic Success Buttons</h2>
            <div class="button-group">
                <button class="btn btn-success" onclick="showSuccess('Action completed!')">
                    <i class='bx bx-check-circle'></i> Success
                </button>
                <button class="btn btn-success" onclick="showSuccess('Verified successfully!')">
                    <i class='bx bx-check'></i> Verified
                </button>
                <button class="btn btn-success" onclick="showSuccess('Approved!')">
                    <i class='bx bx-check-square'></i> Approve
                </button>
                <button class="btn btn-success" onclick="showSuccess('Saved successfully!')">
                    <i class='bx bx-save'></i> Save
                </button>
                <button class="btn btn-success" onclick="showSuccess('Submitted!')">
                    <i class='bx bx-check-double'></i> Submit
                </button>
            </div>
            <div class="code-block">
&lt;button class="btn btn-success" onclick="showSuccess()"&gt;
    &lt;i class='bx bx-check-circle'&gt;&lt;/i&gt; Success
&lt;/button&gt;
            </div>
        </div>
        
        <!-- Tip Portal Success Buttons -->
        <div class="section">
            <h2>Tip Portal Success Buttons</h2>
            <div class="button-group">
                <button class="btn btn-success" onclick="window.location.href='Tip Portal.php?status=verified'">
                    <i class='bx bx-check-circle'></i> View Verified
                </button>
                <button class="btn btn-success" onclick="window.location.href='Tip Portal.php?status=resolved'">
                    <i class='bx bx-check-double'></i> View Resolved
                </button>
                <button class="btn btn-success" onclick="showSuccess('Tip verified successfully!')">
                    <i class='bx bx-check-square'></i> Verify Tip
                </button>
                <button class="btn btn-success" onclick="showSuccess('Tip approved!')">
                    <i class='bx bx-check'></i> Approve Tip
                </button>
                <button class="btn btn-success" onclick="showSuccess('Tip resolved!')">
                    <i class='bx bx-check-double'></i> Resolve Tip
                </button>
            </div>
        </div>
        
        <!-- Button Sizes -->
        <div class="section">
            <h2>Success Button Sizes</h2>
            <div class="button-group">
                <button class="btn btn-success btn-small">
                    <i class='bx bx-check'></i> Small
                </button>
                <button class="btn btn-success">
                    <i class='bx bx-check'></i> Default
                </button>
                <button class="btn btn-success btn-large">
                    <i class='bx bx-check'></i> Large
                </button>
            </div>
        </div>
        
        <!-- Icon Only Success Buttons -->
        <div class="section">
            <h2>Icon Only Success Buttons</h2>
            <div class="button-group">
                <button class="btn btn-success btn-icon-only" title="Success" onclick="showSuccess('Success!')">
                    <i class='bx bx-check'></i>
                </button>
                <button class="btn btn-success btn-icon-only" title="Verified" onclick="showSuccess('Verified!')">
                    <i class='bx bx-check-circle'></i>
                </button>
                <button class="btn btn-success btn-icon-only" title="Approved" onclick="showSuccess('Approved!')">
                    <i class='bx bx-check-square'></i>
                </button>
                <button class="btn btn-success btn-icon-only" title="Saved" onclick="showSuccess('Saved!')">
                    <i class='bx bx-save'></i>
                </button>
            </div>
        </div>
        
        <!-- Action Success Buttons -->
        <div class="section">
            <h2>Action Success Buttons</h2>
            <div class="button-group">
                <button class="btn btn-success" onclick="markAsVerified()">
                    <i class='bx bx-check-circle'></i> Mark as Verified
                </button>
                <button class="btn btn-success" onclick="approveAll()">
                    <i class='bx bx-check-square'></i> Approve All
                </button>
                <button class="btn btn-success" onclick="resolveSelected()">
                    <i class='bx bx-check-double'></i> Resolve Selected
                </button>
                <button class="btn btn-success" onclick="exportSuccess()">
                    <i class='bx bx-download'></i> Export Success
                </button>
            </div>
        </div>
        
        <!-- Complete Code Examples -->
        <div class="section">
            <h2>Complete Code Examples</h2>
            <div class="code-block">
&lt;!-- View Verified Tips --&gt;
&lt;button class="btn btn-success" onclick="window.location.href='?status=verified'"&gt;
    &lt;i class='bx bx-check-circle'&gt;&lt;/i&gt; View Verified
&lt;/button&gt;

&lt;!-- Verify Tip --&gt;
&lt;button class="btn btn-success" onclick="verifyTip(1)"&gt;
    &lt;i class='bx bx-check-square'&gt;&lt;/i&gt; Verify Tip
&lt;/button&gt;

&lt;!-- Approve Selected --&gt;
&lt;button class="btn btn-success" onclick="approveSelected()"&gt;
    &lt;i class='bx bx-check-square'&gt;&lt;/i&gt; Approve Selected
&lt;/button&gt;

&lt;!-- Success Action with Notification --&gt;
&lt;button class="btn btn-success" onclick="showSuccessMessage()"&gt;
    &lt;i class='bx bx-check'&gt;&lt;/i&gt; Success Action
&lt;/button&gt;

&lt;!-- Icon Only Success --&gt;
&lt;button class="btn btn-success btn-icon-only" title="Success" onclick="showSuccess()"&gt;
    &lt;i class='bx bx-check'&gt;&lt;/i&gt;
&lt;/button&gt;
            </div>
        </div>
    </div>
    
    <script>
        function showSuccess(message = 'Success!') {
            showNotification(message, 'success');
        }
        
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class='bx bx-check-circle'></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" style="background:none;border:none;cursor:pointer;margin-left:auto;">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            setTimeout(() => notification.classList.add('show'), 10);
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
        
        function markAsVerified() {
            if (confirm('Mark this tip as verified?')) {
                showSuccess('Tip marked as verified!');
            }
        }
        
        function approveAll() {
            if (confirm('Approve all tips?')) {
                showSuccess('All tips approved!');
            }
        }
        
        function resolveSelected() {
            showSuccess('Selected tips resolved!');
        }
        
        function exportSuccess() {
            showSuccess('Data exported successfully!');
        }
    </script>
</body>
</html>

