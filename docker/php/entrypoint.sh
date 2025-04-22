#!/bin/sh

# Verificar versión de PHP
REQUIRED_PHP_VERSION="8.2"
CURRENT_PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')

if [ "$CURRENT_PHP_VERSION" != "$REQUIRED_PHP_VERSION" ]; then
  echo "❌ ERROR: Este contenedor requiere PHP $REQUIRED_PHP_VERSION pero se está ejecutando PHP $CURRENT_PHP_VERSION"
  exit 1
fi

# Corregir permisos en var y log
echo "🔧 Corrigiendo permisos en var/ y log/..."
chown -R www-data:www-data /var/www/html/var

# Iniciar PHP-FPM
echo "🚀 Iniciando PHP-FPM..."
exec php-fpm