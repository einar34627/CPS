<?php
/**
 * Button Creator Tool
 * Easy way to create and copy buttons for Tip Portal
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
    <title>Button Creator - Tip Portal</title>
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
        .card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-color, #0f172a);
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .preview-area {
            background: #f8f9fa;
            padding: 24px;
            border-radius: 8px;
            min-height: 100px;
            margin-top: 16px;
            border: 2px dashed #cbd5e1;
        }
        .code-output {
            background: #1e293b;
            color: #e2e8f0;
            padding: 16px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin-top: 16px;
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
            margin: 4px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #c511b3, #a855f7);
            color: white;
        }
        .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: var(--text-color);
            border: 1px solid rgba(0,0,0,0.1);
        }
        .btn-success {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
        }
        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #b91c1c;
        }
        .btn-warning {
            background: rgba(245, 158, 11, 0.15);
            color: #92400e;
        }
        .btn-info {
            background: rgba(59, 130, 246, 0.15);
            color: #2563eb;
        }
        .btn-outline {
            background: transparent;
            border: 2px solid #c511b3;
            color: #c511b3;
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
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        .copy-btn {
            background: #10b981;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .copy-btn:hover {
            background: #059669;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="margin-bottom: 24px;">Button Creator Tool</h1>
        
        <div class="card">
            <h2>Create Your Button</h2>
            <form id="buttonForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>Button Type</label>
                        <select id="buttonType">
                            <option value="primary">Primary</option>
                            <option value="secondary">Secondary</option>
                            <option value="success">Success</option>
                            <option value="danger">Danger</option>
                            <option value="warning">Warning</option>
                            <option value="info">Info</option>
                            <option value="outline">Outline</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Button Size</label>
                        <select id="buttonSize">
                            <option value="">Default</option>
                            <option value="small">Small</option>
                            <option value="large">Large</option>
                            <option value="icon-only">Icon Only</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Button Text</label>
                    <input type="text" id="buttonText" placeholder="e.g., Submit Tip" value="Submit Tip">
                </div>
                
                <div class="form-group">
                    <label>Icon (Boxicon class)</label>
                    <input type="text" id="buttonIcon" placeholder="e.g., bx-plus" value="bx-plus">
                    <small style="color: #64748b;">Leave empty for no icon</small>
                </div>
                
                <div class="form-group">
                    <label>OnClick Action</label>
                    <select id="onClickAction">
                        <option value="">None</option>
                        <option value="openCreateModal()">Open Create Modal</option>
                        <option value="window.location.reload()">Refresh Page</option>
                        <option value="window.print()">Print Page</option>
                        <option value="window.location.href='Feedback.php'">Go to Feedback</option>
                        <option value="window.location.href='admin_dashboard.php'">Go to Dashboard</option>
                        <option value="window.location.href='?status=verified'">View Verified</option>
                        <option value="window.location.href='?status=pending'">View Pending</option>
                        <option value="window.location.href='?priority=Urgent'">View Urgent</option>
                        <option value="window.location.href='?status=all'">View All</option>
                        <option value="clearFilters()">Clear Filters</option>
                        <option value="exportTips()">Export Tips</option>
                        <option value="showStats()">Show Statistics</option>
                        <option value="custom">Custom Action</option>
                    </select>
                </div>
                
                <div class="form-group" id="customActionGroup" style="display: none;">
                    <label>Custom Action Code</label>
                    <input type="text" id="customAction" placeholder="e.g., alert('Hello')">
                </div>
                
                <div class="form-group">
                    <label>Button ID (Optional)</label>
                    <input type="text" id="buttonId" placeholder="e.g., submitBtn">
                </div>
                
                <div class="form-group">
                    <label>Button Class (Additional)</label>
                    <input type="text" id="buttonClass" placeholder="e.g., my-custom-class">
                </div>
                
                <button type="button" class="copy-btn" onclick="generateButton()">
                    <i class='bx bx-code-alt'></i> Generate Button
                </button>
            </form>
        </div>
        
        <div class="card">
            <h2>Button Preview</h2>
            <div class="preview-area" id="previewArea">
                <p style="color: #64748b; text-align: center;">Your button will appear here</p>
            </div>
        </div>
        
        <div class="card">
            <h2>Generated Code</h2>
            <div class="code-output" id="codeOutput">
                <p style="color: #94a3b8;">Click "Generate Button" to see the code</p>
            </div>
            <div class="action-buttons">
                <button class="copy-btn" onclick="copyCode()">
                    <i class='bx bx-copy'></i> Copy Code
                </button>
                <button class="copy-btn" style="background: #3b82f6;" onclick="copyHTML()">
                    <i class='bx bx-code-block'></i> Copy HTML Only
                </button>
                <button class="copy-btn" style="background: #8b5cf6;" onclick="saveButton()">
                    <i class='bx bx-save'></i> Save to File
                </button>
            </div>
        </div>
        
        <div class="card">
            <h2>Quick Button Templates</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                <button class="btn btn-primary" onclick="loadTemplate('submit')">
                    <i class='bx bx-plus'></i> Submit Tip Template
                </button>
                <button class="btn btn-success" onclick="loadTemplate('verified')">
                    <i class='bx bx-check-circle'></i> View Verified Template
                </button>
                <button class="btn btn-warning" onclick="loadTemplate('pending')">
                    <i class='bx bx-time'></i> View Pending Template
                </button>
                <button class="btn btn-danger" onclick="loadTemplate('urgent')">
                    <i class='bx bx-error-circle'></i> View Urgent Template
                </button>
                <button class="btn btn-info" onclick="loadTemplate('all')">
                    <i class='bx bx-list-ul'></i> View All Template
                </button>
                <button class="btn btn-secondary" onclick="loadTemplate('refresh')">
                    <i class='bx bx-refresh'></i> Refresh Template
                </button>
            </div>
        </div>
    </div>
    
    <script>
        const iconList = [
            'bx-plus', 'bx-check-circle', 'bx-time', 'bx-error-circle', 'bx-list-ul',
            'bx-refresh', 'bx-printer', 'bx-download', 'bx-home', 'bx-message-square-dots',
            'bx-edit', 'bx-trash', 'bx-save', 'bx-search', 'bx-filter-alt', 'bx-bar-chart',
            'bx-shield', 'bx-check-shield', 'bx-error-alt', 'bx-bell', 'bx-user', 'bx-calendar',
            'bx-map', 'bx-phone', 'bx-info-circle', 'bx-x', 'bx-check', 'bx-upload'
        ];
        
        function generateButton() {
            const type = document.getElementById('buttonType').value;
            const size = document.getElementById('buttonSize').value;
            const text = document.getElementById('buttonText').value;
            const icon = document.getElementById('buttonIcon').value;
            const action = document.getElementById('onClickAction').value;
            const customAction = document.getElementById('customAction').value;
            const buttonId = document.getElementById('buttonId').value;
            const buttonClass = document.getElementById('buttonClass').value;
            
            let onClick = '';
            if (action === 'custom' && customAction) {
                onClick = customAction;
            } else if (action) {
                onClick = action;
            }
            
            let classes = `btn btn-${type}`;
            if (size) {
                classes += ` btn-${size}`;
            }
            if (buttonClass) {
                classes += ` ${buttonClass}`;
            }
            
            let attributes = '';
            if (buttonId) {
                attributes += ` id="${buttonId}"`;
            }
            if (onClick) {
                attributes += ` onclick="${onClick}"`;
            }
            
            let iconHTML = '';
            if (icon) {
                iconHTML = `<i class='bx ${icon}'></i> `;
            }
            
            let buttonHTML = '';
            if (size === 'icon-only') {
                buttonHTML = `<button class="${classes}"${attributes} title="${text}">\n    ${iconHTML}\n</button>`;
            } else {
                buttonHTML = `<button class="${classes}"${attributes}>\n    ${iconHTML}${text}\n</button>`;
            }
            
            // Show preview
            document.getElementById('previewArea').innerHTML = buttonHTML;
            
            // Show code
            document.getElementById('codeOutput').innerHTML = `<pre>${escapeHTML(buttonHTML)}</pre>`;
        }
        
        function escapeHTML(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function copyCode() {
            const code = document.getElementById('codeOutput').textContent;
            navigator.clipboard.writeText(code).then(() => {
                alert('Code copied to clipboard!');
            });
        }
        
        function copyHTML() {
            const preview = document.getElementById('previewArea').innerHTML;
            navigator.clipboard.writeText(preview).then(() => {
                alert('HTML copied to clipboard!');
            });
        }
        
        function saveButton() {
            const code = document.getElementById('codeOutput').textContent;
            const blob = new Blob([code], { type: 'text/html' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'button-code.html';
            a.click();
            URL.revokeObjectURL(url);
        }
        
        function loadTemplate(template) {
            const templates = {
                submit: {
                    type: 'primary',
                    text: 'Submit Tip',
                    icon: 'bx-plus',
                    action: 'openCreateModal()'
                },
                verified: {
                    type: 'success',
                    text: 'View Verified',
                    icon: 'bx-check-circle',
                    action: "window.location.href='?status=verified'"
                },
                pending: {
                    type: 'warning',
                    text: 'View Pending',
                    icon: 'bx-time',
                    action: "window.location.href='?status=pending'"
                },
                urgent: {
                    type: 'danger',
                    text: 'View Urgent',
                    icon: 'bx-error-circle',
                    action: "window.location.href='?priority=Urgent'"
                },
                all: {
                    type: 'info',
                    text: 'View All',
                    icon: 'bx-list-ul',
                    action: "window.location.href='?status=all'"
                },
                refresh: {
                    type: 'secondary',
                    text: 'Refresh',
                    icon: 'bx-refresh',
                    action: 'window.location.reload()'
                }
            };
            
            const t = templates[template];
            if (t) {
                document.getElementById('buttonType').value = t.type;
                document.getElementById('buttonText').value = t.text;
                document.getElementById('buttonIcon').value = t.icon;
                document.getElementById('onClickAction').value = t.action;
                generateButton();
            }
        }
        
        document.getElementById('onClickAction').addEventListener('change', function() {
            if (this.value === 'custom') {
                document.getElementById('customActionGroup').style.display = 'block';
            } else {
                document.getElementById('customActionGroup').style.display = 'none';
            }
        });
        
        // Auto-generate on input change
        ['buttonType', 'buttonSize', 'buttonText', 'buttonIcon', 'onClickAction'].forEach(id => {
            document.getElementById(id).addEventListener('change', generateButton);
        });
        
        document.getElementById('customAction').addEventListener('input', generateButton);
    </script>
</body>
</html>

