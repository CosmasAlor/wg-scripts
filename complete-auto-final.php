<?php
$page_title = 'WireGuard Script Generator';
ob_start();

function generatePassword($length = 12) {
    return bin2hex(random_bytes($length / 2));
}

$generated_script = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_script'])) {
    $router_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['router_name'] ?? 'router');
    $vps_ip = $_POST['vps_ip'] ?? '2.58.80.82';
    $wg_port = $_POST['wg_port'] ?? '51820';
    $server_pub = $_POST['server_pub'] ?? '3GfNXVeht5do/inm/nly0L4QK6W5iovTau/9gCsxFkg=';
    $api_user = $_POST['api_user'] ?? 'mikhmon';
    $api_pass = !empty($_POST['api_pass']) ? $_POST['api_pass'] : generatePassword();
    $client_ip = $_POST['client_ip'] ?? '10.0.0.4';
    
    $allowed_ips = '10.0.0.0/24';
    $vps_webhook = "http://{$vps_ip}:8095/api/add-peer.php";
    
    $generated_script = "# ============================================
# WIREGUARD ENTERPRISE CONFIGURATION
# ============================================
# Router: {$router_name}
# Generated: " . date('Y-m-d H:i:s') . "
# Mode: Split Tunnel (VPN traffic only)
# ============================================

# Generate WireGuard keys
/interface wireguard key generate-file
:delay 1s

# Get generated keys
:local privateKey [/file get private.key contents]
:local publicKey [/file get public.key contents]

# Create WireGuard interface
/interface wireguard add name=\"wg_{$router_name}\" mtu=1420 private-key=\$privateKey

# Add IP address
/ip address add address={$client_ip}/24 interface=\"wg_{$router_name}\"

# Add VPS peer (Split Tunnel)
/interface wireguard peers add interface=\"wg_{$router_name}\" public-key=\"{$server_pub}\" endpoint-address={$vps_ip} endpoint-port={$wg_port} allowed-address={$allowed_ips} persistent-keepalive=25s

# Firewall rules
/ip firewall filter add chain=input protocol=udp dst-port={$wg_port} action=accept comment=\"WireGuard\"
/ip firewall filter add chain=input connection-state=established,related action=accept
/ip firewall filter add chain=input connection-state=invalid action=drop

# NAT masquerade
/ip firewall nat add chain=srcnat out-interface=wg_{$router_name} action=masquerade

# DNS configuration
/ip dns set servers=1.1.1.1,8.8.8.8 allow-remote-requests=yes

# API user for monitoring
/user group add name=\"mikhmon_api\" policy=\"api,local,!telnet,!ssh,!ftp,!winbox,!web,!rest-api\"
/user add name=\"{$api_user}\" group=mikhmon_api password=\"{$api_pass}\"
/ip service enable api
/ip service set api port=8728 address=0.0.0.0/0

# Auto-register with VPS
:put \"Registering with VPS...\"
/tool fetch url=\"{$vps_webhook}\" http-method=post http-data=\"public_key=\$publicKey&client_ip={$client_ip}&router_name={$router_name}\" keep-result=no

:delay 2s

