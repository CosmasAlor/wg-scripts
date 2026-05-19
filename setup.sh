#!/bin/bash
# WireGuard Manager Setup Script

echo "🚀 Installing WireGuard Manager..."

# Create directory
sudo mkdir -p /var/www/html

# Copy files
sudo cp onecommand.php /var/www/html/
sudo cp routeraccess.php /var/www/html/
sudo cp admin.php /var/www/html/
sudo cp complete-auto-final.php /var/www/html/
sudo cp index.html /var/www/html/
sudo cp -r api /var/www/html/
sudo mkdir -p /var/www/html/mikrotik
sudo cp mikrotik/index.php /var/www/html/mikrotik/ 2>/dev/null || true

# Set permissions
sudo chown -R www-data:www-data /var/www/html
sudo chmod 644 /var/www/html/*.php /var/www/html/*.html
sudo chmod 755 /var/www/html/api /var/www/html/mikrotik

# Setup WireGuard status cron
echo "* * * * * root /usr/bin/wg show > /var/www/html/wg-status.txt" | sudo tee /etc/cron.d/wg-status

echo "✅ Installation complete!"
echo "Access at: http://YOUR_SERVER_IP:8095/onecommand.php"
