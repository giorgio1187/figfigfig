# ANEXO: Procedimiento de Respaldo de Base de Datos (BD)
## Sistema F.I.G - Funcionamiento Íntegro Gastronómico

**Versión:** 1.0  
**Última actualización:** Mayo 2026  
**Estado:** Documento Oficial de Operación

---

## 1. ALCANCE Y FRECUENCIA

### 1.1 Alcance
Este procedimiento de respaldo aplica a la **Base de Datos PostgreSQL** del sistema F.I.G (Funcionamiento Íntegro Gastronómico) que contiene:
- Datos de autenticación de usuarios (credentials encriptadas)
- Inventario de ingredientes
- Catálogo de productos y recetas
- Órdenes de comida y seguimiento
- Gestión de mesas del restaurante
- Configuración administrativa

### 1.2 Frecuencia de Respaldos

| Tipo de Respaldo | Frecuencia | Hora de Ejecución | Retención |
|---|---|---|---|
| **Respaldo Completo (Full Backup)** | Diario | 02:00 AM (Zona Horaria del Servidor) | 7 días |
| **Respaldo Incremental** | Cada 6 horas | 02:00, 08:00, 14:00, 20:00 | 48 horas |
| **Respaldo Semanal Completo** | Una vez por semana | Domingo 03:00 AM | 4 semanas |
| **Respaldo Mensual** | Una vez por mes | Primer día del mes 03:00 AM | 12 meses |

**Objetivo RTO/RPO:**
- **RTO (Recovery Time Objective):** Máximo 2 horas de recuperación
- **RPO (Recovery Point Objective):** Máximo 6 horas de pérdida de datos

---

## 2. HERRAMIENTA UTILIZADA

### 2.1 Herramienta Principal: `pg_dump`

**Especificación técnica:**
```bash
PostgreSQL Client Tools (versión >= 14.0)
```

**Justificación de elección:**
- Nativa de PostgreSQL, sin dependencias externas adicionales
- Soporta respaldos en formato SQL y binario
- Permite compresión integrada
- Facilita restauración rápida y verificación

### 2.2 Herramientas Complementarias

#### a) **Compresión: `gzip`**
- Reduce el tamaño del respaldo a 60-70% del original
- Comando: `pg_dump | gzip > backup.sql.gz`

#### b) **Sincronización en la Nube: `rclone`**
- Sincroniza respaldos locales con almacenamiento remoto
- Compatible con: AWS S3, Google Cloud Storage, Azure Blob Storage
- Instalación: `sudo apt-get install rclone` (Linux) o `brew install rclone` (macOS)

#### c) **Automatización: `cron` (Linux) o Tareas Programadas (Windows Server)**
- Ejecuta scripts de respaldo sin intervención humana

### 2.3 Versiones Recomendadas

```
PostgreSQL Client Tools: 14.x o superior
gzip: cualquier versión estándar
rclone: v1.65+
curl: para notificaciones (opcional)
```

---

## 3. RUTA DE ALMACENAMIENTO (LOCAL Y REMOTA)

### 3.1 Almacenamiento Local

#### Servidor Linux/Unix (recomendado para producción)
```
Ruta Local Principal:    /var/backups/fig-database/
Ruta de Respaldos:       /var/backups/fig-database/daily/
Ruta Archivos Históricos: /var/backups/fig-database/archive/
Ruta de Scripts:         /usr/local/bin/fig-backup-scripts/
```

#### Servidor Windows
```
Ruta Local Principal:    C:\Backups\FIG-Database\
Ruta de Respaldos:       C:\Backups\FIG-Database\Daily\
Ruta Archivos Históricos: C:\Backups\FIG-Database\Archive\
Ruta de Scripts:         C:\Scripts\fig-backup-scripts\
```

