-- Crear tabla de expedientes
CREATE TABLE IF NOT EXISTS expedientes (
    id SERIAL PRIMARY KEY,
    id_expediente VARCHAR(50) NOT NULL UNIQUE,
    nombre_completo VARCHAR(255) NOT NULL,
    puntuacion NUMERIC(5, 2) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Crear índice para búsquedas rápidas
CREATE INDEX idx_id_expediente ON expedientes(id_expediente);
