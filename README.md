# Formulario de Puntuación Operarios Utrebyte

## [REQUISITOS]

- Docker
- Docker Compose

## [INSTALACIÓN Y CONFIGURACIÓN]

### [1] Ejecutar composer y crear .env

```bash
composer install
```

### [2] Levantar los contenedores

Ejecuta en PowerShell o Terminal:

```bash
docker-compose up --build
```

Este comando realiza lo siguiente:
- Construye la imagen de PHP con Apache
- Descarga la imagen de PostgreSQL 15
- Inicia ambos contenedores
- Importa automáticamente el esquema de la base de datos desde schema.sql

### [3] Acceder a la aplicación web

Una vez que los contenedores estén ejecutándose, abre en tu navegador:

```
http://localhost
```

Deberías ver el formulario de puntuación funcionando correctamente.

### [5] Detener los contenedores

Para parar los contenedores sin eliminarlos:

```bash
docker-compose stop
```

Para eliminar todo (contenedores, imágenes, volúmenes):

```bash
docker-compose down
```

Para reiniciar después de una parada:

```bash
docker-compose start
```

## [VISUALIZAR LA BASE DE DATOS CON POSTGRESQL CLI]

Para conectarte a PostgreSQL y consultar la base de datos:

### [Acceder a la shell de PostgreSQL]

```bash
docker exec -it puntuacion_postgres psql -U postgres -d puntuacion_db
```

### [Comandos útiles en psql]

Una vez conectado, puedes usar los siguientes comandos:

```sql
-- Listar todas las tablas
\dt

-- Ver la estructura de una tabla
\d expedientes

-- Consultar todos los registros
SELECT * FROM expedientes;

-- Consultar con formato mejorado
SELECT * FROM expedientes \gx

-- Contar registros
SELECT COUNT(*) FROM expedientes;

-- Salir de psql
\q
```

## [CONFIGURACIÓN DE LA BASE DE DATOS]

- Host: localhost
- Puerto: 5432
- Usuario: postgres
- Contraseña: postgres_password
- Base de datos: puntuacion_db

## [TROUBLESHOOTING]

### [Puerto 80 o 5432 ya están en uso]

Si el puerto está ocupado, modifica docker-compose.yml:

```yaml
php:
  ports:
    - "8080:80"  # Cambiar 80 al puerto que desees

postgres:
  ports:
    - "5433:5432"  # Cambiar 5432 al puerto que desees
```

### [Error de conexión a PostgreSQL]

Espera unos segundos a que PostgreSQL esté completamente listo. El docker-compose.yml tiene un healthcheck configurado.

## [NOTAS IMPORTANTES]

- Los datos se persisten en volúmenes de Docker, así que aunque los contenedores se detengan, los datos de PostgreSQL se mantienen.
- La base de datos se inicializa automáticamente con el esquema definido en schema.sql.
- Si necesitas reset total de la base de datos, ejecuta: docker-compose down -v