#### Estructura de directorios recomendada
```
/var/backups/fig-database/
├── daily/
│   ├── fig_backup_2026-05-22_02-00.sql.gz
│   ├── fig_backup_2026-05-22_08-00.sql.gz
│   └── fig_backup_2026-05-23_02-00.sql.gz
├── weekly/
│   └── fig_backup_weekly_2026-05-19.sql.gz
├── monthly/
│   └── fig_backup_monthly_2026-05-01.sql.gz
├── archive/
│   └── fig_backup_2026-04-*.sql.gz (archivos históricos)
└── logs/
    ├── backup_2026-05-22.log
    └── backup_errors_2026-05.log
```

### 3.2 Almacenamiento Remoto (Requerido para Producción)

#### Opción A: **AWS S3** (Recomendado)
```
S3 Bucket Name:        fig-gastronomico-backups
S3 Region:             us-east-1 (o según localización)
S3 Path Structure:
  s3://fig-gastronomico-backups/
  ├── daily/
  ├── weekly/
  ├── monthly/
  └── archive/

Clase de Almacenamiento: GLACIER (para archivos > 30 días)
Versionado:             Habilitado
Encriptación:           AES-256 (predeterminada)
```

#### Opción B: **Google Cloud Storage**
```
Bucket Name:           gs://fig-backups-gcs
Location:              us (Multi-región recomendado)
Storage Class:         STANDARD (últimos 30 días) → COLDLINE (> 30 días)
Encriptación:          Google-managed keys (CMEK opcional)
```

#### Opción C: **Azure Blob Storage**
```
Storage Account:       figdatabasebackups
Container Name:        postgresql-backups
Redundancy:            GRS (Geo-Redundant Storage)
Tier:                  Cool (para archivos > 30 días)
Encriptación:          Microsoft-managed keys
```

#### Opción D: **Servidor NAS Externo (Red Local)**
```
Protocolo:             NFS o SMB/CIFS
Servidor NAS:          192.168.1.50 (Ejemplo)
Ruta Compartida:       /mnt/nas/fig-backups/
Autenticación:         Usuario dedicado con permisos específicos
Replicación:           Habilitada en el NAS (RAID 6 mínimo)
```

**Nota Crítica:** Para producción, es **obligatorio** tener respaldos en almacenamiento remoto geográficamente distribuido.

---

## 4. AUTOMATIZACIÓN

### 4.1 Script de Respaldo Principal (`backup_fig_database.sh`)

#### Ubicación: `/usr/local/bin/fig-backup-scripts/backup_fig_database.sh`

