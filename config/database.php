<?php
// Archivo: config/database.php

// Cargar las variables de entorno
require_once __DIR__ . '/env_loader.php';

use Dotenv\Dotenv;

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private static $conn = null;

    public function __construct() {
        // Asignar valores desde las variables de entorno
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'nombre_base_datos';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: '';
    }
        

    public function getConnection() {
        if (self::$conn === null) {
            try {
                self::$conn = new PDO(
                    "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                    $this->username,
                    $this->password
                );
                self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $exception) {
                error_log("Error de conexión: " . $exception->getMessage());
                throw new Exception("Error al conectar con la base de datos.");
            }
        }

        return self::$conn;
    }
}
