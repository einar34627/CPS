<?php
/**
 * Button Quick Reference
 * Copy and paste these examples into your pages
 */
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Button Quick Reference</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
            background: #f5f5f5;
        }
        .section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        h2 { color: #666; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .code {
            background: #f8f8f8;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #c511b3;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
        }
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
            margin: 5px;
        }
        .btn-primary { background: linear-gradient(135deg, #c511b3, #7c3aed); color: white; }
        .btn-secondary { background: rgba(255,255,255,0.2); color: #333; border: 1px solid #ddd; }
        .btn-success { background: rgba(16, 185, 129, 0.15); color: #047857; }
        .btn-danger { background: rgba(239, 68, 68, 0.15); color: #b91c1c; }
        .btn-warning { background: rgba(245, 158, 11, 0.15); color: #92400e; }
        .btn-info { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
        .btn-outline { background: transparent; border: 2px solid #c511b3; color: #c511b3; }
        .btn:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <h1>🚀 Button Quick Reference Guide</h1>
    
    <div class="section">
        <h2>1. Basic Button (Copy & Paste)</h2>
        <div class="code">
&lt;button class="btn btn-primary"&gt;
    &lt;i class='bx bx-plus'&gt;&lt;/i&gt; Submit Feedback
&lt;/button&gt;
        </div>
        <button class="btn btn-primary">
            <i class='bx bx-plus'></i> Submit Feedback
        </button>
    </div>
    
    <div class="section">
        <h2>2. All Button Types</h2>
        <div class="code">
&lt;button class="btn btn-primary"&gt;Primary&lt;/button&gt;
&lt;button class="btn btn-secondary"&gt;Secondary&lt;/button&gt;
&lt;button class="btn btn-success"&gt;Success&lt;/button&gt;
&lt;button class="btn btn-danger"&gt;Danger&lt;/button&gt;
&lt;button class="btn btn-warning"&gt;Warning&lt;/button&gt;
&lt;button class="btn btn-info"&gt;Info&lt;/button&gt;
&lt;button class="btn btn-outline"&gt;Outline&lt;/button&gt;
        </div>
        <button class="btn btn-primary">Primary</button>
        <button class="btn btn-secondary">Secondary</button>
        <button class="btn btn-success">Success</button>
        <button class="btn btn-danger">Danger</button>
        <button class="btn btn-warning">Warning</button>
        <button class="btn btn-info">Info</button>
        <button class="btn btn-outline">Outline</button>
    </div>
    
    <div class="section">
        <h2>3. Button with JavaScript</h2>
        <div class="code">
&lt;button class="btn btn-primary" onclick="alert('Clicked!')"&gt;
    &lt;i class='bx bx-bell'&gt;&lt;/i&gt; Click Me
&lt;/button&gt;
        </div>
        <button class="btn btn-primary" onclick="alert('Button clicked!')">
            <i class='bx bx-bell'></i> Click Me
        </button>
    </div>
    
    <div class="section">
        <h2>4. Button in Form</h2>
        <div class="code">
&lt;form method="post"&gt;
    &lt;button type="submit" class="btn btn-primary"&gt;
        &lt;i class='bx bx-save'&gt;&lt;/i&gt; Save
    &lt;/button&gt;
&lt;/form&gt;
        </div>
    </div>
    
    <div class="section">
        <h2>5. Link Button</h2>
        <div class="code">
&lt;a href="Feedback.php" class="btn btn-primary" style="text-decoration: none;"&gt;
    &lt;i class='bx bx-message-square-dots'&gt;&lt;/i&gt; Go to Feedback
&lt;/a&gt;
        </div>
        <a href="Feedback.php" class="btn btn-primary" style="text-decoration: none;">
            <i class='bx bx-message-square-dots'></i> Go to Feedback
        </a>
    </div>
    
    <div class="section">
        <h2>6. Large Button</h2>
        <div class="code">
&lt;button class="create-btn-large"&gt;
    &lt;i class='bx bx-plus'&gt;&lt;/i&gt; Submit Feedback
&lt;/button&gt;
        </div>
    </div>
    
    <div class="section">
        <h2>7. Common Actions</h2>
        <div class="code">
&lt;!-- Refresh --&gt;
&lt;button onclick="window.location.reload()" class="btn btn-secondary"&gt;
    &lt;i class='bx bx-refresh'&gt;&lt;/i&gt; Refresh
&lt;/button&gt;

&lt;!-- Print --&gt;
&lt;button onclick="window.print()" class="btn btn-secondary"&gt;
    &lt;i class='bx bx-printer'&gt;&lt;/i&gt; Print
&lt;/button&gt;

&lt;!-- Navigate --&gt;
&lt;button onclick="window.location.href='Feedback.php'" class="btn btn-primary"&gt;
    &lt;i class='bx bx-message-square-dots'&gt;&lt;/i&gt; Feedback
&lt;/button&gt;
        </div>
    </div>
    
    <div class="section">
        <h2>✅ Quick Steps to Create a Button</h2>
        <ol>
            <li><strong>Copy this template:</strong>
                <div class="code">
&lt;button class="btn btn-primary"&gt;
    &lt;i class='bx bx-plus'&gt;&lt;/i&gt; Your Text Here
&lt;/button&gt;
                </div>
            </li>
            <li><strong>Change the class:</strong> Replace `btn-primary` with `btn-success`, `btn-danger`, etc.</li>
            <li><strong>Change the icon:</strong> Replace `bx-plus` with any Boxicons icon</li>
            <li><strong>Add action:</strong> Add `onclick="yourFunction()"` if needed</li>
            <li><strong>Done!</strong> Your button is ready!</li>
        </ol>
    </div>
    
    <div class="section">
        <h2>📚 More Resources</h2>
        <ul>
            <li><strong>button-examples.html</strong> - Live examples you can test</li>
            <li><strong>buttons-ready-to-use.html</strong> - Copy-paste ready code</li>
            <li><strong>simple-button.php</strong> - PHP component for dynamic buttons</li>
            <li><strong>HOW-TO-CREATE-BUTTONS.md</strong> - Complete documentation</li>
        </ul>
    </div>
</body>
</html>

