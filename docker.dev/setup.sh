#!/bin/bash
set -e

echo "🚀 Setting up Laravel environment..."

# Setup .env
echo "⚙️  Copying .env file..."
docker compose exec -u www-data app cp .env.example .env #wsl change added -u www-data to map uid and gid

# Install dependencies
echo "📦 Installing Composer dependencies..."
docker compose exec app composer install

# Generate app key
echo "🔑 Generating application key..."
docker compose exec app php artisan key:generate

# Run migrations
echo "🗄️  Running migrations..."
docker compose exec app php artisan migrate

# Link public storage
echo "🗄️  Running migrations..."
docker compose exec app php artisan storage:link

# Fix file permissions on host (commented out for wsl)
# echo "📁 Setting file permissions..."
# ACTUAL_USER=$(logname 2>/dev/null || echo $SUDO_USER)
# sudo setfacl -R -m u:${ACTUAL_USER}:rwx .
# sudo setfacl -R -d -m u:${ACTUAL_USER}:rwx .

echo ""
echo "✅ Setup complete!"
echo "🌐 App: http://localhost:8080"
echo "📧 Mailpit: http://localhost:8025"
echo "🗃️ Redis: localhost:6379"
echo "💾 MySQL: localhost:3306"

#######################
# ====================#
#######################

# git clone <your-repo>
# cd <your-repo>

# # Set ACL for permissions
# sudo setfacl -R -m u:$USER:rwx . 6
# sudo setfacl -R -d -m u:$USER:rwx . 7

# # Start containers
# docker-compose up -d --build

# # Laravel setup
# docker compose exec app composer install - already inside
# docker compose exec app cp .env.example .env 1
# docker compose exec app php artisan key:generate 2
# docker compose exec app php artisan migrate 3
# docker compose exec app php artisan config:clear 4
# docker compose exec app supervisorctl restart laravel-worker:laravel-worker_00 5


# # Test
# docker compose exec app php artisan tinker
# See if it all comes up clean! \Mail::to('test@example.com')->queue(new \App\Mail\TestMail);
# = "UuHn4OFHRiWc7WLM3kXVtzJuzp43ZAiV"mailpit won't show email


# steps to execute
# git clone git@github.com:parthmp/deskmint-backend.git
# cd deskmint-backend

# docker compose up --build

# docker compose exec app cp .env.example .env
# docker compose exec app composer install
# docker compose exec app php artisan key:generate 
# docker compose exec app php artisan migrate
# sudo setfacl -R -m u:$USER:rwx .
# sudo setfacl -R -d -m u:$USER:rwx . 