```bash
#!/bin/bash

###############################################################################
# Script de Respaldo Automático - Base de Datos F.I.G
# Descripción: Realiza respaldos diarios y semanales de PostgreSQL
# Versión: 1.0
# Último update: 05/2026
###############################################################################

# ============================================================================
# CONFIGURACIÓN
# ============================================================================

# Variables de Entorno
export PGPASSWORD="${DB_PASSWORD}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DB_USER="${DB_USER:-postgres}"
DB_NAME="${DB_NAME:-fig_gastronomico}"

# Rutas de Almacenamiento
BACKUP_BASE_DIR="/var/backups/fig-database"
BACKUP_DAILY_DIR="${BACKUP_BASE_DIR}/daily"
BACKUP_WEEKLY_DIR="${BACKUP_BASE_DIR}/weekly"
BACKUP_MONTHLY_DIR="${BACKUP_BASE_DIR}/monthly"
ARCHIVE_DIR="${BACKUP_BASE_DIR}/archive"
LOG_DIR="${BACKUP_BASE_DIR}/logs"

# Configuración de Cloud (AWS S3)
S3_BUCKET="fig-gastronomico-backups"
S3_REGION="us-east-1"
RCLONE_CONFIG="s3_fig"  # Nombre de configuración en rclone

# Correos de Notificación
ADMIN_EMAIL="admin@fig-restaurante.com"
NOTIFICATION_EMAIL="devops@fig-restaurante.com"

# Fechas
BACKUP_DATE=$(date +%Y-%m-%d)
BACKUP_TIME=$(date +%H-%M)
BACKUP_TIMESTAMP=$(date +%Y-%m-%d_%H-%M)
DAY_OF_WEEK=$(date +%u)  # 1=Lunes, 7=Domingo
DAY_OF_MONTH=$(date +%d)

# ============================================================================
# FUNCIONES
# ============================================================================

# Crear directorios si no existen
create_directories() {
    mkdir -p "${BACKUP_DAILY_DIR}"
    mkdir -p "${BACKUP_WEEKLY_DIR}"
    mkdir -p "${BACKUP_MONTHLY_DIR}"
    mkdir -p "${ARCHIVE_DIR}"
    mkdir -p "${LOG_DIR}"
    chmod 700 "${BACKUP_BASE_DIR}"  # Solo root puede acceder
}

# Función de logging
log_message() {
    local level=$1
    local message=$2
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[${timestamp}] [${level}] ${message}" | tee -a "${LOG_DIR}/backup_${BACKUP_DATE}.log"
}

# Respaldo Diario (Completo)
daily_backup() {
    log_message "INFO" "Iniciando respaldo diario completo..."
    
    local backup_file="${BACKUP_DAILY_DIR}/fig_backup_${BACKUP_TIMESTAMP}.sql.gz"
    
    if pg_dump -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" \
               -d "${DB_NAME}" --verbose 2>&1 | \
       gzip > "${backup_file}"; then
        
        local size=$(du -h "${backup_file}" | cut -f1)
        log_message "SUCCESS" "Respaldo diario completado: ${backup_file} (Tamaño: ${size})"
        return 0
    else
        log_message "ERROR" "Falló el respaldo diario"
        return 1
    fi
}

# Respaldo Semanal (Domingo a las 03:00 AM)
weekly_backup() {
    if [ "$DAY_OF_WEEK" = "7" ]; then
        log_message "INFO" "Iniciando respaldo semanal..."
        
        local backup_file="${BACKUP_WEEKLY_DIR}/fig_backup_weekly_${BACKUP_DATE}.sql.gz"
        
        if pg_dump -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" \
                   -d "${DB_NAME}" --verbose 2>&1 | \
           gzip > "${backup_file}"; then
            
            local size=$(du -h "${backup_file}" | cut -f1)
            log_message "SUCCESS" "Respaldo semanal completado: ${backup_file} (Tamaño: ${size})"
            return 0
        else
            log_message "ERROR" "Falló el respaldo semanal"
            return 1
        fi
    fi
}

# Respaldo Mensual (Día 1 del mes a las 03:00 AM)
monthly_backup() {
    if [ "$DAY_OF_MONTH" = "01" ]; then
        log_message "INFO" "Iniciando respaldo mensual..."
        
        local month=$(date +%Y-%m)
        local backup_file="${BACKUP_MONTHLY_DIR}/fig_backup_monthly_${month}.sql.gz"
        
        if pg_dump -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" \
                   -d "${DB_NAME}" --verbose 2>&1 | \
           gzip > "${backup_file}"; then
            
            local size=$(du -h "${backup_file}" | cut -f1)
            log_message "SUCCESS" "Respaldo mensual completado: ${backup_file} (Tamaño: ${size})"
            return 0
        else
            log_message "ERROR" "Falló el respaldo mensual"
            return 1
        fi
    fi
}

# Subir a la nube (AWS S3)
sync_to_cloud() {
    log_message "INFO" "Sincronizando respaldos a AWS S3..."
    
    # Verificar que rclone esté configurado
    if ! rclone config get "${RCLONE_CONFIG}" &>/dev/null; then
        log_message "ERROR" "Configuración de rclone '${RCLONE_CONFIG}' no encontrada"
        return 1
    fi
    
    # Sincronizar directorios
    if rclone sync "${BACKUP_DAILY_DIR}" \
                  "${RCLONE_CONFIG}:${S3_BUCKET}/daily/" \
                  --log-file="${LOG_DIR}/rclone_sync_${BACKUP_DATE}.log" \
                  --verbose; then
        log_message "SUCCESS" "Sincronización a S3 completada"
        return 0
    else
        log_message "ERROR" "Falló la sincronización a S3"
        return 1
    fi
}

# Archivar respaldos antiguos (> 30 días)
archive_old_backups() {
    log_message "INFO" "Archivando respaldos antiguos..."
    
    # Mover respaldos diarios de más de 30 días
    find "${BACKUP_DAILY_DIR}" -maxdepth 1 -type f -mtime +30 | while read -r file; do
        if mv "$file" "${ARCHIVE_DIR}/"; then
            log_message "INFO" "Archivado: $(basename $file)"
        fi
    done
    
    # Borrar archivos de más de 1 año del directorio archive (mantener en S3)
    find "${ARCHIVE_DIR}" -maxdepth 1 -type f -mtime +365 -delete
    
    log_message "SUCCESS" "Archivado completado"
}

# Verificar integridad del respaldo
verify_backup() {
    log_message "INFO" "Verificando integridad del respaldo..."
    
    local latest_backup=$(ls -t "${BACKUP_DAILY_DIR}"/*.sql.gz | head -1)
    
    if [ -z "$latest_backup" ]; then
        log_message "ERROR" "No se encontró respaldo para verificar"
        return 1
    fi
    
    # Intentar listar contenido del archivo gzip
    if gzip -t "$latest_backup" 2>/dev/null; then
        log_message "SUCCESS" "Integridad del respaldo verificada: $latest_backup"
        return 0
    else
        log_message "ERROR" "Respaldo corrupto: $latest_backup"
        return 1
    fi
}

# Enviar notificación por correo
send_email_notification() {
    local status=$1
    local message=$2
    
    local subject="FIG Database Backup - Status: ${status}"
    
    echo -e "Timestamp: $(date)\n\nStatus: ${status}\n\n${message}\n\nBackup Dir: ${BACKUP_DAILY_DIR}" | \
    mail -s "${subject}" "${ADMIN_EMAIL}"
    
    log_message "INFO" "Notificación enviada a ${ADMIN_EMAIL}"
}

# ============================================================================
# EJECUCIÓN PRINCIPAL
# ============================================================================

main() {
    log_message "INFO" "=========================================="
    log_message "INFO" "Iniciando proceso de respaldo de BD"
    log_message "INFO" "Timestamp: ${BACKUP_TIMESTAMP}"
    log_message "INFO" "=========================================="
    
    # Crear directorios necesarios
    create_directories
    
    # Ejecutar respaldos
    daily_backup
    DAILY_RESULT=$?
    
    weekly_backup
    WEEKLY_RESULT=$?
    
    monthly_backup
    MONTHLY_RESULT=$?
    
    # Verificar integridad
    verify_backup
    VERIFY_RESULT=$?
    
    # Sincronizar a la nube
    sync_to_cloud
    SYNC_RESULT=$?
    
    # Archivar antiguos
    archive_old_backups
    
    # Determinar estado general
    if [ $DAILY_RESULT -eq 0 ] && [ $VERIFY_RESULT -eq 0 ]; then
        log_message "SUCCESS" "Proceso de respaldo completado exitosamente"
        send_email_notification "SUCCESS" "Todos los respaldos completados"
        exit 0
    else
        log_message "ERROR" "Proceso de respaldo completado con errores"
        send_email_notification "FAILED" "Revisar logs en ${LOG_DIR}"
        exit 1
    fi
}

# Ejecutar
main "$@"
```

