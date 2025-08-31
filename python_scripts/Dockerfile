FROM python:3.11-slim

# Install system dependencies
RUN apt-get update && apt-get install -y \
    whois \
    dnsutils \
    curl \
    nmap \
    git \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Copy requirements and install Python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir --upgrade pip \
    && pip install --no-cache-dir -r requirements.txt

# Create necessary directories
RUN mkdir -p /app/scripts /app/logs /app/api

# Create non-root user for security
RUN useradd -m -u 1000 osint \
    && chown -R osint:osint /app

# Set environment variables
ENV PYTHONPATH=/app
ENV PYTHONUNBUFFERED=1

# Expose port for FastAPI
EXPOSE 8001

# Switch to non-root user
USER osint

# Default command to run FastAPI server
CMD ["python", "-m", "uvicorn", "api.main:app", "--host", "0.0.0.0", "--port", "8001", "--reload"]