<?php
// Generate unique ID for router
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

$generated_command = '';
$generated_id = '';
$router_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $router_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['router_name'] ?? 'router');
    $generated_id = generateUUID();
    $generated_command = "/tool fetch url=\"http://2.58.80.82:8095/mikrotik/{$generated_id}/install\" dst-path=mikrotik-commands.rsc; /import mikrotik-commands.rsc; /file remove mikrotik-commands.rsc";
    
    $config_dir = '/var/www/html/api/router_configs';
    if (!is_dir($config_dir)) mkdir($config_dir, 0755, true);
    
    $config = [
        'id' => $generated_id,
        'name' => $router_name,
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'pending',
        'client_ip' => '10.0.0.' . rand(10, 200)
    ];
    file_put_contents("{$config_dir}/{$generated_id}.json", json_encode($config, JSON_PRETTY_PRINT));
}

// Handle AJAX test request
if (isset($_GET['test']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $test_id = $_GET['id'];
    $config_file = '/var/www/html/api/router_configs/' . $test_id . '.json';
    
    if (!file_exists($config_file)) {
        echo json_encode(['success' => false, 'message' => 'Configuration not found']);
        exit;
    }
    
    $config = json_decode(file_get_contents($config_file), true);
    $router_name = $config['name'];
    
    $names_file = '/var/www/html/api/router_names.json';
    $found = false;
    $router_info = null;
    
    if (file_exists($names_file)) {
        $routers = json_decode(file_get_contents($names_file), true);
        foreach ($routers as $key => $router) {
            if ($router['name'] === $router_name) {
                $found = true;
                $router_info = $router;
                break;
            }
        }
    }
    
    if ($found) {
        echo json_encode([
            'success' => true, 
            'message' => 'Router connected successfully!',
            'router' => $router_info
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Router not connected yet.'
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WireGuard One-Command Installer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);min-height:100vh;padding:20px;}
        .container{max-width:1200px;margin:0 auto;}
        .header{background:rgba(15,23,42,0.8);backdrop-filter:blur(20px);border-radius:24px;padding:24px 32px;margin-bottom:32px;border:1px solid rgba(99,102,241,0.2);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;}
        .logo h1{font-size:28px;font-weight:800;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);-webkit-background-clip:text;background-clip:text;color:transparent;}
        .logo p{color:#94a3b8;font-size:13px;margin-top:4px;}
        .nav{display:flex;gap:12px;flex-wrap:wrap;}
        .nav-btn{background:rgba(51,65,85,0.8);color:#e2e8f0;padding:10px 20px;border-radius:12px;text-decoration:none;font-weight:500;transition:all 0.3s;}
        .nav-btn:hover,.nav-btn.active{background:#667eea;transform:translateY(-2px);}
        .card{background:rgba(15,23,42,0.6);backdrop-filter:blur(10px);border-radius:24px;padding:32px;border:1px solid rgba(99,102,241,0.2);margin-bottom:24px;}
        .card h2{color:white;margin-bottom:20px;}
        .form-group{margin-bottom:20px;}
        label{display:block;margin-bottom:8px;color:#e2e8f0;font-weight:500;}
        input{width:100%;padding:14px;background:rgba(51,65,85,0.5);border:1px solid rgba(99,102,241,0.3);border-radius:12px;color:white;font-size:16px;}
        input:focus{outline:none;border-color:#667eea;}
        .btn-primary{background:#667eea;color:white;border:none;padding:14px 28px;border-radius:12px;font-weight:600;cursor:pointer;width:100%;font-size:16px;transition:all 0.3s;}
        .btn-primary:hover{background:#5a67d8;transform:translateY(-2px);}
        .command-box{background:#1e293b;border-radius:12px;padding:20px;font-family:monospace;font-size:13px;color:#e2e8f0;margin:16px 0;overflow-x:auto;word-break:break-all;}
        .copy-btn{background:#48bb78;color:white;border:none;padding:10px 20px;border-radius:10px;cursor:pointer;font-weight:500;margin-right:10px;}
        .copy-btn:hover{background:#38a169;}
        .test-btn{background:#f59e0b;color:white;border:none;padding:10px 20px;border-radius:10px;cursor:pointer;font-weight:500;}
        .test-btn:hover{background:#d97706;}
        .info{background:rgba(51,65,85,0.3);padding:16px;border-radius:12px;margin-top:16px;border-left:4px solid #667eea;color:#94a3b8;}
        .test-result{margin-top:16px;padding:16px;border-radius:12px;display:none;}
        .test-result.success{background:rgba(72,187,120,0.2);border:1px solid #48bb78;display:block;color:#48bb78;}
        .test-result.error{background:rgba(245,101,101,0.2);border:1px solid #f56565;display:block;color:#f87171;}
        .test-result.waiting{background:rgba(245,158,11,0.2);border:1px solid #f59e0b;display:block;color:#f59e0b;}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:24px;}
        @media(max-width:768px){.row{grid-template-columns:1fr;}}
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo"><h1>⚡ WireGuard One-Command Installer</h1><p>One line. One paste. Done.</p></div>
        <div class="nav">
            <a href="onecommand.php" class="nav-btn active">🚀 One Command</a>
            <a href="complete-auto-final.php" class="nav-btn">📝 Script Generator</a>
            <a href="admin.php" class="nav-btn">⚙️ Admin</a>
            <a href="routeraccess.php" class="nav-btn">🔗 Router Access</a>
        </div>
    </div>

    <div class="row">
        <div class="card">
            <h2>📝 Generate Your One-Line Command</h2>
            <form method="POST" id="generateForm">
                <div class="form-group">
                    <label>Router Name</label>
                    <input type="text" name="router_name" id="routerName" placeholder="e.g., Office-Branch" required>
                </div>
                <button type="submit" name="generate" class="btn-primary">🚀 Generate Install Command</button>
            </form>
        </div>

        <?php if ($generated_command): ?>
        <div class="card">
            <h2>📋 Your One-Line Command</h2>
            <div class="command-box">
                <code id="commandText" style="word-break:break-all;"><?php echo htmlspecialchars($generated_command); ?></code>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button class="copy-btn" onclick="copyCommand()">📋 Copy Command</button>
                <button class="test-btn" onclick="testConnection('<?php echo $generated_id; ?>', '<?php echo htmlspecialchars($router_name); ?>')">🔌 Test Connection</button>
            </div>
            <div id="testResult" class="test-result"></div>
            <div class="info">
                💡 <strong>How to use:</strong><br>
                1. Click the Copy button above<br>
                2. Open MikroTik terminal (WinBox → New Terminal)<br>
                3. Right-click and Paste (or Ctrl+V)<br>
                4. Press Enter<br>
                5. Wait 10 seconds<br>
                6. Click "Test Connection" to verify
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copyCommand() {
    const commandText = document.getElementById('commandText');
    if (!commandText) {
        alert('No command generated yet. Please generate a command first.');
        return;
    }
    
    const text = commandText.innerText;
    
    // Try modern clipboard API first
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            alert('✅ Command copied to clipboard!\n\nPaste into MikroTik terminal.');
        }).catch(function() {
            // Fallback for security restrictions
            copyFallback(text);
        });
    } else {
        // Fallback for older browsers
        copyFallback(text);
    }
}

function copyFallback(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.top = '-9999px';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    textarea.setSelectionRange(0, text.length);
    
    try {
        const success = document.execCommand('copy');
        if (success) {
            alert('✅ Command copied to clipboard!\n\nPaste into MikroTik terminal.');
        } else {
            alert('❌ Failed to copy. Please select and copy the command manually.');
        }
    } catch (err) {
        alert('❌ Failed to copy. Please select and copy the command manually.');
    }
    
    document.body.removeChild(textarea);
}

async function testConnection(routerId, routerName) {
    const resultDiv = document.getElementById('testResult');
    resultDiv.innerHTML = '🔄 Checking connection... Please wait.';
    resultDiv.className = 'test-result waiting';
    
    let attempts = 0;
    const maxAttempts = 5;
    const intervalSeconds = 12;
    
    const checkInterval = setInterval(async () => {
        attempts++;
        
        try {
            const response = await fetch('/api/router_names.json?_=' + Date.now());
            const routers = await response.json();
            
            let found = false;
            let routerInfo = null;
            
            for (const [key, router] of Object.entries(routers)) {
                if (router.name === routerName) {
                    found = true;
                    routerInfo = router;
                    break;
                }
            }
            
            if (found) {
                clearInterval(checkInterval);
                resultDiv.innerHTML = `
                    ✅ <strong>CONNECTION SUCCESSFUL!</strong><br>
                    Router: ${routerInfo.name}<br>
                    IP: ${routerInfo.ip}<br>
                    Registered: ${routerInfo.registered_at}<br>
                    <span style="display:inline-block;margin-top:8px;padding:4px 12px;border-radius:20px;font-size:12px;background:#48bb78;color:white;">● Online</span>
                `;
                resultDiv.className = 'test-result success';
            } else if (attempts >= maxAttempts) {
                clearInterval(checkInterval);
                resultDiv.innerHTML = `
                    ❌ <strong>Connection Timeout</strong><br>
                    Router not detected after 1 minute.<br><br>
                    Please check:<br>
                    • Did you paste the command in MikroTik terminal?<br>
                    • Did you press Enter after pasting?<br>
                    • Is the router connected to the internet?<br>
                    • Try running the command again.
                `;
                resultDiv.className = 'test-result error';
            } else {
                resultDiv.innerHTML = `⏳ Waiting for router to connect... (Attempt ${attempts}/${maxAttempts})<br>Make sure you've pasted and run the command in MikroTik terminal.`;
                resultDiv.className = 'test-result waiting';
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }, intervalSeconds * 1000);
}
</script>
</body>
</html>
