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

    public function insertExpediente($id_expediente, $nombre_completo, $puntuacion)
    {
        try {
            $query = "INSERT INTO expedientes (id_expediente, nombre_completo, puntuacion) 
                      VALUES (:id_expediente, :nombre_completo, :puntuacion)";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([
                ':id_expediente' => $id_expediente,
                ':nombre_completo' => $nombre_completo,
                ':puntuacion' => $puntuacion
            ]);

            return true;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'duplicate key') !== false) {
                throw new Exception('El ID de expediente ya existe');
            }
            throw new Exception('Error al guardar: ' . $e->getMessage());
        }
    }

    public function getAllExpedientes()
    {
        try {
            $query = "SELECT id, id_expediente, nombre_completo, puntuacion, fecha_creacion 
                      FROM expedientes 
                      ORDER BY fecha_creacion DESC";
            
            $stmt = $this->pdo->query($query);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Error al obtener expedientes: ' . $e->getMessage());
        }
    }

    public function getExpedienteById($id)
    {
        try {
            $query = "SELECT * FROM expedientes WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception('Error al obtener expediente: ' . $e->getMessage());
        }
    }

    public function deleteExpediente($id)
    {
        try {
            $query = "DELETE FROM expedientes WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            throw new Exception('Error al eliminar: ' . $e->getMessage());
        }
    }
}
