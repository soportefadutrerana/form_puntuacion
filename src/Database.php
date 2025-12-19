<?php

class Database
{
    private $pdo;
    private $host;
    private $db_name;
    private $user;
    private $password;
    private $port;

    public function __construct()
    {
        $this->host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'localhost';
        $this->port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? 5432;
        $this->db_name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'puntuacion_db';
        $this->user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?? 'postgres';
        $this->password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?? 'postgres_password';

        $this->connect();
    }

    private function connect()
    {
        try {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db_name}";
            $this->pdo = new PDO($dsn, $this->user, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            die('Error de conexión: ' . $e->getMessage());
        }
    }

    /**
     * Obtener todos los items del checklist
     */
    public function getChecklistItems()
    {
        try {
            $query = "SELECT id, codigo, texto, puntos_si, orden 
                      FROM checklist_items 
                      ORDER BY orden ASC";
            
            $stmt = $this->pdo->query($query);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Error al obtener checklist: ' . $e->getMessage());
        }
    }

    /**
     * Insertar un nuevo expediente
     */
    public function insertExpediente($data)
    {
        try {
            // Primero insertar el expediente
            $query = "INSERT INTO expedientes (id_expediente, operario_id, puntuacion, fecha_expediente)
                      VALUES (:id_expediente, :operario_id, :puntuacion, :fecha_expediente)
                      RETURNING id";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':id_expediente', $data['id_expediente'], PDO::PARAM_STR);
            $stmt->bindValue(':operario_id', $data['operario_id'], PDO::PARAM_INT);
            $stmt->bindValue(':puntuacion', $data['puntuacion'], PDO::PARAM_STR);
            $stmt->bindValue(':fecha_expediente', $data['fecha_expediente'] ?? null, PDO::PARAM_STR);

            $stmt->execute();
            $result = $stmt->fetch();
            $expediente_id = $result['id'];

            // Luego insertar las respuestas del checklist
            $this->insertChecklistRespuestas($expediente_id, $data['respuestas'] ?? []);

            return true;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'duplicate key') !== false) {
                throw new Exception('El ID de expediente ya existe');
            }
            throw new Exception('Error al guardar: ' . $e->getMessage());
        }
    }

    /**
     * Insertar respuestas del checklist
     */
    private function insertChecklistRespuestas($expediente_id, $respuestas)
    {
        try {
            $query = "INSERT INTO checklist_respuestas (expediente_id, item_id, marcado) 
                      VALUES (:expediente_id, :item_id, :marcado)
                      ON CONFLICT (expediente_id, item_id) DO UPDATE 
                      SET marcado = EXCLUDED.marcado, updated_at = NOW()";
            
            $stmt = $this->pdo->prepare($query);

            foreach ($respuestas as $item_id => $marcado) {
                $stmt->bindValue(':expediente_id', $expediente_id, PDO::PARAM_INT);
                $stmt->bindValue(':item_id', $item_id, PDO::PARAM_INT);
                $stmt->bindValue(':marcado', $marcado === true || $marcado === '1', PDO::PARAM_BOOL);
                $stmt->execute();
            }
        } catch (PDOException $e) {
            throw new Exception('Error al guardar respuestas: ' . $e->getMessage());
        }
    }

    /**
     * Obtener expediente con sus respuestas
     */
    public function getExpedienteWithRespuestas($expediente_id)
    {
        try {
            $query = "SELECT e.*, cr.item_id, cr.marcado 
                      FROM expedientes e
                      LEFT JOIN checklist_respuestas cr ON e.id = cr.expediente_id
                      WHERE e.id = :id";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':id', $expediente_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Error al obtener expediente: ' . $e->getMessage());
        }
    }

    /**
     * Obtener todos los expedientes
     */
    public function getAllExpedientes()
    {
        try {
            $query = "SELECT e.id, e.id_expediente, o.nombre_completo, e.puntuacion, e.fecha_expediente, e.fecha_creacion
                      FROM expedientes e
                      INNER JOIN operarios o ON e.operario_id = o.id
                      ORDER BY e.fecha_creacion DESC";

            $stmt = $this->pdo->query($query);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Error al obtener expedientes: ' . $e->getMessage());
        }
    }

    /**
     * Calcular puntuación automáticamente
     */
    public function calcularPuntuacion($expediente_id)
    {
        try {
            $query = "SELECT COALESCE(SUM(ci.puntos_si), 0) as total_puntos 
                      FROM checklist_respuestas cr
                      INNER JOIN checklist_items ci ON cr.item_id = ci.id
                      WHERE cr.expediente_id = :expediente_id AND cr.marcado = true";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':expediente_id', $expediente_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result['total_puntos'] ?? 0;
        } catch (PDOException $e) {
            throw new Exception('Error al calcular puntuación: ' . $e->getMessage());
        }
    }

    /**
     * Obtener nombres únicos de expedientes
     */
    public function getNombresUnicos()
    {
        try {
            $query = "SELECT DISTINCT nombre_completo
                      FROM expedientes
                      ORDER BY nombre_completo ASC";

            $stmt = $this->pdo->query($query);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Error al obtener nombres: ' . $e->getMessage());
        }
    }

    /**
     * Obtener operarios activos
     */
    public function getOperariosActivos()
    {
        try {
            $query = "SELECT id, nombre_completo
                      FROM operarios
                      WHERE activo = true
                      ORDER BY nombre_completo ASC";

            $stmt = $this->pdo->query($query);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Error al obtener operarios: ' . $e->getMessage());
        }
    }

    /**
     * Obtener expedientes filtrados por nombre y rango de fechas
     */
    public function getExpedientesFiltrados($nombre_completo, $fecha_inicio, $fecha_fin)
    {
        try {
            $query = "SELECT e.id, e.id_expediente, o.nombre_completo, e.puntuacion, e.fecha_expediente, e.fecha_creacion
                      FROM expedientes e
                      INNER JOIN operarios o ON e.operario_id = o.id
                      WHERE o.nombre_completo = :nombre_completo
                      AND e.fecha_expediente >= :fecha_inicio
                      AND e.fecha_expediente <= :fecha_fin
                      ORDER BY e.fecha_expediente DESC";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':nombre_completo', $nombre_completo, PDO::PARAM_STR);
            $stmt->bindValue(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
            $stmt->bindValue(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Error al filtrar expedientes: ' . $e->getMessage());
        }
    }
}   