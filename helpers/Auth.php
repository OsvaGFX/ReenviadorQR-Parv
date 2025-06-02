<?php
// Archivo: helpers/Auth.php

class Auth {
    /**
     * Inicia la sesión si no está activa.
     */
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Verifica si el usuario está autenticado.
     */
    public static function isAuthenticated() {
        self::startSession();
        return isset($_SESSION['user']);
    }

    /**
     * Obtiene la información del usuario logueado.
     */
    public static function getUser() {
        self::startSession();
        return $_SESSION['user'] ?? null;
    }

    /**
     * Valida si el usuario tiene un rol específico.
     * 
     * @param string $role Rol requerido ('admin', 'taller', 'cliente').
     */
    public static function hasRole($role) {
        $user = self::getUser();
        return $user && $user['rol'] === $role;
    }

    /**
     * Cierra la sesión del usuario.
     */
    public static function logout() {
        self::startSession();
        session_unset();
        session_destroy();
    }
}
