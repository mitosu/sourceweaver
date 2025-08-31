#!/bin/bash

echo "🚀 Iniciando instalación del entorno de desarrollo SourceWeaver..."

# Verificaciones mínimas
command -v docker >/dev/null 2>&1 || { echo >&2 "❌ Docker no está instalado. Abortando."; exit 1; }
command -v docker-compose >/dev/null 2>&1 || { echo >&2 "❌ Docker Compose no está instalado. Abortando."; exit 1; }
command -v make >/dev/null 2>&1 || { echo >&2 "❌ Make no está instalado. Abortando."; exit 1; }

# Levanta el entorno
echo "📦 Levantando los contenedores..."
make up

# Esperar a que la base de datos esté lista
echo "⏳ Esperando a que la base de datos esté lista..."
sleep 10

# Instala dependencias PHP
echo "📥 Ejecutando composer install..."
make install

# Crea archivo .env.local si no existe
if [ ! -f .env.local ]; then
    echo "🔧 Creando archivo .env.local con configuración de entorno..."
    cat <<EOF > .env.local
APP_ENV=dev
APP_SECRET=$(openssl rand -hex 16)
DATABASE_URL="mysql://symfony:secret@db:3306/sourceweaver?serverVersion=8&charset=utf8mb4"
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@rabbitmq:5672/%2f/messages

# OSINT API Keys (configurar con tus propias claves)
GOOGLE_API_KEY=your_google_api_key
GOOGLE_CSE_ID=your_custom_search_engine_id
VIRUSTOTAL_API_KEY=your_virustotal_api_key
HAVEIBEENPWNED_API_KEY=your_hibp_api_key
EOF
else
    echo "ℹ️ El archivo .env.local ya existe. No se sobrescribirá."
fi

# Corregir permisos
echo "🔧 Corrigiendo permisos de archivos..."
make fix-permissions

# Ejecutar migraciones de base de datos
echo "🎯 Ejecutando migraciones de base de datos..."
docker-compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction

# Crear base de datos si no existe
echo "📊 Verificando base de datos..."
docker-compose exec -T php php bin/console doctrine:database:create --if-not-exists

# Cargar datos de prueba (fixtures)
echo "🌱 Cargando datos de prueba..."
docker-compose exec -T php php bin/console doctrine:fixtures:load --no-interaction

# Limpiar cache
echo "🧹 Limpiando cache de Symfony..."
docker-compose exec -T php php bin/console cache:clear

# Verificar servicios
echo "🔍 Verificando servicios..."
echo "📍 Aplicación web: http://localhost:8080"
echo "📍 Python OSINT API: http://localhost:8001"
echo "📍 RabbitMQ Management: http://localhost:15672 (guest/guest)"

# Verificar que todos los contenedores estén corriendo
echo "📦 Verificando estado de contenedores..."
docker-compose ps

# Mostrar usuarios de prueba
echo ""
echo "👤 Usuarios de prueba creados:"
echo "   Admin: admin1@example.com / adminpass1"
echo "   Admin: admin2@example.com / adminpass2"
echo "   User:  user1@example.com / userpass1"
echo "   User:  miguel@mail.com / miguelpass1"

echo ""
echo "⚠️  IMPORTANTE: Configura tus claves de API OSINT en .env.local para funcionalidad completa:"
echo "   - GOOGLE_API_KEY: Clave de Google Custom Search"
echo "   - GOOGLE_CSE_ID: ID del Custom Search Engine"
echo "   - VIRUSTOTAL_API_KEY: Clave de VirusTotal"
echo "   - HAVEIBEENPWNED_API_KEY: Clave de HaveIBeenPwned"

echo ""
echo "✅ Instalación completada exitosamente!"
echo "🌐 Accede a la aplicación en: http://localhost:8080"