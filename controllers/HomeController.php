<?php
require_once "../helpers/Auth.php";

class HomeController {
    /**
     * Muestra la página principal según el rol del usuario.
     */
    public function index() {
        // Verificar si el usuario está autenticado
        if (!Auth::isAuthenticated()) {
            header("Location: /views/login.html");
            exit;
        }

        // Obtener información del usuario autenticado
        $user = Auth::getUser();

        // Cargar la vista correspondiente según el rol
        switch ($user['rol']) {
            case 'admin':
                $this->adminDashboard($user);
                break;
            case 'taller':
                $this->tallerDashboard($user);
                break;
            case 'cliente':
                $this->clienteDashboard($user);
                break;
            default:
                echo "Rol no reconocido. Contacta al administrador.";
        }
    }

    /**
     * Muestra el panel para administradores.
     */
    private function adminDashboard($user) {
        include "../views/admin_dashboard.php";
    }

    /**
     * Muestra el panel para usuarios de taller.
     */
    private function tallerDashboard($user) {
        include "../views/taller_dashboard.php";
    }

    /**
     * Muestra el panel para clientes.
     */
    private function clienteDashboard($user) {
        include "../views/cliente_dashboard.php";
    }
}
