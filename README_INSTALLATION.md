# SourceWeaver - Guía de Instalación

## Instalación Rápida

### Prerrequisitos

Asegúrate de tener instalado:

- **Docker** (versión 20.10 o superior)
- **Docker Compose** (versión 1.29 o superior)  
- **Make** (para comandos de gestión)
- **Git** (para clonar el repositorio)

### Instalación Automática

1. **Clona el repositorio:**
```bash
git clone https://github.com/sourceweaver/sourceweaver.git
cd sourceweaver
```

2. **Ejecuta el script de instalación:**
```bash
./install.sh
```

El script realizará automáticamente:
- ✅ Verificación de dependencias
- ✅ Levantado de contenedores Docker
- ✅ Instalación de dependencias PHP
- ✅ Configuración del entorno (.env.local)
- ✅ Ejecución de migraciones de base de datos
- ✅ Carga de datos de prueba (fixtures)
- ✅ Configuración de permisos
- ✅ Limpieza de cache

### Acceso a la Aplicación

Una vez completada la instalación:

- **Aplicación Web**: http://localhost:8080
- **RabbitMQ Management**: http://localhost:15672 (usuario: guest, contraseña: guest)

### Usuarios de Prueba

El sistema incluye usuarios preconfigurados:

| Email | Contraseña | Rol |
|-------|------------|-----|
| admin1@example.com | adminpass1 | Admin |
| admin2@example.com | adminpass2 | Admin |
| user1@example.com | userpass1 | Usuario |
| miguel@mail.com | miguelpass1 | Usuario |

## Configuración de APIs OSINT

Para funcionalidad completa, configura tus claves de API en `.env.local`:

```bash
# APIs OSINT requeridas
GOOGLE_API_KEY=tu_clave_google_api
GOOGLE_CSE_ID=tu_custom_search_engine_id
VIRUSTOTAL_API_KEY=tu_clave_virustotal
HAVEIBEENPWNED_API_KEY=tu_clave_hibp
```

### Obtener Claves de API

#### Google Custom Search
1. Visita [Google Cloud Console](https://console.cloud.google.com/)
2. Crea un proyecto y habilita "Custom Search API"
3. Genera una clave API
4. Configura un Custom Search Engine en [CSE Panel](https://cse.google.com/)

#### VirusTotal
1. Registrate en [VirusTotal](https://www.virustotal.com/)
2. Ve a tu perfil → API Key
3. Copia tu clave API

#### HaveIBeenPwned
1. Visita [HIBP API](https://haveibeenpwned.com/API/Key)
2. Solicita una clave API (de pago)

## Comandos de Gestión

### Docker
```bash
make up          # Levantar contenedores
make down        # Detener contenedores  
make restart     # Reiniciar contenedores
make logs        # Ver logs de contenedores
```

### Symfony
```bash
make symfony cmd="cache:clear"              # Limpiar cache
make symfony cmd="doctrine:migrations:diff" # Crear migración
make symfony cmd="doctrine:fixtures:load"   # Cargar fixtures
```

### Base de Datos
```bash
make dbshell     # Acceder a MySQL shell
```

### Desarrollo
```bash
make bash        # Acceder al contenedor PHP
make fix-permissions  # Corregir permisos de archivos
```

## Solución de Problemas

### Error: "Port already in use"
```bash
# Verifica puertos ocupados
netstat -tulpn | grep :8080
netstat -tulpn | grep :3306

# Detén servicios conflictivos o cambia puertos en docker-compose.yml
```

### Error: "Permission denied"
```bash
# Corrige permisos
make fix-permissions
sudo chown -R $(id -u):$(id -g) .
```

### Error: "Database connection failed"
```bash
# Reinicia contenedores
make restart

# Verifica configuración de base de datos
cat .env.local | grep DATABASE_URL
```

### Reinstalación Completa
```bash
# Detener y limpiar contenedores
make down
docker system prune -a

# Eliminar datos persistentes
sudo rm -rf var/

# Ejecutar instalación nuevamente
./install.sh
```

## Estructura del Proyecto

```
sourceweaver/
├── docker-compose.yml    # Configuración Docker
├── Dockerfile           # Imagen PHP personalizada
├── Makefile            # Comandos de gestión
├── install.sh          # Script de instalación
├── src/                # Código fuente Symfony
├── templates/          # Templates Twig
├── migrations/         # Migraciones de BD
├── python-osint/       # Microservicio Python FastAPI
├── public/            # Assets públicos
└── var/               # Cache y logs
```

## URLs de Servicios

| Servicio | URL | Credenciales |
|----------|-----|--------------|
| Aplicación Web | http://localhost:8080 | Ver usuarios de prueba |
| Python OSINT API | http://localhost:8001 | - |
| RabbitMQ Management | http://localhost:15672 | guest / guest |
| MySQL Database | localhost:3306 | symfony / secret |

## Desarrollo

### Añadir Nuevas Funcionalidades

1. **Backend Symfony:**
   - Controladores: `src/Controller/`
   - Servicios: `src/Service/`
   - Entidades: `src/Entity/`

2. **OSINT Python:**
   - APIs: `python-osint/api/`
   - Modelos: `python-osint/models/`

3. **Frontend:**
   - Templates: `templates/`
   - Assets: `public/`

### Testing

```bash
# Ejecutar tests PHP
make symfony cmd="test"

# Tests Python
docker-compose exec python-osint pytest
```