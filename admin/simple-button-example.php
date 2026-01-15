<?php
/**
 * SIMPLE BUTTON EXAMPLE
 * This shows the easiest way to create a button
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Button Example</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* Copy this CSS to your page */
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
        .btn-primary {
            background: linear-gradient(135deg, #c511b3, #7c3aed);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(197, 17, 179, 0.3);
        }
    </style>
</head>
<body style="padding: 50px; font-family: Arial;">
    <h1>Simple Button Example</h1>
    
    <h2>Step 1: Copy this HTML code</h2>
    <pre style="background: #f5f5f5; padding: 15px; border-radius: 5px;">
&lt;button class="btn btn-primary"&gt;
    &lt;i class='bx bx-plus'&gt;&lt;/i&gt; Submit Feedback
&lt;/button&gt;
    </pre>
    
    <h2>Step 2: See it in action</h2>
    <button class="btn btn-primary">
        <i class='bx bx-plus'></i> Submit Feedback
    </button>
    
    <h2>Step 3: Add JavaScript (Optional)</h2>
    <pre style="background: #f5f5f5; padding: 15px; border-radius: 5px;">
&lt;button class="btn btn-primary" onclick="alert('Hello!')"&gt;
    &lt;i class='bx bx-plus'&gt;&lt;/i&gt; Click Me
&lt;/button&gt;
    </pre>
    
    <button class="btn btn-primary" onclick="alert('Button clicked!')">
        <i class='bx bx-plus'></i> Click Me
    </button>
    
    <h2>That's it! You now know how to create buttons! 🎉</h2>
</body>
</html>

