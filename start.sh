#!/bin/bash

# To start psql service
echo "Starting psql service..."
sudo -u postgres pg_ctlcluster 14 main start

# To create database
echo "Creating database..."
export PGPASSWORD=$DB_PASSWORD
psql -h localhost -U postgres -f main.sql

set -e  # Exit on error

echo "Starting backend container setup..."

# Ensure the directory exists
# mkdir -p /home/netsec/Documents/NetSec/app

# Navigate to the app directory
# cd /var/www/html/app || exit 1

# Install dependencies
# composer install

# Start the server
sh /var/www/html/runserver.sh