### 4.2 Configuración de `cron` (Linux/macOS)

#### Paso 1: Hacer el script ejecutable
```bash
chmod +x /usr/local/bin/fig-backup-scripts/backup_fig_database.sh
```

#### Paso 2: Editar crontab
```bash
sudo crontab -e
```

#### Paso 3: Agregar las siguientes líneas
```cron
# Respaldo diario completo a las 02:00 AM
0 2 * * * /usr/local/bin/fig-backup-scripts/backup_fig_database.sh >> /var/log/fig_backup_cron.log 2>&1

# Respaldo incremental cada 6 horas (02:00, 08:00, 14:00, 20:00)
0 2,8,14,20 * * * /usr/local/bin/fig-backup-scripts/backup_fig_database.sh >> /var/log/fig_backup_cron.log 2>&1
```

### 4.3 Configuración de Tareas Programadas (Windows Server)

#### Paso 1: Crear script PowerShell (`C:\Scripts\fig-backup-scripts\backup_fig_database.ps1`)

```powershell
# Script de Respaldo para Windows Server
$BackupPath = "C:\Backups\FIG-Database\daily"
$DB_Host = $env:DB_HOST
$DB_User = $env:DB_USER
$DB_Password = $env:DB_PASSWORD
$DB_Name = $env:DB_NAME
$LogFile = "C:\Backups\FIG-Database\logs\backup_$(Get-Date -Format 'yyyy-MM-dd').log"

# Ejecutar pg_dump
$env:PGPASSWORD = $DB_Password
pg_dump.exe -h $DB_Host -U $DB_User -d $DB_Name | gzip | Out-File -FilePath "$BackupPath\fig_backup_$(Get-Date -Format 'yyyy-MM-dd_HH-mm').sql.gz"

"Respaldo completado: $(Get-Date)" | Add-Content -Path $LogFile
```

