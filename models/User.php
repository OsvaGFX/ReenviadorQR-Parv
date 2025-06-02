<?php
// Archivo: models/User.php

class User {
    private $conn;
    private $table = "USUARIOS";

    public $usuario_id;
    public $nombre;
    public $email;
    public $password;
    public $rol;
    public $estatus;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Encuentra un usuario por su correo electrónico.
     */
    public function findUserByEmail($email) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE email = :email LIMIT 1 ";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al buscar usuario por email: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Crea un nuevo usuario.
     */
    public function createUser($data) {
        try {
            $query = "INSERT INTO " . $this->table . " (nombre, email, password, rol, estatus, fecha_creacion) 
                      VALUES (:nombre, :email, :password, :rol, :estatus, NOW())";
            $stmt = $this->conn->prepare($query);
    
            $stmt->bindParam(":nombre", $data['nombre']);
            $stmt->bindParam(":email", $data['email']);
            $stmt->bindParam(":password", $data['password']); // Asegúrate de que esté encriptada
            $stmt->bindParam(":rol", $data['rol']);
            $stmt->bindParam(":estatus", $data['estatus']);
    
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error al crear usuario: " . $e->getMessage());
            return false;
        }
    }
    
    

    /**
     * Obtiene todos los usuarios de la base de datos.
     *
     * @return array Arreglo con todos los usuarios.
     */
    public function getAllUsers() {
        try {
            $query = "SELECT usuario_id, nombre, email, rol, estatus FROM " . $this->table;
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC); // Retornar todos los registros como un arreglo asociativo
        } catch (PDOException $e) {
            error_log("Error al obtener usuarios: " . $e->getMessage());
            return [];
        }
    }

    public function updateStatus($id, $estatus) {
        try {
            $query = "UPDATE " . $this->table . " SET estatus = :estatus WHERE usuario_id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':estatus', $estatus);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error al actualizar estatus: " . $e->getMessage());
            return false;
        }
    }    
    

    public function findUserById($id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE usuario_id = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al buscar usuario por ID: " . $e->getMessage());
            return null;
        }
    }
    

    /**
     * Actualiza un usuario existente.
     */
    public function updateUser($id, $data) {
        try {
            $query = "UPDATE " . $this->table . " 
                      SET nombre = :nombre, email = :email, rol = :rol
                      WHERE usuario_id = :id";
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(":nombre", $data['nombre']);
            $stmt->bindParam(":email", $data['email']);
            $stmt->bindParam(":rol", $data['rol']);
            $stmt->bindParam(":id", $id);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error al actualizar usuario: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina un usuario por su ID.
     */
    public function deleteUser($id) {
        try {
            $query = "UPDATE " . $this->table . " SET estatus = :estatus WHERE usuario_id = :id";
            $stmt = $this->conn->prepare($query);
            
            $estatus = 'B'; 
            $stmt->bindParam(':estatus', $estatus);
            $stmt->bindParam(':id', $id);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error al actualizar estatus del usuario: " . $e->getMessage());
            return false;
        }
    }
    
    

    /**
     * Hashea las contraseñas existentes en la base de datos.
     */
    public function hashExistingPasswords() {
        try {
            $query = "SELECT usuario_id, password FROM Usuarios";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $users = $stmt->fetchAll();

            foreach ($users as $user) {
                if (!password_get_info($user['password'])['algo']) {
                    $hashedPassword = password_hash($user['password'], PASSWORD_BCRYPT);

                    $updateQuery = "UPDATE Usuarios SET password = :password WHERE usuario_id = :usuario_id";
                    $updateStmt = $this->conn->prepare($updateQuery);
                    $updateStmt->bindParam(':password', $hashedPassword);
                    $updateStmt->bindParam(':usuario_id', $user['usuario_id']);
                    $updateStmt->execute();
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Error al hashear contraseñas existentes: " . $e->getMessage());
            return false;
        }
    }

    public function getActiveUsersCount() {
        try {
            $query = "SELECT COUNT(*) as active_count FROM " . $this->table . " WHERE estatus = 'A'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['active_count'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error al contar usuarios activos: " . $e->getMessage());
            return 0;
        }
    }
    
}
