<?php
// Archivo: public/index.php

require_once "../controllers/LoginController.php";
require_once "../helpers/Auth.php";

// Configurar encabezados para devolver respuestas JSON
header('Content-Type: application/json');
ob_start(); // Capturar salidas no deseadas

// Acción solicitada por el cliente
$action = $_GET['action'] ?? '';

// Función para estructurar respuestas JSON
function jsonResponse($success, $message = '', $data = []) {
    ob_clean(); // Limpia cualquier salida antes de enviar JSON
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

try {
    if ($action === 'login') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            jsonResponse(false, 'El correo y la contraseña son obligatorios.');
        }

        $loginController = new LoginController();

        if ($loginController->login($email, $password)) {
            $user = Auth::getUser();
            if (!$user) {
                jsonResponse(false, 'Error al obtener los datos del usuario.');
            }
            jsonResponse(true, 'Inicio de sesión exitoso.', [
                'usuario_id' => $user['usuario_id'],
                'nombre' => $user['nombre'],
                'email' => $user['email'],
                'rol' => $user['rol'],
                'estatus' => $user['estatus']
            ]);
        } else {
            jsonResponse(false, 'Credenciales incorrectas.');
        }
    } elseif ($action === 'logout') {
        Auth::logout();
        jsonResponse(true, 'Sesión cerrada correctamente.');
    } else {
        jsonResponse(false, 'Acción no reconocida.');
    }
} catch (Exception $e) {
    jsonResponse(false, 'Error en el servidor: ' . $e->getMessage());
}
