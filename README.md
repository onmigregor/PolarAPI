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

Una vez configurado, utiliza los siguientes comandos para sincronizar la información desde las bases de datos externas de los clientes:

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
