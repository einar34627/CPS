<?php
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Button Showcase - CPAS</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" type="image/png" sizes="32x32" href="../img/cpas-logo.png">
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        .content-wrapper {
            padding: 32px;
        }
        
        .showcase-section {
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
        
        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #b91c1c;
        }
        
        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.25);
        }
        
        .btn-success {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
        }
        
        .btn-success:hover {
            background: rgba(16, 185, 129, 0.25);
        }
        
        .btn-warning {
            background: rgba(245, 158, 11, 0.15);
            color: #92400e;
        }
        
        .btn-warning:hover {
            background: rgba(245, 158, 11, 0.25);
        }
        
        .btn-info {
            background: rgba(59, 130, 246, 0.15);
            color: #2563eb;
        }
        
        .btn-info:hover {
            background: rgba(59, 130, 246, 0.25);
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
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
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
            transform: scale(1.1) translateY(-4px);
            box-shadow: 0 12px 32px rgba(197, 17, 179, 0.5);
        }
        
        .button-demo {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 16px;
        }
        
        .demo-item {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        @media (max-width: 768px) {
            .button-demo {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
                    <a href="Event Sheduling.php" class="menu-item">
                        <div class="icon-box icon-bg-purple">
                            <i class='bx bxs-calendar icon-purple'></i>
                        </div>
                        <span class="font-medium">Event Scheduling</span>
                    </a>
                    <a href="Button Showcase.php" class="menu-item active">
                        <div class="icon-box icon-bg-blue">
                            <i class='bx bxs-palette icon-blue'></i>
                        </div>
                        <span class="font-medium">Button Showcase</span>
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
                        <h1 style="font-size: 24px; font-weight: 700;">Button Showcase</h1>
                        <p style="color: var(--text-light);">Complete collection of button styles and examples</p>
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
                <!-- Primary Buttons -->
                <div class="showcase-section">
                    <h2 class="section-title">Primary Buttons</h2>
                    <p class="section-description">Main action buttons with gradient background</p>
                    
                    <div class="button-group">
                        <div class="button-group-title">Standard Primary</div>
                        <button class="btn btn-primary">
                            <i class='bx bx-plus'></i> Create Event
                        </button>
                        <button class="btn btn-primary">
                            <i class='bx bx-save'></i> Save
                        </button>
                        <button class="btn btn-primary">
                            <i class='bx bx-check'></i> Submit
                        </button>
                    </div>
                    
                    <div class="code-block">
                        <pre>&lt;button class="btn btn-primary"&gt;
    &lt;i class='bx bx-plus'&gt;&lt;/i&gt; Create Event
&lt;/button&gt;</pre>
                    </div>
                </div>
                
                <!-- Secondary Buttons -->
                <div class="showcase-section">
                    <h2 class="section-title">Secondary Buttons</h2>
                    <p class="section-description">Secondary actions with transparent background</p>
                    
                    <div class="button-group">
                        <div class="button-group-title">Standard Secondary</div>
                        <button class="btn btn-secondary">
                            <i class='bx bx-edit'></i> Edit
                        </button>
                        <button class="btn btn-secondary">
                            <i class='bx bx-cancel'></i> Cancel
                        </button>
                        <button class="btn btn-secondary">
                            <i class='bx bx-refresh'></i> Refresh
                        </button>
                    </div>
                    
                    <div class="code-block">
                        <pre>&lt;button class="btn btn-secondary"&gt;
    &lt;i class='bx bx-edit'&gt;&lt;/i&gt; Edit
&lt;/button&gt;</pre>
                    </div>
                </div>
                
                <!-- Danger Buttons -->
                <div class="showcase-section">
                    <h2 class="section-title">Danger Buttons</h2>
                    <p class="section-description">Destructive actions like delete or remove</p>
                    
                    <div class="button-group">
                        <div class="button-group-title">Danger Actions</div>
                        <button class="btn btn-danger">
                            <i class='bx bx-trash'></i> Delete
                        </button>
                        <button class="btn btn-danger">
                            <i class='bx bx-x'></i> Remove
                        </button>
                        <button class="btn btn-danger">
                            <i class='bx bx-block'></i> Block
                        </button>
                    </div>
                    
                    <div class="code-block">
                        <pre>&lt;button class="btn btn-danger"&gt;
    &lt;i class='bx bx-trash'&gt;&lt;/i&gt; Delete
&lt;/button&gt;</pre>
                    </div>
                </div>
                
                <!-- Status Buttons -->
                <div class="showcase-section">
                    <h2 class="section-title">Status Buttons</h2>
                    <p class="section-description">Buttons for different status actions</p>
                    
                    <div class="button-group">
                        <div class="button-group-title">Status Types</div>
                        <button class="btn btn-success">
                            <i class='bx bx-check-circle'></i> Approve
                        </button>
                        <button class="btn btn-warning">
                            <i class='bx bx-error'></i> Warning
                        </button>
                        <button class="btn btn-info">
                            <i class='bx bx-info-circle'></i> Info
                        </button>
                    </div>
                    
                    <div class="code-block">
                        <pre>&lt;button class="btn btn-success"&gt;Approve&lt;/button&gt;
&lt;button class="btn btn-warning"&gt;Warning&lt;/button&gt;
&lt;button class="btn btn-info"&gt;Info&lt;/button&gt;</pre>
                    </div>
                </div>
                
                <!-- Button Sizes -->
                <div class="showcase-section">
                    <h2 class="section-title">Button Sizes</h2>
                    <p class="section-description">Different sizes for various use cases</p>
                    
                    <div class="button-group">
                        <div class="button-group-title">Size Variations</div>
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
                            <i class='bx bx-plus'></i> Extra Large
                        </button>
                    </div>
                    
                    <div class="code-block">
                        <pre>&lt;button class="btn btn-primary btn-small"&gt;Small&lt;/button&gt;
&lt;button class="btn btn-primary"&gt;Default&lt;/button&gt;
&lt;button class="btn btn-primary btn-large"&gt;Large&lt;/button&gt;
&lt;button class="create-btn-large"&gt;Extra Large&lt;/button&gt;</pre>
                    </div>
                </div>
                
                <!-- Button Variants -->
                <div class="showcase-section">
                    <h2 class="section-title">Button Variants</h2>
                    <p class="section-description">Different button styles and variants</p>
                    
                    <div class="button-group">
                        <div class="button-group-title">Style Variants</div>
                        <button class="btn btn-primary">
                            <i class='bx bx-plus'></i> Filled
                        </button>
                        <button class="btn btn-outline">
                            <i class='bx bx-plus'></i> Outline
                        </button>
                        <button class="btn btn-primary btn-icon-only">
                            <i class='bx bx-plus'></i>
                        </button>
                        <button class="btn btn-primary btn-disabled">
                            <i class='bx bx-plus'></i> Disabled
                        </button>
                    </div>
                    
                    <div class="code-block">
                        <pre>&lt;button class="btn btn-primary"&gt;Filled&lt;/button&gt;
&lt;button class="btn btn-outline"&gt;Outline&lt;/button&gt;
&lt;button class="btn btn-primary btn-icon-only"&gt;
    &lt;i class='bx bx-plus'&gt;&lt;/i&gt;
&lt;/button&gt;
&lt;button class="btn btn-primary btn-disabled"&gt;Disabled&lt;/button&gt;</pre>
                    </div>
                </div>
                
                <!-- Large Create Button -->
                <div class="showcase-section">
                    <h2 class="section-title">Large Create Button</h2>
                    <p class="section-description">Prominent button for primary actions</p>
                    
                    <div class="button-group">
                        <div class="button-group-title">Create Button</div>
                        <button class="create-btn-large">
                            <i class='bx bx-plus'></i> Create New Event
                        </button>
                    </div>
                    
                    <div class="code-block">
                        <pre>&lt;button class="create-btn-large" onclick="openCreateModal()"&gt;
    &lt;i class='bx bx-plus'&gt;&lt;/i&gt; Create New Event
&lt;/button&gt;</pre>
                    </div>
                </div>
                
                <!-- Button with Forms -->
                <div class="showcase-section">
                    <h2 class="section-title">Buttons in Forms</h2>
                    <p class="section-description">How to use buttons with forms</p>
                    
                    <div class="button-demo">
                        <div class="demo-item">
                            <div class="button-group-title">Form Submit</div>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="create_event">
                                <button type="submit" class="btn btn-primary">
                                    <i class='bx bx-save'></i> Save Event
                                </button>
                            </form>
                        </div>
                        
                        <div class="demo-item">
                            <div class="button-group-title">Delete with Confirmation</div>
                            <form method="post" onsubmit="return confirm('Are you sure?');" style="display: inline;">
                                <input type="hidden" name="action" value="delete_event">
                                <input type="hidden" name="event_id" value="1">
                                <button type="submit" class="btn btn-danger">
                                    <i class='bx bx-trash'></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="code-block">
                        <pre>&lt;!-- Form Submit --&gt;
&lt;form method="post"&gt;
    &lt;button type="submit" class="btn btn-primary"&gt;
        &lt;i class='bx bx-save'&gt;&lt;/i&gt; Save
    &lt;/button&gt;
&lt;/form&gt;

&lt;!-- Delete with Confirmation --&gt;
&lt;form method="post" onsubmit="return confirm('Are you sure?');"&gt;
    &lt;input type="hidden" name="action" value="delete_event"&gt;
    &lt;button type="submit" class="btn btn-danger"&gt;
        &lt;i class='bx bx-trash'&gt;&lt;/i&gt; Delete
    &lt;/button&gt;
&lt;/form&gt;</pre>
                    </div>
                </div>
                
                <!-- Button Actions -->
                <div class="showcase-section">
                    <h2 class="section-title">Button with JavaScript Actions</h2>
                    <p class="section-description">Buttons that trigger JavaScript functions</p>
                    
                    <div class="button-group">
                        <div class="button-group-title">JavaScript Actions</div>
                        <button class="btn btn-primary" onclick="alert('Button clicked!')">
                            <i class='bx bx-bell'></i> Alert
                        </button>
                        <button class="btn btn-secondary" onclick="console.log('Clicked')">
                            <i class='bx bx-code-alt'></i> Console
                        </button>
                        <button class="btn btn-info" onclick="window.location.reload()">
                            <i class='bx bx-refresh'></i> Reload
                        </button>
                    </div>
                    
                    <div class="code-block">
                        <pre>&lt;button class="btn btn-primary" onclick="openCreateModal()"&gt;
    &lt;i class='bx bx-plus'&gt;&lt;/i&gt; Open Modal
&lt;/button&gt;

&lt;button class="btn btn-secondary" onclick="closeModal()"&gt;
    Cancel
&lt;/button&gt;

&lt;button class="btn btn-info" onclick="window.location.reload()"&gt;
    &lt;i class='bx bx-refresh'&gt;&lt;/i&gt; Reload
&lt;/button&gt;</pre>
                    </div>
                </div>
                
                <!-- Icon Buttons -->
                <div class="showcase-section">
                    <h2 class="section-title">Icon-Only Buttons</h2>
                    <p class="section-description">Buttons with only icons for compact UI</p>
                    
                    <div class="button-group">
                        <div class="button-group-title">Icon Buttons</div>
                        <button class="btn btn-primary btn-icon-only" title="Add">
                            <i class='bx bx-plus'></i>
                        </button>
                        <button class="btn btn-secondary btn-icon-only" title="Edit">
                            <i class='bx bx-edit'></i>
                        </button>
                        <button class="btn btn-danger btn-icon-only" title="Delete">
                            <i class='bx bx-trash'></i>
                        </button>
                        <button class="btn btn-success btn-icon-only" title="Check">
                            <i class='bx bx-check'></i>
                        </button>
                        <button class="btn btn-info btn-icon-only" title="Info">
                            <i class='bx bx-info-circle'></i>
                        </button>
                    </div>
                    
                    <div class="code-block">
                        <pre>&lt;button class="btn btn-primary btn-icon-only" title="Add"&gt;
    &lt;i class='bx bx-plus'&gt;&lt;/i&gt;
&lt;/button&gt;</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Floating Create Button -->
    <button class="floating-create-btn" onclick="alert('Floating button clicked!')" title="Floating Action Button">
        <i class='bx bx-plus'></i>
    </button>
    
    <script>
        // Demo functions
        function openCreateModal() {
            alert('Create modal would open here!');
        }
        
        function closeModal() {
            alert('Modal would close here!');
        }
    </script>
</body>
</html>

