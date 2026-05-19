<?php
// Start session at the very beginning - NO output before this
session_start();

$admin_password = 'admin123';
$message = '';

// Simple auth
if (isset($_POST['login'])) {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Handle actions
if ($is_admin) {
    // Add peer
    if (isset($_POST['add_peer'])) {
        $pubkey = escapeshellarg($_POST['public_key']);
        $ip = escapeshellarg($_POST['client_ip']);
        shell_exec("sudo wg set wg0 peer $pubkey allowed-ips={$ip}/32 persistent-keepalive=25 2>&1");
        // Add to config
        $config = file_get_contents('/etc/wireguard/wg0.conf');
        $new_peer = "\n\n[Peer]\nPublicKey = " . $_POST['public_key'] . "\nAllowedIPs = " . $_POST['client_ip'] . "/32\n";
        file_put_contents('/etc/wireguard/wg0.conf', $config . $new_peer);
        $message = "✅ Peer added successfully!";
    }
    
    // Delete peer
    if (isset($_POST['delete_peer'])) {
        $pubkey = escapeshellarg($_POST['public_key']);
        shell_exec("sudo wg set wg0 peer $pubkey remove 2>&1");
        // Remove from config
        $config = file_get_contents('/etc/wireguard/wg0.conf');
        $pattern = '/\n\n\[Peer\]\nPublicKey = ' . preg_quote($_POST['public_key'], '/') . '\nAllowedIPs = [^\n]+\n/';
        $new_config = preg_replace($pattern, '', $config);
        file_put_contents('/etc/wireguard/wg0.conf', $new_config);
        $message = "🗑️ Peer removed successfully!";
    }
    
    // Restart WireGuard
    if (isset($_POST['restart_wg'])) {
        shell_exec("sudo systemctl restart wg-quick@wg0 2>&1");
        $message = "🔄 WireGuard restarted!";
    }
    
    // Backup config
    if (isset($_POST['backup'])) {
        $backup_file = "/var/www/html/backup_wg0_" . date('Ymd_His') . ".conf";
        copy('/etc/wireguard/wg0.conf', $backup_file);
        $message = "💾 Backup created!";
    }
}

// Get data
$wg_output = shell_exec("sudo wg show 2>&1");
$config_content = file_exists('/etc/wireguard/wg0.conf') ? file_get_contents('/etc/wireguard/wg0.conf') : '';
$reg_log = file_exists('/var/www/html/api/registered_peers.log') ? file_get_contents('/var/www/html/api/registered_peers.log') : '';
$system_load = sys_getloadavg();
$disk_free = disk_free_space('/');
$disk_total = disk_total_space('/');
$disk_percent = round(($disk_total - $disk_free) / $disk_total * 100);

// Count peers
$peer_count = 0;
if (preg_match_all('/\[Peer\]/', $config_content, $matches)) {
    $peer_count = count($matches[0]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WireGuard Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);min-height:100vh;padding:20px;}
        .container{max-width:1400px;margin:0 auto;}
        .header{background:rgba(15,23,42,0.8);backdrop-filter:blur(20px);border-radius:24px;padding:20px 32px;margin-bottom:32px;border:1px solid rgba(99,102,241,0.2);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;}
        .logo h1{font-size:28px;font-weight:800;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);-webkit-background-clip:text;background-clip:text;color:transparent;}
        .nav{display:flex;gap:12px;}
        .nav-btn{background:rgba(51,65,85,0.8);color:#e2e8f0;padding:10px 20px;border-radius:12px;text-decoration:none;font-weight:500;transition:all 0.3s;}
        .nav-btn:hover,.nav-btn.active{background:#667eea;}
        .card{background:rgba(15,23,42,0.6);backdrop-filter:blur(10px);border-radius:24px;padding:24px;margin-bottom:24px;border:1px solid rgba(99,102,241,0.2);}
        .card h2{color:white;margin-bottom:20px;padding-bottom:10px;border-bottom:2px solid #667eea;}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:24px;}
        .stat-card{background:rgba(51,65,85,0.3);border-radius:16px;padding:20px;text-align:center;}
        .stat-value{font-size:32px;font-weight:800;color:#667eea;}
        .stat-label{color:#94a3b8;font-size:13px;margin-top:8px;}
        table{width:100%;border-collapse:collapse;}
        th,td{padding:12px;text-align:left;border-bottom:1px solid rgba(51,65,85,0.3);color:#e2e8f0;}
        th{background:rgba(51,65,85,0.5);color:#667eea;}
        .mono{font-family:monospace;font-size:11px;}
        input,textarea{width:100%;padding:12px;background:rgba(51,65,85,0.5);border:1px solid rgba(99,102,241,0.3);border-radius:12px;color:white;margin-bottom:12px;}
        input:focus{outline:none;border-color:#667eea;}
        button{background:#667eea;color:white;border:none;padding:10px 20px;border-radius:10px;cursor:pointer;transition:all 0.3s;}
        button:hover{background:#5a67d8;transform:translateY(-2px);}
        .btn-danger{background:#ef4444;}
        .btn-danger:hover{background:#dc2626;}
        .btn-success{background:#48bb78;}
        .login-box{max-width:400px;margin:100px auto;background:rgba(15,23,42,0.95);border-radius:24px;padding:40px;text-align:center;}
        .message{background:#48bb78;color:white;padding:12px;border-radius:12px;margin-bottom:20px;}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:24px;}
        pre{background:rgba(0,0,0,0.5);padding:16px;border-radius:12px;overflow-x:auto;font-size:11px;color:#94a3b8;}
        @media(max-width:768px){.row{grid-template-columns:1fr;}}
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo"><h1>⚙️ WireGuard Admin Panel</h1><p>System Administration</p></div>
        <div class="nav">
            <a href="index.html" class="nav-btn">📊 Dashboard</a>
            <a href="complete-auto-final.php" class="nav-btn">📝 Script Generator</a>
            <a href="admin.php" class="nav-btn active">⚙️ Admin</a>
            <?php if ($is_admin): ?><a href="?logout=1" class="nav-btn" style="background:#ef4444;">🚪 Logout</a><?php endif; ?>
        </div>
    </div>

<?php if (!$is_admin): ?>
    <div class="login-box">
        <h2 style="color:white;margin-bottom:20px;">🔐 Admin Access</h2>
        <form method="POST">
            <input type="password" name="password" placeholder="Enter Password" required>
            <button type="submit" name="login">Login</button>
        </form>
    </div>
<?php else: ?>
    <?php if (!empty($message)): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-value"><?php echo $peer_count; ?></div><div class="stat-label">Total Peers</div></div>
        <div class="stat-card"><div class="stat-value"><?php echo round($disk_percent); ?>%</div><div class="stat-label">Disk Usage</div></div>
        <div class="stat-card"><div class="stat-value"><?php echo $system_load[0]; ?></div><div class="stat-label">Load Average</div></div>
        <div class="stat-card"><div class="stat-value">51820</div><div class="stat-label">WireGuard Port</div></div>
    </div>

    <div class="row">
        <div class="card">
            <h2>➕ Add New Peer Manually</h2>
            <form method="POST">
                <input type="text" name="public_key" placeholder="Client Public Key" required>
                <input type="text" name="client_ip" placeholder="Client IP (e.g., 10.0.0.10)" required>
                <button type="submit" name="add_peer" class="btn-success">✅ Add Peer</button>
            </form>
        </div>

        <div class="card">
            <h2>🔧 System Actions</h2>
            <form method="POST" style="display:inline-block;margin-right:10px;">
                <button type="submit" name="restart_wg">🔄 Restart WireGuard</button>
            </form>
            <form method="POST" style="display:inline-block;">
                <button type="submit" name="backup">💾 Backup Config</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h2>🔗 All Configured Peers</h2>
        <?php
        $peers = [];
        $sections = explode('[Peer]', $config_content);
        foreach ($sections as $section) {
            if (preg_match('/PublicKey\s*=\s*(.+)/', $section, $key)) {
                preg_match('/AllowedIPs\s*=\s*(.+)/', $section, $ip);
                $peers[] = ['key' => trim($key[1]), 'ip' => trim($ip[1] ?? 'N/A')];
            }
        }
        ?>
        <?php if (empty($peers)): ?>
            <p>No peers configured</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr><th>Public Key</th><th>Allowed IP</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($peers as $peer): ?>
                    <tr>
                        <td class="mono"><?php echo substr($peer['key'], 0, 40); ?>...</td>
                        <td><?php echo $peer['ip']; ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Remove this peer?');" style="display:inline;">
                                <input type="hidden" name="public_key" value="<?php echo htmlspecialchars($peer['key']); ?>">
                                <button type="submit" name="delete_peer" class="btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </div>
            </table>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="card">
            <h2>📄 WireGuard Config</h2>
            <pre><?php echo htmlspecialchars($config_content); ?></pre>
        </div>
        <div class="card">
            <h2>📋 Registration Log</h2>
            <pre style="max-height:300px;"><?php echo htmlspecialchars($reg_log); ?></pre>
        </div>
    </div>
<?php endif; ?>
</div>
</body>
</html>
