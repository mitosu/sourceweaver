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

# Copy application code
COPY api/ /app/api/
COPY scripts/ /app/scripts/

# Make scripts executable
RUN find /app/scripts -name "*.py" -exec chmod +x {} \;

# Create non-root user for security
RUN useradd -m -u 1000 osint \
    && chown -R osint:osint /app
USER osint

# Health check script
COPY --chown=osint:osint healthcheck.py /app/
RUN chmod +x /app/healthcheck.py

# Expose port for FastAPI
EXPOSE 8001

# Set environment variables
ENV PYTHONPATH=/app
ENV PYTHONUNBUFFERED=1

# Default command to run FastAPI server
CMD ["python", "-m", "uvicorn", "api.main:app", "--host", "0.0.0.0", "--port", "8001", "--reload"]