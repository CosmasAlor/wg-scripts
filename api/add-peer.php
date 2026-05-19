<?php
$log_file = '/var/www/html/api/registered_peers.log';
$names_file = '/var/www/html/api/router_names.json';

$public_key = $_POST['public_key'] ?? $_GET['public_key'] ?? '';
$client_ip = $_POST['client_ip'] ?? $_GET['client_ip'] ?? '';
$router_name = $_POST['router_name'] ?? $_GET['router_name'] ?? '';
$timestamp = date('Y-m-d H:i:s');

if (empty($public_key)) {
    http_response_code(400);
    echo "❌ Missing public_key";
    exit;
}

// Store router name mapping
$names = [];
if (file_exists($names_file)) {
    $names = json_decode(file_get_contents($names_file), true) ?: [];
}
$names[$public_key] = [
    'name' => $router_name ?: 'Unknown',
    'ip' => $client_ip,
    'registered_at' => $timestamp
];
file_put_contents($names_file, json_encode($names, JSON_PRETTY_PRINT));

// Log registration
$log_entry = "[$timestamp] Router: $router_name | IP: $client_ip | Key: $public_key\n";
file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

echo "=========================================\n";
echo "✅ REGISTRATION RECEIVED!\n";
echo "=========================================\n";
echo "Router: $router_name\n";
echo "Client IP: $client_ip\n";
echo "Public Key: $public_key\n";
echo "=========================================\n";
?>
