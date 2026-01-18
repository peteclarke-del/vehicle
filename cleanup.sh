#!/bin/bash

# Stop all containers
echo "Stopping containers..."
docker-compose down

# Remove volumes (optional - removes database data)
read -p "Do you want to remove database data? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]
then
    docker-compose down -v
    echo "Volumes removed"
fi

# Remove generated files
echo "Cleaning up generated files..."
rm -rf backend/var/cache/*
rm -rf backend/var/log/*
rm -rf frontend/node_modules
rm -rf frontend/build

echo "Cleanup complete!"
