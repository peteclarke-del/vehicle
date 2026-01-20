#!/bin/bash

echo "Setting up Vehicle Management System..."

# Create JWT directory and generate keys
echo "Generating JWT keys..."
mkdir -p backend/config/jwt
ssh-keygen -t rsa -b 4096 -m PEM -f backend/config/jwt/private.pem -N "changeme"
openssl rsa -in backend/config/jwt/private.pem -pubout -outform PEM -out backend/config/jwt/public.pem

echo "Starting Docker containers..."
docker-compose up -d

echo "Waiting for MySQL to be ready..."
sleep 10

echo "Installing Composer dependencies..."
docker exec vehicle_php composer install

echo "Creating database..."
docker exec vehicle_php bin/console doctrine:database:create --if-not-exists

echo "Running migrations..."
docker exec vehicle_php bin/console doctrine:migrations:migrate --no-interaction

echo "Loading fixtures..."
if [ "$FORCE_FIXTURES" != "1" ] && [ "$APP_ENV" != "test" ]; then
	if [ -t 0 ]; then
		read -p "WARNING: Loading fixtures will DESTROY dev DB data. Type 'yes' to proceed: " ans
		if [ "$ans" != "yes" ]; then
			echo "Aborting fixture load."
			exit 1
		fi
	else
		echo "Refusing to run fixtures non-interactively without FORCE_FIXTURES=1 or APP_ENV=test"
		exit 1
	fi
fi

docker exec vehicle_php bin/console doctrine:fixtures:load --no-interaction

echo "Installing frontend dependencies..."
cd frontend && npm install && cd ..

echo "Setup complete!"
echo ""
echo "Access the application at:"
echo "  Frontend: http://localhost:3000"
echo "  Backend API: http://localhost:8080/api"
echo ""
echo "To start the frontend development server:"
echo "  cd frontend && npm start"