#### Paso 2: Agendar con el Programador de Tareas
```powershell
# Ejecutar como Administrador
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-File C:\Scripts\fig-backup-scripts\backup_fig_database.ps1"
$trigger = New-ScheduledTaskTrigger -Daily -At 02:00AM
$principal = New-ScheduledTaskPrincipal -UserID "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

Register-ScheduledTask -TaskName "FIG_Database_Backup_Daily" `
                       -Description "Respaldo diario de BD F.I.G" `
                       -Action $action `
                       -Trigger $trigger `
                       -Principal $principal
```

---

## 5. PROCEDIMIENTO DE RESTAURACIÓN (CRÍTICO)

### 5.1 Pre-requisitos Antes de Restaurar

```bash
# Verificar que PostgreSQL está disponible
psql --version

# Verificar conectividad a la BD
psql -h ${DB_HOST} -U ${DB_USER} -d postgres -c "SELECT version();"

# Tener las credenciales de acceso
export PGPASSWORD="${DB_PASSWORD}"
```

### 5.2 Restauración Completa desde Respaldo

#### Escenario A: Restaurar en la misma instancia (Destrucción parcial)

**Paso 1: Parar la aplicación**
```bash
# En Linux
systemctl stop fig-application

# En macOS (si usa launchd)
launchctl stop com.fig.application
```

**Paso 2: Crear copia de seguridad del estado actual (si es posible)**
```bash
pg_dump -h localhost -U postgres -d fig_gastronomico > /tmp/current_state_backup.sql
```

**Paso 3: Desconectar todos los usuarios de la BD**
```bash
psql -h localhost -U postgres -d postgres -c "
SELECT pg_terminate_backend(pg_stat_activity.pid)
FROM pg_stat_activity
WHERE pg_stat_activity.datname = 'fig_gastronomico'
AND pid <> pg_backend_pid();
"
```

**Paso 4: Eliminar la BD existente (si está corrupta)**
```bash
psql -h localhost -U postgres -c "DROP DATABASE IF EXISTS fig_gastronomico;"
```

**Paso 5: Crear BD nueva (vacía)**
```bash
psql -h localhost -U postgres -c "CREATE DATABASE fig_gastronomico OWNER postgres;"
```

**Paso 6: Restaurar desde respaldo**
```bash
# Opción A: Desde archivo local comprimido
gunzip -c /var/backups/fig-database/daily/fig_backup_2026-05-22_02-00.sql.gz | \
psql -h localhost -U postgres -d fig_gastronomico

# Opción B: Desde archivo sin comprimir
psql -h localhost -U postgres -d fig_gastronomico < /tmp/fig_backup.sql

# Opción C: Mostrar progreso
gunzip -c /var/backups/fig-database/daily/fig_backup_2026-05-22_02-00.sql.gz | \
psql -h localhost -U postgres -d fig_gastronomico -v ON_ERROR_STOP=1
```

