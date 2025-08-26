FROM python:3.11-slim

# Install system dependencies
RUN apt-get update && apt-get install -y \
    whois \
    dnsutils \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Copy requirements and install Python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy scripts
COPY . /app/scripts/
COPY ../python_api /app/api/

# Make scripts executable
RUN chmod +x /app/scripts/*.py

# Create non-root user
RUN useradd -m -u 1000 osint && chown -R osint:osint /app
USER osint

# Expose port for FastAPI
EXPOSE 8001

# Default command to run FastAPI server
CMD ["python", "/app/api/main.py"]