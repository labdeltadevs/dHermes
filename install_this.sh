#!/bin/bash

# Detener el script si ocurre algún error grave
set -e

echo "🚀 Iniciando configuración de Laravel en SiteGround..."

# 1. Crear estructura de carpetas para evitar errores de Compiler/Cache
echo "📁 Creando estructura de carpetas en storage y bootstrap/cache..."
mkdir -p storage/framework/views \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/logs \
         bootstrap/cache

# 2. Asignar permisos correctos de lectura y escritura
echo "🔒 Configurando permisos (775)..."
chmod -R 775 storage bootstrap/cache

# 3. Copiar archivo .env si no existe
if [ ! -f .env ]; then
    echo "📄 Copiando .env.example a .env..."
    cp .env.example .env
else
    echo "ℹ️ El archivo .env ya existe."
fi

# 4. Instalar dependencias de PHP con Composer
echo "📦 Instalando dependencias de Composer (Producción)..."
composer install --optimize-autoloader --no-dev

# 5. Generar la clave de la aplicación
echo "🔑 Generando clave de la aplicación (APP_KEY)..."
php artisan key:generate --force

# 6. Configuración del enlace simbólico para la web en SiteGround
echo "🔗 Enlazando carpeta 'public' con 'public_html'..."
rm -rf public_html
ln -s public public_html

# 7. Crear el enlace simbólico de almacenamiento para subida de archivos
echo "🔗 Generando storage:link..."
php artisan storage:link --force 2>/dev/null || true

# 8. Compilar frontend si existe package.json
if [ -f package.json ]; then
    echo "🎨 Instalando paquetes de Node y compilando assets..."
    npm install
fi

# 9. Limpiar caché del framework
echo "🧹 Limpiando cachés..."
php artisan optimize:clear

echo "--------------------------------------------------------"
echo "✅ ¡Instalación básica completada!"
echo "👉 Siguiente paso:"
echo "   1. Configura tu base de datos en el archivo .env (nano .env)"
echo "   2. Ejecuta: php artisan migrate --seed"
echo "--------------------------------------------------------"
