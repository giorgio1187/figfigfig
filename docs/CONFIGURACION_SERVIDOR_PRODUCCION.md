# DOCUMENTO DE CONFIGURACIÓN DEL SERVIDOR
## Sistema F.I.G - Funcionamiento Íntegro Gastronómico
### Pre-producción / Producción Futura

**Versión:** 1.0  
**Última actualización:** Mayo 2026  
**Estado:** Documento Técnico de Referencia  
**Clasificación:** Interno - Técnico

---

## TABLA DE CONTENIDOS

1. [Especificaciones del Sistema Operativo](#1-especificaciones-del-sistema-operativo)
2. [Configuración del Servidor Web/Proxy](#2-configuración-del-servidor-webproxy)
3. [Instalación del Stack de Programación](#3-instalación-del-stack-de-programación)
4. [Bibliotecas y Dependencias del Sistema](#4-bibliotecas-y-dependencias-del-sistema)
5. [Configuración de Seguridad Básica](#5-configuración-de-seguridad-básica)
6. [Configuración de Base de Datos](#6-configuración-de-base-de-datos)
7. [Variables de Entorno](#7-variables-de-entorno)
8. [Instalación de la Aplicación](#8-instalación-de-la-aplicación)
9. [Configuración de Monitoreo](#9-configuración-de-monitoreo)
10. [Procedimientos de Validación](#10-procedimientos-de-validación)

---

## 1. ESPECIFICACIONES DEL SISTEMA OPERATIVO

### 1.1 Opción Recomendada: Ubuntu Server 22.04 LTS (Linux)

#### 1.1.1 Requisitos Mínimos

```
Procesador:     2 vCPU (Intel Xeon o equivalente AWS/Azure)
Memoria RAM:    4 GB mínimo (8 GB recomendado para producción)
Almacenamiento: 40 GB SSD (para SO + aplicación)
                100 GB adicional para base de datos
                50 GB adicional para respaldos locales
Red:            Interfaz Ethernet de 1 Gbps mínimo

Uptime:         99.5% mínimo (SLA)
Tipo de Disco:  SSD NVMe preferido (I/O rápido para BD)
```

#### 1.1.2 Descarga e Instalación

**Fuente oficial:**
```
Ubuntu Server 22.04 LTS
ISO: ubuntu-22.04-live-server-amd64.iso
Descargar desde: https://www.ubuntu.com/download/server
```

**Pasos de instalación:**
```
1. Boot desde ISO en servidor físico o VM
2. Seleccionar idioma: Español
3. Configurar red:
   - IP estática: 192.168.x.x (según red)
   - Gateway: 192.168.1.1
   - DNS: 8.8.8.8, 8.8.4.4
4. Particionar disco:
   - /boot: 1 GB (ext4)
   - /: 40 GB (ext4)
   - /var: 50 GB (ext4, para logs)
   - /home: 100 GB (ext4, para datos)
5. Seleccionar OpenSSH durante instalación
6. Actualizar sistema al completar
```

**Actualizar inmediatamente después de instalar:**
```bash
sudo apt-get update
sudo apt-get upgrade -y
sudo apt-get dist-upgrade -y
sudo reboot
```

### 1.2 Alternativa: Rocky Linux 9 (Enterprise Linux)

```bash
# Descargar ISO desde:
# https://rockylinux.org/download/

# Especificaciones similares a Ubuntu
# Ventajas: Mayor compatibilidad RHEL, soporte extendido (10 años)
# Desventaja: Comunidad más pequeña que Ubuntu

# Post-instalación:
sudo dnf update -y
sudo dnf install epel-release -y
```

### 1.3 Alternativa: Windows Server 2022

```powershell
# Requisitos adicionales:
# - Licencia Windows Server
# - PowerShell 7.x
# - .NET Runtime

# Instalación mínima (sin GUI recomendado para producción)
# Usar RemoteDesktop o PowerShell remoto para administración
```

### 1.4 Configuración de Hostname y Zona Horaria

```bash
# Ubuntu/Rocky Linux

# 1. Establecer nombre del servidor (hostname)
sudo hostnamectl set-hostname fig-production-server

# 2. Verificar cambio
hostnamectl

# 3. Configurar zona horaria
sudo timedatectl set-timezone America/Mexico_City
# O para España: Europe/Madrid

# 4. Verificar zona horaria
timedatectl

# 5. Sincronizar hora (NTP)
sudo systemctl enable systemd-timesyncd
sudo systemctl start systemd-timesyncd
```

---

## 2. CONFIGURACIÓN DEL SERVIDOR WEB/PROXY

### 2.1 Opción Recomendada: NGINX

#### 2.1.1 Instalación

```bash
# Ubuntu/Debian
sudo apt-get install -y nginx

# Rocky Linux
sudo dnf install -y nginx

# Iniciar servicio
sudo systemctl enable nginx
sudo systemctl start nginx

# Verificar estado
sudo systemctl status nginx
```

#### 2.1.2 Configuración Principal (`/etc/nginx/nginx.conf`)

**Backup de configuración original:**
```bash
sudo cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.bak
```

**Configuración optimizada:**
```nginx
# /etc/nginx/nginx.conf

user www-data;
worker_processes auto;  # Detecta automáticamente número de CPUs
pid /run/nginx.pid;
error_log /var/log/nginx/error.log warn;

events {
    worker_connections 2048;
    use epoll;  # Optimizado para Linux
    multi_accept on;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    # Logging
    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;

    # Optimización de rendimiento
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 20M;

    # Compresión
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript 
               application/json application/javascript application/xml+rss;

    # Incluir configuraciones de sitios
    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}
```

#### 2.1.3 Configuración del Sitio (`/etc/nginx/sites-available/fig-app`)

```nginx
# /etc/nginx/sites-available/fig-app

# Redirigir HTTP a HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name fig.restaurante.com www.fig.restaurante.com;

    return 301 https://$server_name$request_uri;
}

# Servidor HTTPS (Producción)
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    
    server_name fig.restaurante.com www.fig.restaurante.com;

    # Certificados SSL (Let's Encrypt con Certbot)
    ssl_certificate /etc/letsencrypt/live/fig.restaurante.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/fig.restaurante.com/privkey.pem;

    # Configuración SSL optimizada
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # Headers de seguridad
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    # Logs
    access_log /var/log/nginx/fig-access.log main;
    error_log /var/log/nginx/fig-error.log warn;

    # Raíz de documentos (frontend estático)
    root /var/www/fig-app/frontend;
    index index.html;

    # Proxy a Node.js backend
    location /api/ {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
        
        # Timeouts para operaciones largas
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }

    # Archivos estáticos con caché
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Negar acceso a archivos sensibles
    location ~ /\.ht {
        deny all;
    }

    location ~ /\. {
        deny all;
    }

    # Página de error 404 personalizada
    error_page 404 /404.html;

    # Página de error 500
    error_page 500 502 503 504 /50x.html;
    location = /50x.html {
        root /usr/share/nginx/html;
    }
}
```

**Habilitar configuración:**
```bash
sudo ln -s /etc/nginx/sites-available/fig-app /etc/nginx/sites-enabled/

sudo nginx -t  # Probar configuración

sudo systemctl reload nginx
```

#### 2.1.4 Certificado SSL con Let's Encrypt

```bash
# 1. Instalar certbot
sudo apt-get install -y certbot python3-certbot-nginx

# 2. Obtener certificado (requiere acceso a puerto 80)
sudo certbot certonly --nginx -d fig.restaurante.com -d www.fig.restaurante.com

# 3. Renovación automática (cron)
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer

# 4. Probar renovación
sudo certbot renew --dry-run
```

### 2.2 Alternativa: Apache 2.4

```bash
# Instalación
sudo apt-get install -y apache2 apache2-utils

# Habilitar módulos necesarios
sudo a2enmod rewrite ssl proxy proxy_http headers

# Crear VirtualHost
sudo a2ensite fig-app
sudo a2dissite 000-default

# Certificado SSL
sudo certbot certonly --apache -d fig.restaurante.com

# Reiniciar
sudo systemctl restart apache2
```

---

## 3. INSTALACIÓN DEL STACK DE PROGRAMACIÓN

### 3.1 Node.js (Versión LTS 18.x)

#### 3.1.1 Instalación mediante NodeSource

```bash
# Ubuntu/Debian
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# Verificar instalación
node --version      # v18.x.x
npm --version       # 9.x.x

# Actualizar npm a versión más reciente
sudo npm install -g npm@latest
```

#### 3.1.2 Alternativa: Node Version Manager (nvm)

```bash
# Instalar nvm
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash

# Recargar shell
source ~/.bashrc

# Instalar Node.js 18
nvm install 18
nvm use 18
nvm alias default 18
```

#### 3.1.3 Verificación

```bash
node -e "console.log('Node.js funcionando correctamente')"
npm -v
npm list -g  # Ver paquetes globales
```

### 3.2 PostgreSQL 14+ (Cliente)

**Nota:** Para producción, usar PostgreSQL gestinado (AWS RDS, Azure Database for PostgreSQL, Supabase) es recomendado.

#### 3.2.1 Instalación del Cliente PostgreSQL

```bash
# Ubuntu/Debian
sudo apt-get install -y postgresql-client postgresql-client-common

# Rocky Linux
sudo dnf install -y postgresql

# Verificar instalación
psql --version    # psql (PostgreSQL) 14.x

# Configurar psql para búsqueda de host local
echo "host    all             all             127.0.0.1/32            md5" | \
  sudo tee -a /etc/postgresql/14/main/pg_hba.conf
```

#### 3.2.2 Verificación de Conectividad (antes de instalar app)

```bash
# Prueba de conexión (cuando BD esté disponible)
psql -h ${DB_HOST} -U ${DB_USER} -d postgres -c "SELECT version();"
```

### 3.3 Gestor de Paquetes npm

```bash
# Verificar que npm funciona
npm search express

# Configurar registro privado (si aplica)
npm config set registry https://registry.npmjs.org/

# Ver configuración
npm config list
```

### 3.4 Herramientas Adicionales

#### 3.4.1 PM2 (Gestor de Procesos de Node.js)

```bash
# Instalación global
sudo npm install -g pm2

# Completamiento bash
pm2 completion install

# Verificar
pm2 --version
```

#### 3.4.2 Git (Control de Versiones)

```bash
# Ubuntu/Debian
sudo apt-get install -y git

# Rocky Linux
sudo dnf install -y git

# Configurar usuario global
git config --global user.name "DevOps FIG"
git config --global user.email "devops@fig.com"
```

---

## 4. BIBLIOTECAS Y DEPENDENCIAS DEL SISTEMA

### 4.1 Herramientas de Desarrollo

```bash
# Ubuntu/Debian
sudo apt-get install -y \
    build-essential \
    curl \
    wget \
    git \
    vim \
    nano \
    htop \
    net-tools \
    telnet \
    traceroute \
    dnsutils

# Rocky Linux
sudo dnf install -y \
    gcc \
    gcc-c++ \
    make \
    curl \
    wget \
    git \
    vim \
    nano \
    htop \
    net-tools \
    bind-utils
```

### 4.2 Bibliotecas de Seguridad

```bash
# Ubuntu/Debian
sudo apt-get install -y \
    openssl \
    libssl-dev \
    ca-certificates \
    certbot \
    fail2ban

# Rocky Linux
sudo dnf install -y \
    openssl \
    openssl-devel \
    ca-certificates \
    certbot \
    fail2ban
```

### 4.3 Herramientas de Respaldo y Sincronización

```bash
# PostgreSQL tools (incluido con cliente)
# pg_dump, psql, pg_restore

# rclone (sincronización con cloud)
curl https://rclone.org/install.sh | sudo bash

# AWS CLI v2 (para S3)
sudo apt-get install -y awscli
# O compilar desde: https://aws.amazon.com/cli/
```

### 4.4 Utilidades de Monitoreo

```bash
# Instalación de herramientas de monitoreo
sudo apt-get install -y \
    sysstat \
    iotop \
    nethogs

# Ver uso de CPU/RAM en tiempo real
top        # O htop (más amigable)

# Ver uso de disco
df -h
du -sh /var/*

# Ver procesos por puerto
netstat -tuln
ss -tuln   # Alternativa moderna
```

### 4.5 Docker (Opcional para desarrollo/testing)

```bash
# Instalación en Ubuntu
sudo apt-get install -y docker.io docker-compose

# Agregar usuario actual al grupo docker
sudo usermod -aG docker $USER
newgrp docker

# Verificar instalación
docker --version
docker-compose --version
```

---

## 5. CONFIGURACIÓN DE SEGURIDAD BÁSICA

### 5.1 Firewall (UFW - Ubuntu)

#### 5.1.1 Habilitar Firewall

```bash
# Habilitar UFW
sudo ufw enable

# Verificar estado
sudo ufw status
```

#### 5.1.2 Configuración de Puertos

```bash
# Puerto 22 (SSH) - CRÍTICO: Permitir ANTES de deshabilitar
sudo ufw allow 22/tcp

# Puerto 80 (HTTP)
sudo ufw allow 80/tcp

# Puerto 443 (HTTPS) - PREFERIDO
sudo ufw allow 443/tcp

# Puerto 3000 (Node.js backend - SOLO desde localhost)
sudo ufw allow from 127.0.0.1 to 127.0.0.1 port 3000

# Puerto 5432 (PostgreSQL - SOLO desde localhost en desarrollo)
# En producción, NO exponer. Usar Unix socket o acceso restringido
sudo ufw allow from 127.0.0.1 to 127.0.0.1 port 5432

# Ver reglas activas
sudo ufw show added

# Denegar todo lo demás (por defecto)
sudo ufw default deny incoming
sudo ufw default allow outgoing
```

#### 5.1.3 Regla para Acceso SSH Seguro (Opcional)

```bash
# Permitir SSH solo desde IP específica (administrador)
sudo ufw allow from 203.0.113.50/32 to any port 22

# O cambiar puerto SSH a uno no estándar (ej: 2222)
# Editar /etc/ssh/sshd_config: Port 2222
sudo sed -i 's/#Port 22/Port 2222/' /etc/ssh/sshd_config
sudo systemctl restart ssh

# Luego: sudo ufw allow 2222/tcp
```

### 5.2 Firewall en Rocky Linux (firewalld)

```bash
# Habilitar firewalld
sudo systemctl enable firewalld
sudo systemctl start firewalld

# Permitir puertos
sudo firewall-cmd --permanent --add-port=22/tcp
sudo firewall-cmd --permanent --add-port=80/tcp
sudo firewall-cmd --permanent --add-port=443/tcp
sudo firewall-cmd --permanent --add-port=3000/tcp

# Recargar
sudo firewall-cmd --reload

# Listar puertos
sudo firewall-cmd --list-ports
```

### 5.3 Hardening SSH

#### 5.3.1 Editar `/etc/ssh/sshd_config`

```bash
sudo cp /etc/ssh/sshd_config /etc/ssh/sshd_config.bak
sudo nano /etc/ssh/sshd_config
```

**Cambios recomendados:**

```bash
# Cambiar puerto (opcional)
# Port 2222

# Deshabilitar acceso root
PermitRootLogin no

# Deshabilitar autenticación por contraseña (usar SSH keys)
PasswordAuthentication no
PubkeyAuthentication yes

# Limitar intentos de login
MaxAuthTries 3
MaxSessions 5

# Timeouts
ClientAliveInterval 300
ClientAliveCountMax 2

# Deshabilitar X11 forwarding
X11Forwarding no

# Banner
Banner /etc/ssh/banner.txt

# LogLevel
LogLevel VERBOSE
```

**Crear banner SSH (opcional):**
```bash
sudo tee /etc/ssh/banner.txt > /dev/null <<EOF
╔═════════════════════════════════════════════════════════════════╗
║        SISTEMA F.I.G - ACCESO AUTORIZADO SOLAMENTE              ║
║                   Connexiones No Autorizadas                     ║
║                      Serán Prosecutadas                           ║
╚═════════════════════════════════════════════════════════════════╝
EOF
```

**Aplicar cambios:**
```bash
sudo systemctl reload ssh

# Verificar cambios
sudo sshd -t  # Test de configuración
```

#### 5.3.2 Configurar Claves SSH

```bash
# En cliente (administrador), generar clave SSH
ssh-keygen -t ed25519 -C "admin@fig.com" -f ~/.ssh/id_fig

# Copiar clave pública al servidor
ssh-copy-id -i ~/.ssh/id_fig.pub admin@servidor.fig.com

# Verificar acceso sin contraseña
ssh -i ~/.ssh/id_fig admin@servidor.fig.com "uname -a"
```

### 5.4 Protección contra Fuerza Bruta (Fail2ban)

#### 5.4.1 Instalación y Configuración

```bash
# Instalar
sudo apt-get install -y fail2ban

# Crear configuración local
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local

# Editar configuración
sudo nano /etc/fail2ban/jail.local
```

**Configuración recomendada:**

```ini
[DEFAULT]
bantime = 3600        # Ban por 1 hora
findtime = 600        # Revisar últimos 10 minutos
maxretry = 5          # Máximo 5 intentos

[sshd]
enabled = true
port = ssh,2222       # Ajustar si SSH está en puerto diferente
logpath = /var/log/auth.log

[nginx-http-auth]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log

[nginx-noscript]
enabled = true
port = http,https
logpath = /var/log/nginx/access.log
```

**Iniciar y habilitar:**
```bash
sudo systemctl enable fail2ban
sudo systemctl start fail2ban

# Ver bans activos
sudo fail2ban-client status
sudo fail2ban-client status sshd
```

### 5.5 SELinux (Rocky Linux)

```bash
# Ver estado
getenforce

# Configurar modo (Enforcing = seguridad máxima)
sudo semanage permissive -a httpd_t  # Permitir httpd
sudo semanage permissive -a nodejs_t # Permitir node.js
```

### 5.6 Auditoría de Seguridad

```bash
# Verificar puertos abiertos
sudo ss -tlnp | grep LISTEN

# Verificar permisos de archivos críticos
sudo ls -la /etc/ssh/
sudo ls -la /etc/sudoers.d/

# Ver usuarios activos
w
who

# Ver login history
last
lastb  # Intentos fallidos
```

---

## 6. CONFIGURACIÓN DE BASE DE DATOS

### 6.1 PostgreSQL en Supabase (Recomendado para Producción)

#### 6.1.1 Crear Proyecto en Supabase

```
1. Ir a https://supabase.com
2. Sign up o Sign in
3. Crear nuevo proyecto
4. Seleccionar región geográficamente cercana
5. Establecer contraseña de usuario postgres
6. Copiar connection string
```

#### 6.1.2 Connection String Supabase

```
Formato:
postgresql://postgres:[PASSWORD]@[HOST]:[PORT]/postgres?sslmode=require

Ejemplo:
postgresql://postgres:MyStrongPassword123@db.xxxxxprojid.supabase.co:5432/postgres?sslmode=require
```

### 6.2 PostgreSQL Autoalojado (Alternativa)

#### 6.2.1 Instalación Servidor PostgreSQL

```bash
# Ubuntu
sudo apt-get install -y postgresql postgresql-contrib

# Rocky Linux
sudo dnf install -y postgresql-server postgresql-contrib

# Inicializar (Rocky)
sudo postgresql-setup initdb
```

#### 6.2.2 Configuración Básica

```bash
# Editar postgresql.conf
sudo nano /etc/postgresql/14/main/postgresql.conf

# Cambios recomendados:
# listen_addresses = 'localhost'  (solo localhost para seguridad)
# max_connections = 200
# shared_buffers = 256MB           (25% de RAM)
# effective_cache_size = 1GB       (50-75% de RAM)
```

**Iniciar servicio:**
```bash
sudo systemctl enable postgresql
sudo systemctl start postgresql
```

#### 6.2.3 Crear Base de Datos

```bash
# Conectar como usuario postgres
sudo -u postgres psql

# Dentro de psql:
CREATE DATABASE fig_gastronomico OWNER postgres;
\c fig_gastronomico
```

**Crear usuario específico (NO usar postgres en producción):**
```sql
CREATE USER fig_app_user WITH ENCRYPTED PASSWORD 'StrongPassword123!';
GRANT ALL PRIVILEGES ON DATABASE fig_gastronomico TO fig_app_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO fig_app_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO fig_app_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON FUNCTIONS TO fig_app_user;
```

### 6.3 Cargar Schema de Base de Datos

```bash
# Desde el servidor de aplicación
psql -h ${DB_HOST} -U ${DB_USER} -d fig_gastronomico \
     -f /var/www/fig-app/backend/database/schema.sql

# O si es localhost:
sudo -u postgres psql -d fig_gastronomico < \
     /var/www/fig-app/backend/database/schema.sql
```

**Verificar tablas creadas:**
```bash
psql -h localhost -U postgres -d fig_gastronomico -c "\dt"
```

---

## 7. VARIABLES DE ENTORNO

### 7.1 Crear archivo `.env` en servidor

**Ubicación:** `/var/www/fig-app/.env`

```bash
# /var/www/fig-app/.env
# ============================================================
# F.I.G - Funcionamiento Íntegro Gastronómico
# Configuración de Entorno - PRODUCCIÓN
# ============================================================

# ─── NODE.JS ───
NODE_ENV=production
PORT=3000
LOG_LEVEL=info

# ─── BASE DE DATOS ───
# Opción A: Supabase (Recomendado)
DB_HOST=db.xxxxxprojid.supabase.co
DB_PORT=5432
DB_NAME=postgres
DB_USER=postgres
DB_PASSWORD=tu_password_muy_fuerte_aqui
DB_SSL=true

# Opción B: PostgreSQL Local
# DB_HOST=localhost
# DB_PORT=5432
# DB_NAME=fig_gastronomico
# DB_USER=fig_app_user
# DB_PASSWORD=StrongPassword123!
# DB_SSL=false

# ─── SUPABASE (Si aplica) ───
SUPABASE_URL=https://xxxxxprojid.supabase.co
SUPABASE_ANON_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
SUPABASE_SERVICE_ROLE_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...

# ─── SEGURIDAD ───
JWT_SECRET=tu_jwt_secret_super_largo_y_aleatorio_aqui_minimo_32_caracteres
BCRYPT_ROUNDS=10
SESSION_TIMEOUT=3600

# ─── CORREO (Para notificaciones) ───
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=noreply@fig.com
SMTP_PASSWORD=contraseña_app_gmail
SMTP_FROM=FIG Restaurante <noreply@fig.com>

# ─── AWS S3 (Para respaldos) ───
AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
AWS_REGION=us-east-1
AWS_S3_BUCKET=fig-gastronomico-backups

# ─── APLICACIÓN ───
APP_NAME=FIG
APP_VERSION=1.0.0
APP_URL=https://fig.restaurante.com

# ─── MONITOREO ───
SENTRY_DSN=https://example@sentry.io/1234567
ROLLBAR_ACCESS_TOKEN=rollbar_token_aqui

# ─── CORS ───
CORS_ORIGIN=https://fig.restaurante.com

# ─── LÍMITE DE RATE ───
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=100
```

**Proteger archivo:**
```bash
sudo chown fig-app:fig-app /var/www/fig-app/.env
sudo chmod 600 /var/www/fig-app/.env
```

### 7.2 Cargar variables en sistema

**Opción A: Usar PM2 ecosystem file**

```javascript
// ecosystem.config.js
module.exports = {
  apps: [{
    name: 'fig-app',
    script: './backend/Server.js',
    instances: 'max',
    exec_mode: 'cluster',
    env: {
      NODE_ENV: 'production',
      PORT: 3000
    },
    env_file: '.env',
    error_file: '/var/log/pm2/fig-app-error.log',
    out_file: '/var/log/pm2/fig-app-out.log',
    merge_logs: true
  }]
};
```

**Opción B: Usar systemd service**

Ver sección 8.3.1

---

## 8. INSTALACIÓN DE LA APLICACIÓN

### 8.1 Descargar Código de Aplicación

```bash
# Crear directorio de aplicación
sudo mkdir -p /var/www/fig-app
sudo chown -R www-data:www-data /var/www/fig-app

# Opción A: Clonar desde Git
cd /var/www/fig-app
sudo -u www-data git clone https://github.com/turepositorio/fig-app.git .

# Opción B: Copiar desde código existente
sudo cp -r ~/Desktop/FIG_FV-version-funcional-final/* /var/www/fig-app/
```

### 8.2 Instalar Dependencias Node.js

```bash
cd /var/www/fig-app

# Instalar dependencias
sudo -u www-data npm install

# Generar lock file
sudo -u www-data npm ci  # Usar si existe package-lock.json

# Verificar instalación
sudo -u www-data npm list
```

### 8.3 Ejecutar Aplicación con PM2

#### 8.3.1 Crear archivo ecosystem.config.js

```javascript
// /var/www/fig-app/ecosystem.config.js

module.exports = {
  apps: [
    {
      name: 'fig-app',
      script: './backend/Server.js',
      instances: 'max',           // Usar todos los cores
      exec_mode: 'cluster',        // Modo cluster para load balancing
      env_file: '.env',
      env: {
        NODE_ENV: 'production'
      },
      error_file: '/var/log/pm2/fig-app-error.log',
      out_file: '/var/log/pm2/fig-app-out.log',
      merge_logs: true,
      max_memory_restart: '500M',  // Reiniciar si usa > 500MB
      watch: false,                // No reiniciar en cambios de archivo
      ignore_watch: ['node_modules', 'logs'],
      max_restarts: 10,
      min_uptime: '10s',
      autorestart: true,
      
      // Hooks de ciclo de vida
      preStop: 'npm run preStop || true',
      postStop: 'npm run postStop || true',
      preReload: 'npm run preReload || true'
    }
  ],

  deploy: {
    production: {
      user: 'www-data',
      host: 'fig-production.ejemplo.com',
      ref: 'origin/main',
      repo: 'https://github.com/turepositorio/fig-app.git',
      path: '/var/www/fig-app',
      'post-deploy': 'npm install && pm2 reload ecosystem.config.js --env production'
    }
  }
};
```

#### 8.3.2 Iniciar con PM2

```bash
cd /var/www/fig-app

# Iniciar aplicación
sudo -u www-data pm2 start ecosystem.config.js --env production

# Ver procesos
sudo pm2 list

# Ver logs en tiempo real
sudo pm2 logs fig-app

# Guardar configuración de PM2
sudo pm2 save

# Habilitar startup automático
sudo pm2 startup systemd -u www-data --hp /var/www
sudo pm2 save
```

### 8.4 Alternativa: Usar Systemd Service

```bash
# /etc/systemd/system/fig-app.service

[Unit]
Description=FIG Gastronómico Application
After=network.target postgresql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/fig-app
EnvironmentFile=/var/www/fig-app/.env
Environment="NODE_ENV=production"
ExecStart=/usr/bin/node /var/www/fig-app/backend/Server.js
Restart=on-failure
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

**Habilitar servicio:**
```bash
sudo systemctl enable fig-app.service
sudo systemctl start fig-app.service
sudo systemctl status fig-app.service
```

### 8.5 Verificar que Aplicación Funciona

```bash
# Verificar que Node.js está escuchando en puerto 3000
sudo ss -tlnp | grep 3000

# Hacer request de prueba
curl http://localhost:3000/

# Ver logs
sudo journalctl -u fig-app.service -n 50
# O con PM2:
sudo pm2 logs fig-app
```

---

## 9. CONFIGURACIÓN DE MONITOREO

### 9.1 Monitoreo de Logs con Systemd Journal

```bash
# Ver logs en tiempo real
sudo journalctl -u fig-app.service -f

# Ver últimas 100 líneas
sudo journalctl -u fig-app.service -n 100

# Ver errores de hoy
sudo journalctl -u fig-app.service --since today --priority err
```

### 9.2 Monitoreo de Recursos (CPU, RAM, Disco)

```bash
# Instalación
sudo apt-get install -y htop iotop nethogs

# Ver uso de recursos
htop

# Ver I/O de disco
sudo iotop

# Ver uso de red por proceso
sudo nethogs

# Ver crecimiento de disco
du -sh /var/www/fig-app/*
df -h /
```

### 9.3 Alertas Básicas (Script cron)

```bash
#!/bin/bash
# /usr/local/bin/check_fig_health.sh

# Verificar si aplicación está funcionando
if ! sudo pm2 status fig-app | grep -q "online"; then
    echo "ALERTA: FIG App no está corriendo" | \
    mail -s "ALERTA: FIG App Down" admin@fig.com
fi

# Verificar uso de memoria
MEMORY_USAGE=$(ps aux | grep 'node' | grep -v grep | awk '{sum+=$6} END {print sum}')
if [ $MEMORY_USAGE -gt 600000 ]; then  # > 600MB
    echo "ALERTA: Uso de memoria alto: ${MEMORY_USAGE}KB" | \
    mail -s "ALERTA: Memoria alta FIG" admin@fig.com
fi

# Verificar espacio en disco
DISK_USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 80 ]; then
    echo "ALERTA: Uso de disco: ${DISK_USAGE}%" | \
    mail -s "ALERTA: Disco lleno FIG" admin@fig.com
fi
```

**Agendar en cron:**
```bash
# Ejecutar cada 5 minutos
*/5 * * * * /usr/local/bin/check_fig_health.sh
```

### 9.4 Logging Centralizado (Recomendado)

**Integración con Sentry o ELK Stack (Elasticsearch, Logstash, Kibana)**

```bash
# Instalar cliente Sentry en Node.js
npm install @sentry/node

# Usar en aplicación (backend/Server.js)
const Sentry = require("@sentry/node");
Sentry.init({ dsn: process.env.SENTRY_DSN });
```

---

## 10. PROCEDIMIENTOS DE VALIDACIÓN

### 10.1 Checklist de Puesta en Producción

```
☐ Sistema Operativo
  ☐ Ubuntu 22.04 LTS instalado
  ☐ Sistema actualizado (apt update && apt upgrade)
  ☐ Hostname configurado
  ☐ Zona horaria correcta
  ☐ NTP sincronizado

☐ Seguridad
  ☐ Firewall habilitado (UFW/firewalld)
  ☐ Puertos correctos: 22, 80, 443 (SSH solo desde IP conocida)
  ☐ SSH configurado (sin root, sin contraseña)
  ☐ Certificado SSL válido (Let's Encrypt)
  ☐ fail2ban instalado y activo
  ☐ SELinux/AppArmor configurado (si aplica)

☐ Servidor Web
  ☐ Nginx instalado y funcionando
  ☐ Configuración optimizada
  ☐ Headers de seguridad añadidos
  ☐ Compresión gzip habilitada
  ☐ Caché de archivos estáticos configurado

☐ Runtime
  ☐ Node.js v18.x instalado
  ☐ npm actualizado
  ☐ PM2 instalado globalmente
  ☐ Otros tools: git, curl, wget, htop

☐ Base de Datos
  ☐ PostgreSQL cliente instalado
  ☐ Conectividad verificada (psql test)
  ☐ Usuario específico creado (fig_app_user)
  ☐ Schema importado
  ☐ Respaldos configurados

☐ Aplicación F.I.G
  ☐ Código descargado en /var/www/fig-app
  ☐ Permisos correctos (www-data owner)
  ☐ .env configurado y protegido (chmod 600)
  ☐ npm install completado
  ☐ PM2 ecosystem.config.js creado
  ☐ Aplicación iniciada y verificada

☐ Monitoreo
  ☐ Logs en /var/log/pm2/
  ☐ Alerts configuradas
  ☐ Backup schedule activo
  ☐ Verificación diaria de logs

☐ Documentación
  ☐ Este documento completado
  ☐ Procedimiento de respaldos documentado
  ☐ Contactos de emergencia actualizados
  ☐ Passwords guardados en gestor seguro (LastPass/1Password)
```

### 10.2 Test de Conectividad

```bash
# Test 1: ¿Nginx está corriendo?
curl http://localhost
# Esperado: HTML de la aplicación

# Test 2: ¿Backend está disponible?
curl http://localhost:3000/
# Esperado: Conexión exitosa

# Test 3: ¿HTTPS funciona?
curl -I https://fig.restaurante.com
# Esperado: HTTP/1.1 200 OK

# Test 4: ¿BD está conectada?
curl -X POST http://localhost:3000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"test"}'
# Esperado: Respuesta válida (éxito o fallo de autenticación, no error de conexión)

# Test 5: ¿Puertos están protegidos?
sudo nmap -p- localhost
# Esperado: Solo 22, 80, 443 abiertos (3000 cerrado desde fuera)
```

### 10.3 Test de Seguridad

```bash
# Test 1: Verificar certificado SSL
openssl s_client -connect fig.restaurante.com:443

# Test 2: Escanear vulnerabilidades SSH
ssh -v fig.restaurante.com
# Verificar que no permite root ni contraseña

# Test 3: Verificar headers de seguridad
curl -I https://fig.restaurante.com
# Esperado: Strict-Transport-Security, X-Frame-Options, etc.

# Test 4: Verificar bans de fail2ban
sudo fail2ban-client status sshd

# Test 5: Ver puertos abiertos (desde otra máquina)
nmap fig.restaurante.com
```

### 10.4 Test de Rendimiento

```bash
# Instalar herramientas
sudo apt-get install -y apache2-utils

# Test de carga básico
ab -n 100 -c 10 http://localhost/

# Test más complejo (herramienta: siege)
sudo apt-get install -y siege
siege -c 10 -r 10 http://localhost/
```

### 10.5 Test de Respaldo

```bash
# Test 1: Ejecutar respaldo manual
/usr/local/bin/fig-backup-scripts/backup_fig_database.sh

# Test 2: Verificar archivo de respaldo
ls -lh /var/backups/fig-database/daily/

# Test 3: Restaurar en BD de prueba
createdb fig_test
gunzip -c /var/backups/fig-database/daily/fig_backup_*.sql.gz | \
  psql -d fig_test

# Test 4: Validar datos restaurados
psql -d fig_test -c "SELECT COUNT(*) FROM users;"

# Limpiar BD de prueba
dropdb fig_test
```

---

## REFERENCIAS Y RECURSOS

### Documentación Oficial
- Ubuntu Server: https://ubuntu.com/server/docs
- Nginx: https://nginx.org/en/docs/
- PostgreSQL: https://www.postgresql.org/docs/
- Node.js: https://nodejs.org/en/docs/
- PM2: https://pm2.keymetrics.io/docs

### Herramientas Recomendadas
- SSH Key Manager: `ssh-keygen`
- SSL Certificate: Let's Encrypt (https://letsencrypt.org)
- Uptime Monitoring: StatusPage.io, Uptime.com
- Log Aggregation: ELK Stack, Splunk
- Performance Monitoring: New Relic, DataDog

### Documentos Relacionados
- [ANEXO: Procedimiento de Respaldo de Base de Datos](./ANEXO_Procedimiento_Respaldo_BD.md)
- [Plan de Recuperación ante Desastres (DRP)](./DRP_Plan_Recuperacion.md) [Por crear]
- [Manual de Operación Diaria](./Manual_Operacion_Diaria.md) [Por crear]

---

**Documento preparado por:** Equipo DevOps  
**Última actualización:** 22 de Mayo de 2026  
**Próxima revisión:** 22 de Agosto de 2026  
**Clasificación:** Interno - Técnico  
**Confidencialidad:** Alta

---

### Nota Importante sobre Contraseñas

⚠️ **SEGURIDAD CRÍTICA**: Todos los valores de ejemplo en este documento deben ser reemplazados con contraseñas FUERTES en el ambiente real:
- Usar generador de contraseñas aleatorias
- Mínimo 20 caracteres
- Incluir mayúsculas, minúsculas, números y símbolos
- Almacenar en gestor de contraseñas empresarial (LastPass for Business, 1Password, etc.)
- NO guardar en repositorios de código
- Rotar cada 90 días

