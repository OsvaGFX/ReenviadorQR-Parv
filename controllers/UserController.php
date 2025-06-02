<?php
require_once "../config/database.php";
require_once "../models/User.php";

header('Content-Type: application/json');

class UserController {
    private $user;

    public function __construct() {
        $db = (new Database())->getConnection();
        $this->user = new User($db); 
    }

    /**
     * Envía una respuesta JSON.
     */
    private function jsonResponse($success, $message, $data = null) {
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ]);
    }

    public function getUser() {
        $id = $_GET['id'] ?? null;
    
        if (empty($id)) {
            return $this->jsonResponse(false, "El ID del usuario es obligatorio.");
        }
    
        try {
            $user = $this->user->findUserById($id);
    
            if ($user) {
                $this->jsonResponse(true, "Usuario obtenido exitosamente.", $user);
            } else {
                $this->jsonResponse(false, "Usuario no encontrado.");
            }
        } catch (Exception $e) {
            $this->jsonResponse(false, "Error al obtener usuario: " . $e->getMessage());
        }
    }
    

    /**
     * Obtiene todos los usuarios.
     */
    public function getAllUsers() {
        try {
            $usuarios = $this->user->getAllUsers();
            $this->jsonResponse(true, "Usuarios obtenidos exitosamente.", $usuarios);
        } catch (Exception $e) {
            $this->jsonResponse(false, "Error al obtener usuarios: " . $e->getMessage());
        }
    }

    /**
     * Agrega un nuevo usuario.
     */
    public function addUser() {
        $data = json_decode(file_get_contents('php://input'), true);
        $nombre = trim($data['nombre'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $rol = $data['rol'] ?? '';
    
        // Validaciones
        if (empty($nombre) || empty($email) || empty($password) || empty($rol)) {
            return $this->jsonResponse(false, "Todos los campos son obligatorios.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonResponse(false, "Formato de email no válido.");
        }
        $validRoles = ['admin', 'cliente', 'taller'];
        if (!in_array($rol, $validRoles)) {
            return $this->jsonResponse(false, "El rol proporcionado no es válido.");
        }
    
        try {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $result = $this->user->createUser([
                'nombre' => $nombre,
                'email' => $email,
                'password' => $hashedPassword,
                'rol' => $rol,
                'estatus' => 'A',
            ]);
    
            if ($result) {
                $this->jsonResponse(true, "Usuario agregado exitosamente.");
            } else {
                $this->jsonResponse(false, "Error al agregar usuario.");
            }
        } catch (Exception $e) {
            $this->jsonResponse(false, "Error al agregar usuario: " . $e->getMessage());
        }
    }
    
    
    /**
     * Actualiza un usuario existente.
     */
    public function updateUser() {
        // Leer los datos JSON
        $data = json_decode(file_get_contents('php://input'), true);
    
        // Extraer y sanitizar los campos
        $id = trim($data['id'] ?? '');
        $nombre = trim($data['nombre'] ?? '');
        $email = trim($data['email'] ?? '');
        $rol = trim($data['rol'] ?? '');
    
        // Validaciones
        if (empty($id) || empty($nombre) || empty($email) || empty($rol)) {
            return $this->jsonResponse(false, "Todos los campos son obligatorios.");
        }
    
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonResponse(false, "El formato del email no es válido.");
        }
    
        $validRoles = ['admin', 'cliente', 'taller'];
        if (!in_array($rol, $validRoles)) {
            return $this->jsonResponse(false, "El rol proporcionado no es válido. Roles permitidos: admin, cliente, taller.");
        }
    
        try {
            // Actualizar el usuario
            $result = $this->user->updateUser($id, [
                'nombre' => $nombre,
                'email' => $email,
                'rol' => $rol,
            ]);
    
            if ($result) {
                $this->jsonResponse(true, "Usuario actualizado exitosamente.");
            } else {
                $this->jsonResponse(false, "Error al actualizar usuario. Asegúrate de que el ID sea válido.");
            }
        } catch (Exception $e) {
            $this->jsonResponse(false, "Error al actualizar usuario: " . $e->getMessage());
        }
    }
    

    /**
     * Elimina un usuario.
     */
    public function deleteUser() {
        $data = json_decode(file_get_contents('php://input'), true);

        $id = $data['id'] ?? null;
    
        if (empty($id)) {
            return $this->jsonResponse(false, "El ID del usuario es obligatorio.");
        }
    
        try {
            $result = $this->user->deleteUser($id);
    
            if ($result) {
                $this->jsonResponse(true, "Usuario eliminado exitosamente.");
            } else {
                $this->jsonResponse(false, "Error al eliminar usuario.");
            }
        } catch (Exception $e) {
            $this->jsonResponse(false, "Error al eliminar usuario: " . $e->getMessage());
        }
    }

    public function deleteUsers() {
        $data = json_decode(file_get_contents('php://input'), true);
    
        $ids = $data['DELETE_USUARIOS_ID'] ?? [];
    
        if (empty($ids)) {
            return $this->jsonResponse(false, "No se proporcionaron usuarios para eliminar.");
        }
    
        try {
            $success = true;
    
            foreach ($ids as $id) {
                if (!$this->user->deleteUser($id)) {
                    $success = false;
                    break;
                }
            }
    
            if ($success) {
                $this->jsonResponse(true, "Usuarios eliminados exitosamente.");
            } else {
                $this->jsonResponse(false, "Error al eliminar uno o más usuarios.");
            }
        } catch (Exception $e) {
            $this->jsonResponse(false, "Error al eliminar usuarios: " . $e->getMessage());
        }
    }

    public function updateStatus() {
        $data = json_decode(file_get_contents('php://input'), true);
    
        $id = $data['id'] ?? null;
        $estatus = $data['estatus'] ?? null;
    
        if (empty($id) || empty($estatus)) {
            return $this->jsonResponse(false, "El ID del usuario y el nuevo estatus son obligatorios.");
        }
    
        // Verificar si el usuario en sesión está siendo deshabilitado
        session_start();
        $userInSession = $_SESSION['user_id'] ?? null;
    
        if ($id == $userInSession && $estatus == 'B') {
            return $this->jsonResponse(false, "No puedes deshabilitar tu propia cuenta.");
        }
    
        try {
            // Validar que no se deshabiliten todos los usuarios
            if ($estatus == 'B') {
                $activeUsers = $this->user->getActiveUsersCount();
                if ($activeUsers <= 1) {
                    return $this->jsonResponse(false, "No puedes deshabilitar todos los usuarios. Debe haber al menos uno habilitado.");
                }
            }
    
            $result = $this->user->updateStatus($id, $estatus);
    
            if ($result) {
                $this->jsonResponse(true, "Estatus del usuario actualizado exitosamente.");
            } else {
                $this->jsonResponse(false, "Error al actualizar el estatus del usuario.");
            }
        } catch (Exception $e) {
            $this->jsonResponse(false, "Error al actualizar estatus: " . $e->getMessage());
        }
    }

    public function updateStatusBox() {
        $data = json_decode(file_get_contents('php://input'), true);
        $usuarios = $data['usuarios'] ?? [];
    
        if (empty($usuarios)) {
            return $this->jsonResponse(false, "No se proporcionaron usuarios para actualizar el estatus.");
        }
    
        session_start();
        $userInSession = $_SESSION['user_id'] ?? null;
    
        try {
            // Verificar si se intenta deshabilitar el usuario en sesión
            foreach ($usuarios as $usuario) {
                if ($usuario['id'] == $userInSession && $usuario['estatus'] == 'B') {
                    return $this->jsonResponse(false, "No puedes deshabilitar tu propia cuenta.");
                }
            }
    
            // Validar que no se deshabiliten todos los usuarios
            $activeUsers = $this->user->getActiveUsersCount();
            $toDisable = array_filter($usuarios, function($u) {
                return $u['estatus'] == 'B';
            });
    
            if ($activeUsers - count($toDisable) <= 0) {
                return $this->jsonResponse(false, "No puedes deshabilitar todos los usuarios. Debe haber al menos uno habilitado.");
            }
    
            foreach ($usuarios as $usuario) {
                $id = $usuario['id'];
                $estatus = $usuario['estatus'];
    
                if (!$this->user->updateStatus($id, $estatus)) {
                    return $this->jsonResponse(false, "Error al actualizar el estatus del usuario con ID $id.");
                }
            }
    
            $this->jsonResponse(true, "Estatus de usuarios actualizado exitosamente.");
        } catch (Exception $e) {
            $this->jsonResponse(false, "Error al actualizar estatus: " . $e->getMessage());
        }
    }
    
}

// Crear una instancia del controlador y manejar acciones basadas en 'action'
$action = $_GET['action'] ?? null;
$controller = new UserController();

if (method_exists($controller, $action)) {
    $controller->$action();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Acción no válida.'
    ]);
}
