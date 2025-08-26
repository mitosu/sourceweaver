#!/bin/bash

echo "🚀 Building Python OSINT Microservice..."

# Stop existing containers
echo "Stopping existing containers..."
docker-compose down python-osint

# Build the Python service
echo "Building Python OSINT container..."
docker-compose build python-osint

# Start the service
echo "Starting Python OSINT service..."
docker-compose up -d python-osint

# Wait for service to be ready
echo "Waiting for service to be ready..."
sleep 10

# Check health
echo "Checking service health..."
docker-compose exec -T python-osint python healthcheck.py

if [ $? -eq 0 ]; then
    echo "✅ Python OSINT Microservice is running successfully!"
    echo "📊 Service URL: http://localhost:8001"
    echo "📚 API Documentation: http://localhost:8001/docs"
    echo "🔍 Health Check: http://localhost:8001/health"
else
    echo "❌ Python OSINT Microservice failed to start properly"
    echo "Checking logs..."
    docker-compose logs python-osint
fi