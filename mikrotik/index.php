<?php
// This file is the installer endpoint
$router_id = $_GET['id'] ?? '';
if (!$router_id) {
    http_response_code(404);
    die("Invalid request");
}

$config_file = '/var/www/html/api/router_configs/' . $router_id . '.json';
if (!file_exists($config_file)) {
    http_response_code(404);
    die("Configuration not found");
}

$config = json_decode(file_get_contents($config_file), true);
$router_name = $config['name'];
$client_ip = $config['client_ip'] ?? '10.0.0.' . rand(10, 200);

$script = "# WireGuard Configuration for $router_name
/interface wireguard key generate-file
:delay 1s
:local privateKey [/file get private.key contents]
:local publicKey [/file get public.key contents]
/interface wireguard add name=\"wg_$router_name\" mtu=1420 private-key=\$privateKey
/ip address add address=$client_ip/24 interface=\"wg_$router_name\"
/interface wireguard peers add interface=\"wg_$router_name\" public-key=\"3GfNXVeht5do/inm/nly0L4QK6W5iovTau/9gCsxFkg=\" endpoint-address=2.58.80.82 endpoint-port=51820 allowed-address=10.0.0.0/24 persistent-keepalive=25s
/ip firewall filter add chain=input protocol=udp dst-port=51820 action=accept
/ip firewall nat add chain=srcnat out-interface=wg_$router_name action=masquerade
/ip dns set servers=1.1.1.1,8.8.8.8
/user set admin password=cosmas
/user group add name=mikhmon_api policy=api,local
/user add name=mikhmon group=mikhmon_api password=mikhmon
/ip service enable api
/ip service set api port=8728 address=0.0.0.0/0
/tool fetch url=\"http://2.58.80.82:8095/api/add-peer.php\" http-method=post http-data=\"public_key=\$publicKey&client_ip=$client_ip&router_name=$router_name\" keep-result=no
:put \"✅ Configuration complete! IP: $client_ip\"";
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="mikrotik-commands.rsc"');
echo $script;
?>