# Display results
:put \"=========================================\"
:put \"✅ CONFIGURATION COMPLETE!\"
:put \"=========================================\"
:put \"Router: {$router_name}\"
:put \"IP: {$client_ip}\"
:put \"Public Key: \$publicKey\"
:put \"API Password: {$api_pass}\"
:put \"=========================================\"
:put \"Test: /ping 10.0.0.1\"
:put \"=========================================\"";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WireGuard Script Generator</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);min-height:100vh;padding:20px;}
        .container{max-width:1400px;margin:0 auto;}
        .header{background:rgba(15,23,42,0.8);backdrop-filter:blur(20px);border-radius:24px;padding:20px 32px;margin-bottom:32px;border:1px solid rgba(99,102,241,0.2);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;}
        .logo h1{font-size:28px;font-weight:800;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);-webkit-background-clip:text;background-clip:text;color:transparent;}
        .logo p{color:#94a3b8;font-size:13px;}
        .nav{display:flex;gap:12px;}
        .nav-btn{background:rgba(51,65,85,0.8);color:#e2e8f0;padding:10px 20px;border-radius:12px;text-decoration:none;font-weight:500;transition:all 0.3s;}
        .nav-btn:hover,.nav-btn.active{background:#667eea;transform:translateY(-2px);}
        .card{background:rgba(15,23,42,0.6);backdrop-filter:blur(10px);border-radius:24px;padding:32px;border:1px solid rgba(99,102,241,0.2);}
        .form-group{margin-bottom:20px;}
        label{display:block;margin-bottom:8px;color:#e2e8f0;font-weight:500;}
        input{width:100%;padding:12px;background:rgba(51,65,85,0.5);border:1px solid rgba(99,102,241,0.3);border-radius:12px;color:white;font-size:14px;}
        input:focus{outline:none;border-color:#667eea;}
        button{background:#667eea;color:white;border:none;padding:14px 28px;border-radius:12px;font-weight:600;cursor:pointer;width:100%;transition:all 0.3s;}
        button:hover{background:#5a67d8;transform:translateY(-2px);}
        textarea{width:100%;height:450px;font-family:monospace;font-size:12px;padding:16px;background:rgba(51,65,85,0.5);border:1px solid rgba(99,102,241,0.3);border-radius:12px;color:#e2e8f0;}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:24px;}
        .info{background:rgba(51,65,85,0.3);padding:16px;border-radius:12px;margin-top:16px;border-left:4px solid #667eea;color:#94a3b8;}
        .button-group{display:flex;gap:12px;margin-top:16px;}
        .copy-btn{background:#48bb78;}
        .download-btn{background:#f59e0b;}
        @media(max-width:768px){.row{grid-template-columns:1fr;}}
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo"><h1>⚡ WireGuard Script Generator</h1><p>Enterprise VPN Configuration Tool</p></div>
        <div class="nav"><a href="index.html" class="nav-btn">📊 Dashboard</a><a href="complete-auto-final.php" class="nav-btn active">📝 Script Generator</a><a href="admin.php" class="nav-btn">⚙️ Admin</a></div>
    </div>
    <div class="row">
        <div class="card">
            <h2 style="color:white;margin-bottom:24px;">📝 Generate Router Script</h2>
            <form method="POST">
                <div class="form-group"><label>Router Name</label><input type="text" name="router_name" placeholder="branch-office-01" required></div>
                <div class="form-group"><label>Client IP (10.0.0.x)</label><input type="text" name="client_ip" value="10.0.0.4"></div>
                <div class="form-group"><label>VPS IP Address</label><input type="text" name="vps_ip" value="2.58.80.82"></div>
                <div class="form-group"><label>VPS Public Key</label><input type="text" name="server_pub" value="3GfNXVeht5do/inm/nly0L4QK6W5iovTau/9gCsxFkg="></div>
                <div class="form-group"><label>API Username</label><input type="text" name="api_user" value="mikhmon"></div>
                <div class="form-group"><label>API Password</label><input type="text" name="api_pass" placeholder="Auto-generated"></div>
                <button type="submit" name="generate_script">🚀 Generate Configuration</button>
            </form>
        </div>
        <div class="card">
            <h2 style="color:white;margin-bottom:24px;">📋 Configuration Script</h2>
            <?php if ($generated_script): ?>
                <textarea id="scriptContent" readonly><?php echo htmlspecialchars($generated_script); ?></textarea>
                <div class="button-group">
                    <button class="copy-btn" onclick="copyToClipboard()">📋 Copy to Clipboard</button>
                    <button class="download-btn" onclick="downloadScript()">💾 Download .rsc</button>
                </div>
                <div class="info">✅ Script includes auto-registration. Router will automatically register with VPS.</div>
            <?php else: ?>
                <div class="info">💡 Fill out the form and click "Generate Configuration"</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
function copyToClipboard(){var t=document.getElementById('scriptContent');if(!t){alert('No script');return;}t.select();document.execCommand('copy');alert('✅ Script copied!');}
function downloadScript(){var t=document.getElementById('scriptContent').value;if(!t){alert('No script');return;}var a=document.createElement('a'),b=new Blob([t],{type:'text/plain'});a.href=URL.createObjectURL(b);a.download='router_config.rsc';a.click();}
</script>
</body>
</html>
