#!/bin/bash

echo "🚀 Iniciando instalación del entorno de desarrollo Symfony para IRONWHISPER..."

# Verificaciones mínimas
command -v docker >/dev/null 2>&1 || { echo >&2 "❌ Docker no está instalado. Abortando."; exit 1; }
command -v docker-compose >/dev/null 2>&1 || { echo >&2 "❌ Docker Compose no está instalado. Abortando."; exit 1; }

# Levanta el entorno
echo "📦 Levantando los contenedores..."
make up

# Instala dependencias PHP
echo "📥 Ejecutando composer install..."
make install

# Crea archivo .env.local si no existe
if [ ! -f .env.local ]; then
    echo "🔧 Creando archivo .env.local con configuración de entorno..."
    cat <<EOF > .env.local
APP_ENV=dev
APP_SECRET=$(openssl rand -hex 16)
DATABASE_URL="mysql://symfony:secret@db:3306/ironwhisper?serverVersion=8&charset=utf8mb4"
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@rabbitmq:5672/%2f/messages
EOF
else
    echo "ℹ️ El archivo .env.local ya existe. No se sobrescribirá."
fi

# (Opcional) Ejecutar migraciones y fixtures
# echo "🎯 Ejecutando migraciones..."
# make symfony doctrine:migrations:migrate --no-interaction

echo "✅ Instalación completada. Puedes acceder a la app en: http://localhost:8080"