**Paso 7: Verificar integridad de la restauración**
```bash
# Contar registros en tablas clave
psql -h localhost -U postgres -d fig_gastronomico -c "
SELECT 
    'users' as table_name, COUNT(*) as record_count FROM users
UNION ALL
SELECT 'products', COUNT(*) FROM products
UNION ALL
SELECT 'orders', COUNT(*) FROM orders
UNION ALL
SELECT 'ingredients', COUNT(*) FROM ingredients;
"
```

**Paso 8: Verificar constraints e índices**
```bash
psql -h localhost -U postgres -d fig_gastronomico -c "
SELECT constraint_name, constraint_type 
FROM information_schema.table_constraints 
WHERE table_name IN ('users', 'products', 'orders');"
```

**Paso 9: Reiniciar la aplicación**
```bash
systemctl start fig-application
```

**Paso 10: Validación final en la aplicación**
```bash
# Probar login en la interfaz web
curl -X POST http://localhost:3000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"test"}'
```

#### Escenario B: Restaurar desde AWS S3 a nuevo servidor

**Paso 1: Descargar respaldo desde S3**
```bash
# Configurar credenciales AWS
export AWS_ACCESS_KEY_ID="tu_access_key"
export AWS_SECRET_ACCESS_KEY="tu_secret_key"

# Descargar
aws s3 cp s3://fig-gastronomico-backups/daily/fig_backup_2026-05-22_02-00.sql.gz \
         /tmp/fig_backup_restore.sql.gz \
         --region us-east-1
```

**Paso 2: Descomprimir (si está comprimido)**
```bash
gunzip /tmp/fig_backup_restore.sql.gz
```

**Paso 3: Ejecutar restauración**
```bash
psql -h ${NEW_DB_HOST} -U postgres -d fig_gastronomico < /tmp/fig_backup_restore.sql
```

**Paso 4: Validar credenciales y usuarios**
```bash
psql -h ${NEW_DB_HOST} -U postgres -d fig_gastronomico -c "
SELECT user_id, username, role FROM users LIMIT 5;"
```

#### Escenario C: Restauración Point-in-Time (PITR - Punto en el tiempo específico)

```bash
# Restaurar desde respaldo base + WAL logs
pg_basebackup -h ${DB_HOST} -U postgres -D /tmp/backup_directory

# Luego restaurar con recovery.conf apuntando a fecha específica
# Nota: Requiere WAL archiving habilitado
```

### 5.3 Tabla de Tiempo de Recuperación Estimada

| Tamaño de BD | Formato | Tiempo de Restauración | Notas |
|---|---|---|---|
| < 100 MB | SQL.GZ | 2-5 minutos | Aplicaciones pequeñas |
| 100 MB - 1 GB | SQL.GZ | 5-15 minutos | Restaurante pequeño/mediano |
| 1-5 GB | SQL.GZ | 15-45 minutos | Restaurante grande con histórico |
| > 5 GB | Binario (pg_basebackup) | 10-30 minutos | Requiere almacenamiento en espejo |

### 5.4 Checklist de Restauración

```
☐ Credenciales de BD verificadas
☐ Conectividad de red confirmada
☐ Espacio en disco disponible (>3x tamaño del respaldo)
☐ Aplicación detenida
☐ Backup descargado e íntegro
☐ Restauración iniciada
☐ Verificación de datos post-restauración
☐ Indices y constraints validados
☐ Aplicación reiniciada
☐ Pruebas funcionales en interfaz web
☐ Documentación actualizada con fecha/hora de restauración
```

---

## 6. POLÍTICAS DE RETENCIÓN

### 6.1 Cronograma de Retención

