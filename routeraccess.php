<?php
session_start();
$admin_password = 'admin123';

if (isset($_POST['login'])) {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['router_access'] = true;
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: routeraccess.php');
    exit;
}

$is_authenticated = isset($_SESSION['router_access']) && $_SESSION['router_access'] === true;

// Handle delete router
if ($is_authenticated && isset($_POST['delete_router']) && isset($_POST['public_key'])) {
    $public_key = $_POST['public_key'];
    $router_name = $_POST['router_name'];
    
    // Remove from router_names.json
    $names_file = '/var/www/html/api/router_names.json';
    if (file_exists($names_file)) {
        $routers = json_decode(file_get_contents($names_file), true);
        if (isset($routers[$public_key])) {
            unset($routers[$public_key]);
            file_put_contents($names_file, json_encode($routers, JSON_PRETTY_PRINT));
        }
    }
    
    // Remove from WireGuard peer
    shell_exec("sudo wg set wg0 peer $public_key remove 2>&1");
    
    // Remove from config file
    $config_file = '/etc/wireguard/wg0.conf';
    if (file_exists($config_file)) {
        $content = file_get_contents($config_file);
        $pattern = '/\n?\[Peer\]\nPublicKey = ' . preg_quote($public_key, '/') . '\nAllowedIPs = [^\n]+\n/';
        $new_content = preg_replace($pattern, '', $content);
        file_put_contents($config_file, $new_content);
    }
    
    $delete_message = "✅ Router '$router_name' has been removed!";
}

// Get all routers from registration
$names_file = '/var/www/html/api/router_names.json';
$routers = [];

if (file_exists($names_file)) {
    $registered_routers = json_decode(file_get_contents($names_file), true);
    foreach ($registered_routers as $public_key => $router) {
        $ip = str_replace('/32', '', $router['ip']);
        $routers[] = [
            'public_key' => $public_key,
            'name' => $router['name'],
            'ip' => $ip,
            'registered_at' => $router['registered_at']
        ];
    }
}

// Get WireGuard status for online/offline
$wg_output = shell_exec("sudo wg show 2>/dev/null");
$online_ips = [];

if ($wg_output) {
    $lines = explode("\n", $wg_output);
    $in_peer = false;
    
    foreach ($lines as $line) {
        if (preg_match('/^peer:/', $line)) {
            $in_peer = true;
        }
        if ($in_peer && preg_match('/allowed ips:\s+(10\.0\.0\.\d+)\/\d+/', $line, $matches)) {
            $online_ips[] = $matches[1];
            $in_peer = false;
        }
    }
}

