#!/bin/bash
set -e

# Configure Apache port based on Render's $PORT
PORT_TO_USE="${PORT:-80}"
sed -i "s/80/${PORT_TO_USE}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf

# If no external remote DB_HOST is configured, start local MariaDB
if [ -z "$DB_HOST" ] || [ "$DB_HOST" = "localhost" ] || [ "$DB_HOST" = "127.0.0.1" ]; then
    echo "Starting local MariaDB service..."
    service mariadb start || /etc/init.d/mariadb start

    # Wait up to 20 seconds for MariaDB
    for i in {1..20}; do
        if mysqladmin ping --silent 2>/dev/null; then
            echo "MariaDB is ready!"
            break
        fi
        sleep 1
    done

    # Setup database and user
    echo "Configuring database..."
    mysql -e "CREATE DATABASE IF NOT EXISTS subh_gaming CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE USER IF NOT EXISTS 'subh_user'@'%' IDENTIFIED BY 'subh123';"
    mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'subh_user'@'%' WITH GRANT OPTION;"
    mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;"
    mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;"
    mysql -e "FLUSH PRIVILEGES;"

    # Import clean schema if tables don't exist yet
    TABLE_COUNT=$(mysql -N -s -e "SELECT count(*) FROM information_schema.tables WHERE table_schema='subh_gaming';" 2>/dev/null || echo "0")
    if [ "$TABLE_COUNT" -eq "0" ]; then
        if [ -f /var/www/html/subh_gaming_clean.sql ]; then
            echo "Importing subh_gaming_clean.sql (this may take a few seconds)..."
            mysql subh_gaming < /var/www/html/subh_gaming_clean.sql || true
            echo "Database import complete!"
        fi
    else
        echo "Database already contains $TABLE_COUNT tables, skipping import."
    fi
fi

echo "Starting Apache on port ${PORT_TO_USE}..."
exec apache2-foreground