```
┌─────────────────────────────────────────────────────────┐
│              POLÍTICA DE RETENCIÓN DE RESPALDOS          │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ DIARIOS (Daily):                                       │
│   • Mantener: 7 días en almacenamiento local rápido    │
│   • Después: Mover a S3 STANDARD (30 días más)        │
│   • Luego: Cambiar a S3 GLACIER                        │
│                                                         │
│ SEMANALES (Weekly):                                    │
│   • Mantener: 4 semanas en almacenamiento local        │
│   • Después: Mover a S3 GLACIER (indefinido)           │
│                                                         │
│ MENSUALES (Monthly):                                   │
│   • Mantener: 12 meses en S3 GLACIER                   │
│   • Año 1: Acceso rápido (datos calientes)             │
│   • Año 2+: S3 DEEP ARCHIVE (muy raro acceso)          │
│                                                         │
│ TOTALES:                                               │
│   • Mínimo histórico: 1 año (legal/auditoría)          │
│   • Máximo recomendado: 2-3 años                       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### 6.2 Script de Limpieza Automática (Retention Policy)

```bash
#!/bin/bash
# cleanup_old_backups.sh - Ejecutar mensualmente

BACKUP_DIR="/var/backups/fig-database"
S3_BUCKET="fig-gastronomico-backups"

# Limpiar respaldos diarios > 30 días
find "${BACKUP_DIR}/daily" -type f -name "*.sql.gz" -mtime +30 -exec rm {} \;

# Limpiar respaldos semanales > 90 días
find "${BACKUP_DIR}/weekly" -type f -name "*.sql.gz" -mtime +90 -exec rm {} \;

# Cambiar archivos de S3 STANDARD a GLACIER después de 30 días
aws s3api put-object-lifecycle-configuration \
  --bucket "${S3_BUCKET}" \
  --lifecycle-configuration '{
    "Rules": [
      {
        "Id": "Archive-after-30-days",
        "Status": "Enabled",
        "Prefix": "daily/",
        "Transitions": [
          {
            "Days": 30,
            "StorageClass": "GLACIER"
          },
          {
            "Days": 365,
            "StorageClass": "DEEP_ARCHIVE"
          }
        ]
      }
    ]
  }'

echo "Limpieza de respaldos completada: $(date)" >> /var/log/backup_cleanup.log
```

### 6.3 Tabla de Costos Estimados (AWS S3)

```
Asumiendo: 500 MB por respaldo diario

┌─────────────────────────────────────────────────────────┐
│           COSTO MENSUAL ESTIMADO (USD)                  │
├─────────────────────────────────────────────────────────┤
│ Almacenamiento STANDARD (primeros 30 días):   $2.50     │
│ Almacenamiento GLACIER (30-365 días):         $0.80     │
│ Transferencias de datos:                      $2.00     │
│ API requests (put/get):                       $0.30     │
│                                                        │
│ TOTAL MENSUAL ESTIMADO:                       ~$6.00   │
│ TOTAL ANUAL ESTIMADO:                         ~$72.00  │
└─────────────────────────────────────────────────────────┘
```

---

## 7. MONITOREO Y ALERTAS

### 7.1 Indicadores Clave de Rendimiento (KPI)

```
✓ Tasa de éxito de respaldos:       > 99.5%
✓ Tiempo promedio de respaldo:      < 15 minutos
✓ Tamaño promedio de respaldo:      500-800 MB
✓ Tiempo de restauración:           < 2 horas
✓ Cobertura de almacenamiento:      Local + Cloud
✓ Validación de integridad:         Diaria
```

### 7.2 Alertas Configurables

```bash
# Enviar alerta si respaldo falla
if [ $? -ne 0 ]; then
    echo "ERROR: Respaldo fallido en $(date)" | mail -s "ALERTA: Fallo de Respaldo FIG" admin@fig.com
fi

# Enviar alerta si respaldo supera 30 minutos
BACKUP_TIME=$((END_TIME - START_TIME))
if [ $BACKUP_TIME -gt 1800 ]; then
    echo "AVISO: Respaldo tomó ${BACKUP_TIME} segundos" | mail -s "AVISO: Respaldo lento FIG" admin@fig.com