$status_file = '/var/www/html/wg-status.txt';
if (file_exists($status_file)) {
    $status_content = file_get_contents($status_file);
    if (preg_match_all('/allowed ips: (10\.0\.0\.\d+)/', $status_content, $matches)) {
        foreach ($matches[1] as $ip) {
            if (!in_array($ip, $online_ips)) {
                $online_ips[] = $ip;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Router Access Portal</title>
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
        .login-card{max-width:400px;margin:100px auto;background:rgba(15,23,42,0.95);border-radius:24px;padding:40px;text-align:center;border:1px solid rgba(99,102,241,0.3);}
        .login-card input{width:100%;padding:14px;background:rgba(51,65,85,0.5);border:1px solid rgba(99,102,241,0.3);border-radius:12px;color:white;margin-bottom:16px;}
        .login-card button{width:100%;padding:14px;background:#667eea;border:none;border-radius:12px;color:white;font-weight:600;cursor:pointer;}
        .router-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:20px;}
        .router-card{background:rgba(15,23,42,0.6);backdrop-filter:blur(10px);border-radius:20px;padding:24px;border:1px solid rgba(99,102,241,0.2);transition:all 0.3s;position:relative;}
        .router-card:hover{transform:translateY(-3px);border-color:#667eea;}
        .router-name{font-size:20px;font-weight:700;color:white;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
        .status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:500;}
        .status-online{background:rgba(72,187,120,0.2);color:#48bb78;border:1px solid #48bb78;}
        .status-offline{background:rgba(245,101,101,0.2);color:#f87171;border:1px solid #f56565;}
        .info-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(99,102,241,0.2);}
        .info-label{color:#94a3b8;font-size:13px;}
        .info-value{color:white;font-family:monospace;font-size:13px;}
        .credential-box{background:rgba(0,0,0,0.3);border-radius:12px;padding:12px;margin:12px 0;}
        .credential-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;}
        .copy-btn{background:#48bb78;color:white;border:none;padding:4px 12px;border-radius:6px;font-size:11px;margin-left:8px;cursor:pointer;}
        .delete-btn{background:#ef4444;color:white;border:none;padding:8px 16px;border-radius:10px;cursor:pointer;font-size:13px;margin-top:12px;width:100%;transition:all 0.3s;}
        .delete-btn:hover{background:#dc2626;transform:translateY(-2px);}
        .message{background:#48bb78;color:white;padding:12px;border-radius:12px;margin-bottom:20px;}
        .message.error{background:#ef4444;}
        .info-text{margin-top:20px;padding:16px;background:rgba(51,65,85,0.3);border-radius:12px;color:#94a3b8;font-size:13px;}
        .info-text code{background:#1e293b;padding:4px 8px;border-radius:6px;color:#e2e8f0;}
        .last-update{font-size:11px;color:#64748b;margin-top:20px;text-align:right;}
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo"><h1>🔐 Router Access Portal</h1><p>Secure Remote Access - Admin: admin / cosmas</p></div>
        <div class="nav">
            <a href="routeraccess.php" class="nav-btn active">🔗 Router Access</a>
            <a href="onecommand.php" class="nav-btn">🚀 One Command</a>
            <a href="complete-auto-final.php" class="nav-btn">📝 Script Generator</a>
            <a href="admin.php" class="nav-btn">⚙️ Admin</a>
        </div>
    </div>

    <?php if (!$is_authenticated): ?>
    <div class="login-card">
        <h2 style="color:white;margin-bottom:20px;">🔐 Admin Access Required</h2>
        <form method="POST">
            <input type="password" name="password" placeholder="Enter Password" required>
            <button type="submit" name="login">Access Router Portal</button>
        </form>
    </div>
    <?php else: ?>
    
    <?php if (isset($delete_message)): ?>
        <div class="message"><?php echo $delete_message; ?></div>
    <?php endif; ?>
    
    <div class="router-grid">
        <?php if (empty($routers)): ?>
            <div style="grid-column:1/-1; text-align:center; padding:60px; background:rgba(15,23,42,0.6); border-radius:24px;">
                <p style="color:#94a3b8;">No routers registered yet</p>
                <p style="color:#64748b; font-size:13px; margin-top:8px;">Generate a script using the One Command page</p>
            </div>
        <?php else: ?>
            <?php foreach ($routers as $router): 
                $is_online = in_array($router['ip'], $online_ips);
            ?>
            <div class="router-card">
                <div class="router-name">
                    <span>🏷️ <?php echo htmlspecialchars($router['name']); ?></span>
                    <span class="status-badge <?php echo $is_online ? 'status-online' : 'status-offline'; ?>">
                        <?php echo $is_online ? '● Online' : '○ Offline'; ?>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">📍 VPN IP:</span>
                    <span class="info-value"><code><?php echo $router['ip']; ?></code></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">🕐 Registered:</span>
                    <span class="info-value"><?php echo $router['registered_at']; ?></span>
                </div>
                
                <div class="credential-box">
                    <div class="credential-row">
                        <span class="info-label">🔐 Username:</span>
                        <span class="info-value">
                            <code>admin</code>
                            <button class="copy-btn" onclick="copyToClipboard('admin', 'Username')">📋 Copy</button>
                        </span>
                    </div>
                    <div class="credential-row">
                        <span class="info-label">🔑 Password:</span>
                        <span class="info-value">
                            <code>cosmas</code>
                            <button class="copy-btn" onclick="copyToClipboard('cosmas', 'Password')">📋 Copy</button>
                        </span>
                    </div>
                    <div class="credential-row">
                        <span class="info-label">🔗 SSH:</span>
                        <span class="info-value"><code>ssh admin@<?php echo $router['ip']; ?></code></span>
                    </div>
                    <div class="credential-row">
                        <span class="info-label">🌐 Web:</span>
                        <span class="info-value"><code>http://<?php echo $router['ip']; ?></code></span>
                    </div>
                    <div class="credential-row">
                        <span class="info-label">📋 WinBox:</span>
                        <span class="info-value"><code><?php echo $router['ip']; ?> (admin / cosmas)</code></span>
                    </div>
                </div>
                
                <form method="POST" onsubmit="return confirm('⚠️ Are you sure you want to delete router \'<?php echo htmlspecialchars($router['name']); ?>\'?\n\nThis will remove it from WireGuard and the dashboard.\nThis action cannot be undone!');">
                    <input type="hidden" name="public_key" value="<?php echo htmlspecialchars($router['public_key']); ?>">
                    <input type="hidden" name="router_name" value="<?php echo htmlspecialchars($router['name']); ?>">
                    <button type="submit" name="delete_router" class="delete-btn">🗑️ Delete Router</button>
                </form>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="last-update">
        Last updated: <?php echo date('Y-m-d H:i:s'); ?>
        <button onclick="location.reload()" style="background:#667eea; color:white; border:none; padding:4px 12px; border-radius:6px; margin-left:10px; cursor:pointer;">🔄 Refresh</button>
    </div>

    <div class="info-text">
        <strong>💡 Universal Admin Credentials for ALL routers:</strong><br><br>
        <code>Username: admin</code><br>
        <code>Password: cosmas</code><br><br>
        
        <strong>🔹 SSH Access (from VPS terminal):</strong><br>
        <code>ssh admin@10.0.0.x</code><br>
        Password: <code>cosmas</code><br><br>
        
        <strong>🔹 WinBox Access:</strong><br>
        <code>Connect to: 10.0.0.x</code><br>
        Username: <code>admin</code> / Password: <code>cosmas</code><br><br>
        
        <strong>⚠️ Delete Router:</strong> Click the red "Delete Router" button to remove a router from the system.
    </div>
    
    <?php endif; ?>
</div>

<script>
function copyToClipboard(text, label) {
    navigator.clipboard.writeText(text);
    alert('✅ ' + label + ' copied to clipboard');
}
</script>
</body>
</html>
