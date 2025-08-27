# VirusTotal Integration

Esta documentación describe cómo integrar y usar VirusTotal con nuestra API de OSINT.

## 🔧 Configuración

### 1. Obtener API Key

1. Ve a [VirusTotal](https://www.virustotal.com/gui/my-apikey)
2. Crea una cuenta o inicia sesión
3. Copia tu API key

### 2. Configurar Variables de Entorno

Ejecuta el script de configuración:

```bash
python scripts/setup_virustotal.py
```

O configura manualmente:

```bash
export VIRUSTOTAL_API_KEY="tu_api_key_aqui"
```

### 3. Verificar Configuración

Ejecuta las pruebas de integración:

```bash
python tests/test_virustotal_integration.py
```

## 📋 Límites de Rate (Cuenta Gratuita)

- **4 requests por minuto**
- **500 requests por día**
- **15,500 requests por mes**

⚠️ El cliente implementa rate limiting automático para evitar exceder los límites.

## 🌐 Endpoints Disponibles

### Health Check
```http
GET /api/v1/virustotal/health
```

### Análisis de Archivos

#### Subir archivo para análisis
```http
POST /api/v1/virustotal/files/analyze
Content-Type: multipart/form-data

file: <archivo_binario>
password: <opcional_si_esta_encriptado>
```

#### Obtener reporte de archivo por hash
```http
GET /api/v1/virustotal/files/{hash}
```

#### Obtener comportamiento del archivo (sandbox)
```http
GET /api/v1/virustotal/files/{hash}/behaviours
```

### Análisis de URLs

#### Enviar URL para análisis
```http
POST /api/v1/virustotal/urls/analyze
Content-Type: application/json

{
  "url": "https://example.com"
}
```

#### Obtener reporte de URL
```http
GET /api/v1/virustotal/urls/{url_id}
```

#### Análisis rápido de URL (conveniencia)
```http
POST /api/v1/virustotal/urls/analyze-by-url?url=https://example.com
```

### Análisis de Dominios

#### Obtener información de dominio
```http
GET /api/v1/virustotal/domains/{domain}
```

#### Obtener subdominios
```http
GET /api/v1/virustotal/domains/{domain}/subdomains?limit=40
```

#### Obtener resoluciones DNS
```http
GET /api/v1/virustotal/domains/{domain}/resolutions?limit=40
```

### Análisis de IPs

#### Obtener información de IP
```http
GET /api/v1/virustotal/ip/{ip_address}
```

#### Obtener resoluciones DNS de IP
```http
GET /api/v1/virustotal/ip/{ip_address}/resolutions?limit=40
```

### Búsqueda

```http
POST /api/v1/virustotal/search
Content-Type: application/json

{
  "query": "type:ip-address country:US",
  "limit": 10
}
```

### Análisis en Lote

```http
POST /api/v1/virustotal/bulk/hashes
Content-Type: application/json

["hash1", "hash2", "hash3"]
```

### Estadísticas

```http
GET /api/v1/virustotal/stats
```

## 📊 Ejemplos de Respuestas

### Análisis de IP
```json
{
  "resource_type": "ip",
  "resource_id": "8.8.8.8",
  "status": "completed",
  "stats": {
    "harmless": 75,
    "malicious": 0,
    "suspicious": 0,
    "undetected": 10,
    "timeout": 0
  },
  "reputation": 10,
  "analysis_date": "2024-01-15T10:30:00",
  "permalink": "https://www.virustotal.com/gui/ip-address/8.8.8.8"
}
```

### Análisis de Dominio
```json
{
  "resource_type": "domain",
  "resource_id": "google.com",
  "status": "completed",
  "stats": {
    "harmless": 80,
    "malicious": 0,
    "suspicious": 0,
    "undetected": 5,
    "timeout": 0
  },
  "attributes": {
    "categories": {
      "Bitdefender": "searchengines",
      "Forcepoint ThreatSeeker": "search engines"
    },
    "reputation": 1337
  }
}
```

## 🔍 Queries de Búsqueda

VirusTotal soporta queries avanzadas:

```
type:ip-address country:US
type:domain registrar:godaddy
size:100MB+
engines:5+
positives:10+
submitter:researcher
```

## 🛡️ Seguridad

### Mejores Prácticas

1. **Nunca commitear API keys** al control de versiones
2. **Usar variables de entorno** para API keys
3. **Rotar API keys** periodicamente
4. **Monitorear uso** en el dashboard de VirusTotal
5. **Usar API keys diferentes** para diferentes entornos

### Manejo Seguro de Archivos

- Los archivos se procesan temporalmente
- Se eliminan automáticamente después del análisis
- Límite de 32MB por archivo
- Soporte para archivos con contraseña

## 🚨 Manejo de Errores

### Códigos de Error Comunes

- **401**: API key inválida
- **404**: Recurso no encontrado
- **429**: Rate limit excedido
- **413**: Archivo demasiado grande

### Ejemplo de Respuesta de Error
```json
{
  "detail": "VirusTotal API error: Invalid API key"
}
```

## 📈 Monitoreo

### Métricas Disponibles

```http
GET /api/v1/virustotal/stats
```

```json
{
  "total_requests": 150,
  "daily_requests": 45,
  "rate_limit": {
    "requests_per_minute": 4,
    "requests_per_day": 500,
    "daily_usage_percentage": 9.0
  }
}
```

## 🔄 Integración con Symfony

Los resultados de VirusTotal se pueden integrar con la aplicación Symfony usando los endpoints de la API:

```php
// En tu servicio de análisis
$response = $this->httpClient->request('GET', 
    'http://python-osint:8001/api/v1/virustotal/ip/8.8.8.8'
);

$data = $response->toArray();
```

## 🧪 Testing

### Hashes de Prueba Conocidos

- **Archivo limpio**: `44d88612fea8a8f36de82e1278abb02f`
- **Archivo malicioso**: Usa VirusTotal para encontrar samples

### IPs y Dominios de Prueba

- **IP limpia**: `8.8.8.8` (Google DNS)
- **Dominio limpio**: `google.com`

## 📞 Soporte

- **Documentación oficial**: https://docs.virustotal.com/reference/overview
- **API Reference**: https://developers.virustotal.com/reference
- **Community**: https://www.virustotal.com/gui/join-us

## 🔗 Enlaces Útiles

- [VirusTotal Web Interface](https://www.virustotal.com/)
- [API Documentation](https://docs.virustotal.com/reference/overview)
- [My API Key](https://www.virustotal.com/gui/my-apikey)
- [Community](https://www.virustotal.com/gui/join-us)