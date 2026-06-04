# PolarAPI - Sistema de Analítica Multitenant

Backend construido con Laravel para la consolidación de datos desde múltiples bases de datos de clientes (tenants).

## Requisitos Previos

- Docker y Docker Compose instalados.
- Contenedor de base de datos MySQL 8.0 corriendo (red `appnet`).

## Guía de Instalación (Docker)

Sigue estos pasos para poner en marcha el proyecto desde cero:

### 1. Preparar el entorno
Copia el archivo de ejemplo de variables de entorno (específico para Docker):
```powershell
cp .env.docker-example .env
```

### 2. Levantar el contenedor
Construye e inicia el contenedor de la API:
```powershell
docker-compose up -d --build
```

### 3. Instalar dependencias
Instala los paquetes de PHP mediante Composer dentro del contenedor:
```powershell
docker exec -it polar_api composer install
```

### 4. Generar App Key
```powershell
docker exec -it polar_api php artisan key:generate
```

### 5. Configurar Base de Datos
Ejecuta las migraciones para crear la estructura de las tablas maestras:
```powershell
docker exec -it polar_api php artisan migrate
```

### 6. Cargar Datos Iniciales (Seeders)
Carga los roles, el usuario SuperAdmin, regiones y clientes iniciales:
```powershell
# Roles y SuperAdmin
docker exec -it polar_api php artisan db:seed --class="\Modules\User\Database\Seeders\UserDatabaseSeeder"

# Región (Santiago Centro)
docker exec -it polar_api php artisan db:seed --class="\Modules\Region\Database\Seeders\RegionDatabaseSeeder"

# Clientes (Distribuidora i0512, Zanjili)
docker exec -it polar_api php artisan db:seed --class="\Modules\CompanyRoute\Database\Seeders\CompanyRouteDatabaseSeeder"
```

---

## Sincronización de Datos

Una vez configurado, utiliza los siguientes comandos para sincronizar la información desde las bases de datos externas de los clientes.

> [!IMPORTANT]
> Antes de ejecutar los comandos de sincronización, **debes asegurarte de que las bases de datos de los clientes estén cargadas en el servidor MySQL** (ej: `www_i0512`, `www_zanjili`), de lo contrario los comandos fallarán al intentar conectar con los tenants definidos en el seeder.

### 1. Sincronizar Grupos de Productos
Captura los grupos únicos desde los diversos tenants:
```powershell
docker exec -it polar_api php artisan analytics:sync-groups
```

### 2. Sincronizar Clientes Externos
Cruza la información de los clientes registrados en cada base de datos:
```powershell
docker exec -it polar_api php artisan master-client:sync
```

### 3. Sincronizar Catálogo de Productos
Sincroniza todos los productos de los clientes al catálogo maestro:
```powershell
docker exec -it polar_api php artisan products:sync
```

---

## Acceso Directo

La API es accesible mediante:
- **Localhost**: `http://localhost:8090`
- **VHost**: `http://api.polar.localhost:8090` (Requiere mapeo en archivo `hosts` hacia `127.0.0.1`)

---

## Entorno de Producción y Despliegue (VPS)

### 1. Arquitectura Docker en el VPS
En el servidor VPS (`vmi3342666`), este proyecto se ejecuta bajo el servicio **`apimet`** dentro de `/home/docker-compose.yml`:
- **Directorio de código en VPS:** `/home/apimet_source`
- **Puerto expuesto:** `8092`
- **Contexto de Construcción:** `./apimet_source` (apunta a la carpeta del repositorio actualizado).

### 2. Configuración Automática de Entorno (`.env`)
Para asegurar la resiliencia del entorno y evitar fallos por falta de la clave de encriptación al iniciar o reconstruir los contenedores, se implementó un script de punto de entrada ([entrypoint.sh](file:///c:/xampp/htdocs/POLAR/PolarAPI/entrypoint.sh)):
- **Funcionamiento:** Al iniciar el contenedor, el script verifica la existencia de `.env`. Si no existe, realiza un fallback automático copiando el archivo de producción `.env-main` (`cp .env-main .env`).
- **Instalación de Dependencias:** Si no existe la carpeta `vendor`, ejecuta `composer install` de forma automática.
- **Configuración en Dockerfile:** El [Dockerfile](file:///c:/xampp/htdocs/POLAR/PolarAPI/Dockerfile) copia este script a `/usr/local/bin/entrypoint.sh`, le otorga permisos de ejecución, y lo define como:
  ```dockerfile
  ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
  CMD ["apache2-foreground"]
  ```

### 3. Despliegue Continuo (CI/CD)
El flujo de despliegue está automatizado mediante GitHub Actions en [.github/workflows/deploy.yml](file:///c:/xampp/htdocs/POLAR/PolarAPI/.github/workflows/deploy.yml):
- **Activación:** Se dispara tras cada `git push` a la rama `master`.
- **Flujo de Ejecución:**
  1. Conexión SSH al VPS.
  2. Actualización de `/home/apimet_source` vía `git pull origin master`.
  3. Copia caliente del código al contenedor en ejecución con `docker cp`.
  4. Copia de `.env-main` a `.env` dentro del contenedor.
  5. Ejecución de `composer install --no-dev --optimize-autoloader`.
  6. Migración de bases de datos (`php artisan migrate --force`).
  7. Limpieza de cachés de configuración, rutas y vistas.

> [!NOTE]
> Si realizas cambios estructurales en el `Dockerfile` o en el `entrypoint.sh`, es necesario ingresar al VPS y recompilar el servicio manualmente con:
> ```bash
> cd /home
> docker compose up -d --build apimet
> ```
