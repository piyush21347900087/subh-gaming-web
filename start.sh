#!/bin/bash

# Configure Apache port based on Render's $PORT
PORT_TO_USE="${PORT:-80}"
sed -i "s/80/${PORT_TO_USE}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf

# Prepare MariaDB socket and data directories
mkdir -p /var/run/mysqld /var/lib/mysql
chown -R mysql:mysql /var/run/mysqld /var/lib/mysql
chmod 777 /var/run/mysqld

# If no external DB_HOST is configured, start local MariaDB
if [ -z "$DB_HOST" ] || [ "$DB_HOST" = "localhost" ] || [ "$DB_HOST" = "127.0.0.1" ]; then
    echo "Starting local MariaDB..."

    # Initialize datadir if needed
    if [ ! -d "/var/lib/mysql/mysql" ]; then
        echo "Initializing MariaDB data directory..."
        mysql_install_db --user=mysql --datadir=/var/lib/mysql > /dev/null 2>&1 || true
    fi

    # Start mysqld directly in background
    mysqld --user=mysql --skip-name-resolve &

    # Wait up to 30 seconds for MariaDB
    echo "Waiting for MariaDB to start..."
    for i in {1..30}; do
        if mysqladmin ping --silent 2>/dev/null; then
            echo "MariaDB is online!"
            break
        fi
        sleep 1
    done

    # Setup database and user
    echo "Creating subh_gaming database and permissions..."
    mysql -e "CREATE DATABASE IF NOT EXISTS subh_gaming CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
    mysql -e "CREATE USER IF NOT EXISTS 'subh_user'@'%' IDENTIFIED BY 'subh123';" 2>/dev/null || true
    mysql -e "ALTER USER 'subh_user'@'%' IDENTIFIED BY 'subh123';" 2>/dev/null || true
    mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'subh_user'@'%' WITH GRANT OPTION;" 2>/dev/null || true
    mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;" 2>/dev/null || true
    mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;" 2>/dev/null || true
    mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true

    # Import clean schema if tables don't exist yet
    TABLE_COUNT=$(mysql -N -s -e "SELECT count(*) FROM information_schema.tables WHERE table_schema='subh_gaming';" 2>/dev/null || echo "0")
    if [ "$TABLE_COUNT" -eq "0" ]; then
        if [ -f /var/www/html/subh_gaming_clean.sql ]; then
            echo "Importing subh_gaming_clean.sql..."
            mysql subh_gaming < /var/www/html/subh_gaming_clean.sql || true
            echo "Database import complete!"
        fi
    else
        echo "Database already initialized with $TABLE_COUNT tables."
    fi
fi

echo "Starting Apache on port ${PORT_TO_USE}..."
exec apache2-foreground