fi

# Enviar alerta si espacio disponible < 20%
AVAILABLE_SPACE=$(df /var/backups | awk 'NR==2 {print $4}')
TOTAL_SPACE=$(df /var/backups | awk 'NR==2 {print $2}')
PERCENTAGE=$((AVAILABLE_SPACE * 100 / TOTAL_SPACE))
if [ $PERCENTAGE -lt 20 ]; then
    echo "CRÍTICO: Espacio en disco bajo (${PERCENTAGE}% disponible)" | mail -s "CRÍTICO: Espacio en disco FIG" admin@fig.com
fi
```

---

## 8. RESPONSABILIDADES Y CONTACTOS

### 8.1 Equipo de Operaciones

| Rol | Nombre | Correo | Teléfono | Horario |
|---|---|---|---|---|
| DevOps Lead | Juan Pérez | juan.perez@fig.com | +34 91 234 5678 | Lun-Vie 8AM-6PM |
| Backup Admin | María García | maria.garcia@fig.com | +34 91 345 6789 | Lun-Vie 8AM-6PM |
| Sysadmin On-Call | Carlos López | carlos.lopez@fig.com | +34 91 456 7890 | 24/7 Emergencias |
| Escalation Manager | Ana Martínez | ana.martinez@fig.com | +34 91 567 8901 | Lun-Vie 9AM-5PM |

### 8.2 Procedimiento de Escalación en Caso de Fallo

```
Paso 1 (Minuto 0):
  → Sistema detecta fallo de respaldo
  → Envía alerta automática a DevOps Lead

Paso 2 (Minuto 5):
  → DevOps Lead revisa logs
  → Si es problema simple → Intenta reintentar respaldo

Paso 3 (Minuto 15):
  → Si persiste el fallo → Contacta Backup Admin
  → Escala a Sysadmin On-Call si es crítico

Paso 4 (Minuto 30):
  → Si no hay solución → Ejecuta plan de restauración alterno
  → Notifica a gerencia
```

---

## 9. REGISTROS DE MANTENIMIENTO Y PRUEBAS

### 9.1 Bitácora de Pruebas de Restauración

**Todos los respaldos deben ser probados trimestralmente**

```markdown
| Fecha | Tipo de Respaldo | Resultado | Tiempo de Restauración | Observaciones | Responsable |
|---|---|---|---|---|---|
| 2026-05-22 | Diario (02:00 AM) | ✓ Exitoso | 12 min | Respaldo íntegro | María García |
| 2026-05-22 | Verificación de integridad | ✓ OK | 5 min | Archivo comprimido válido | María García |
| 2026-05-25 | Prueba de restauración (Semanal) | ✓ Exitoso | 18 min | BD restaurada, validaciones OK | Carlos López |
```

### 9.2 Changelog de Procedimiento

```
v1.0 (Mayo 2026)
  • Documento inicial
  • Respaldos diarios automáticos
  • Sincronización a AWS S3
  • Procedimientos de restauración documentados

[Próximas versiones]
  • Agregar soporte para replicación en tiempo real
  • Implementar PITR (Point-in-Time Recovery)
  • Considerar Disaster Recovery en otra región
```

---

## 10. REFERENCIAS Y DOCUMENTOS RELACIONADOS

- PostgreSQL Official Backup Documentation: https://www.postgresql.org/docs/current/backup.html
- AWS S3 Lifecycle Policies: https://docs.aws.amazon.com/AmazonS3/latest/userguide/object-lifecycle-mgmt.html
- Documento: "Configuración del Servidor (Pre-producción/Producción)"
- Documento: "Plan de Recuperación ante Desastres (DRP)"

---

**Documento preparado por:** Equipo DevOps  
**Última actualización:** 22 de Mayo de 2026  
**Próxima revisión:** 22 de Agosto de 2026  
**Clasificación:** Interno - Operativo
