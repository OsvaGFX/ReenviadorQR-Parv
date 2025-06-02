<?php
require_once "../config/database.php";
require_once "../models/User.php";
require_once "../helpers/Auth.php";

header('Content-Type: application/json');

class LoginController {
    private $db;
    private $user;

    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->user = new User($this->db);
    }

    /**
     * Envía una respuesta JSON con el formato adecuado.
     */
    private function jsonResponse($success, $message, $data = null) {
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Maneja el inicio de sesión de un usuario.
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
    
            if (empty($email) || empty($password)) {
                return $this->jsonResponse(false, 'El correo y la contraseña son obligatorios.');
            }
    
            
            try {
                $user = $this->user->findUserByEmail($email);

                if ($user) {
                    
                    // Continúa accediendo a otros campos según necesites
                } else {
                    echo "No se encontró un usuario con ese correo electrónico.";
                }
                //return $this->jsonResponse(false,  $user['PASSWORD']);


                // Verificar que el usuario existe y está activo
                
                //return $this->jsonResponse(false, $user['password']);


                if ($user && password_verify($password, $user['password'])) {
                    if ($user['estatus'] !== 'A') {
                        return $this->jsonResponse(false, 'El usuario no está activo. Contacta al administrador.');
                    }
    
                    Auth::startSession();
                    $_SESSION['user'] = [
                        'usuario_id' => $user['usuario_id'],
                        'nombre' => $user['nombre'],
                        'email' => $user['email'],
                        'rol' => $user['rol'],
                        'estatus' => $user['estatus']
                    ];
    
                    return $this->jsonResponse(true, 'Inicio de sesión exitoso.', $_SESSION['user']);
                } else {
                    return $this->jsonResponse(false, 'Credenciales incorrectas.');
                }
            } catch (Exception $e) {
                return $this->jsonResponse(false, 'Error en el servidor: ' . $e->getMessage());
            }
        }
    }
    

    /**
     * Maneja el registro de un usuario.
     */
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $nombre = $_POST['nombre'] ?? '';
            $rol = $_POST['rol'] ?? 'cliente';

            if (empty($email) || empty($password) || empty($nombre)) {
                return $this->jsonResponse(false, 'Todos los campos son obligatorios.');
            }

            try {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $query = "INSERT INTO Usuarios (email, password, nombre, rol, estatus, fecha_creacion) 
                          VALUES (:email, :password, :nombre, :rol, 'A', NOW())";

                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':rol', $rol);

                if ($stmt->execute()) {
                    return $this->jsonResponse(true, 'Usuario registrado exitosamente.');
                } else {
                    return $this->jsonResponse(false, 'Error al registrar el usuario.');
                }
            } catch (Exception $e) {
                return $this->jsonResponse(false, 'Error en el servidor: ' . $e->getMessage());
            }
        }
    }

    /**
     * Maneja el cierre de sesión del usuario.
     */
    public function logout() {
        Auth::logout();
        $this->jsonResponse(true, 'Sesión cerrada exitosamente.');
    }
}

// Crear una instancia del controlador
$controller = new LoginController();

// Manejar acciones basadas en el parámetro 'action'
$action = $_GET['action'] ?? 'login'; 
if (method_exists($controller, $action)) {
    $controller->$action();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Acción no válida.'
    ]);
}
