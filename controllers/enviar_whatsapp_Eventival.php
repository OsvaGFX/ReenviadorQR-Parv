<?php

require_once __DIR__ . '/../config/database.php';

class EnviarWhatsappController
{
    private $conn;
    private $ultramsg_token = 'dtiw16q3n81zxtsw'; // Token de Ultramsg.com
    private $instance_id = '113494'; // ID de tu instancia

    public function __construct()
    {
        try {
            $this->conn = (new Database())->getConnection();
        } catch (PDOException $e) {
            throw new PDOException("Error al conectar con la base de datos: " . $e->getMessage());
        }
    }

    public function handleRequest()
    {
        header('Content-Type: application/json');

        try {
            $action = $_GET['action'] ?? '';

            switch ($action) {
                case 'enviarWhatsapp':
                    $this->enviarWhatsappAction();
                    break;

                default:
                    throw new Exception('Acción no válida', 400);
            }
        } catch (Exception $e) {
            http_response_code($e->getCode() ?: 500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTrace() // Solo para desarrollo, quitar en producción
            ]);
        }
    }

    public function enviarWhatsappAction()
    {
        header('Content-Type: application/json');

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido', 405);
            }

            // Obtener datos del POST
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $input = $_POST; // Fallback a POST normal
            }

            $requiredFields = ['name', 'telefono', 'qrBase64', 'folio', 'fecha', 'hora'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    throw new Exception("El campo $field es requerido", 400);
                }
            }

            // Preparar los datos
            $nombre = $input['name'];
            $telefono = $this->formatPhoneNumber($input['telefono']);
            $folio = $input['folio'];
            $fecha = $input['fecha'];
            $hora = $input['hora'];
            $qrBase64 = $input['qrBase64'];

            if (!str_starts_with($qrBase64, 'data:image')) {
                $qrBase64 = 'data:image/png;base64,' . $qrBase64;
            }
            

            // Guardar el QR temporalmente
            $qrData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $qrBase64));
            if ($qrData === false) {
                throw new Exception('Formato de imagen QR inválido', 400);
            }

            // Crear el mensaje
            $texto = "Hola $nombre, este es tu acceso al evento del día $fecha a las $hora hrs. ¡Disfrútalo!";

            // Enviar por WhatsApp con el QR en base64 directamente
            $response = $this->enviarWhatsapp($telefono, $qrBase64, $texto);

            // Verificar respuesta
            if ($response['sent'] !== true) {
                throw new Exception('La API de WhatsApp no pudo enviar el mensaje: ' . json_encode($response));
            }

            error_log('WhatsApp enviado exitosamente a: ' . $telefono);

            echo json_encode([
                'success' => true,
                'modal' => true,
                'title' => 'Mensaje enviado',
                'message' => 'El mensaje ha sido enviado exitosamente al número:<br><strong>' . $telefono . '</strong>',
                'folio' => $folio,
                'icon' => 'success',
                'response' => $response // Solo para depuración
            ]);

        } catch (Exception $e) {
            error_log('Error en enviarWhatsappAction: ' . $e->getMessage());
            http_response_code($e->getCode() ?: 500);
            echo json_encode([
                'success' => false,
                'modal' => true,
                'title' => 'Error',
                'message' => 'No se pudo enviar el mensaje: ' . $e->getMessage(),
                'icon' => 'error',
                'error_details' => $e->getTraceAsString() // Solo para desarrollo
            ]);
        }
    }

    private function formatPhoneNumber($phone)
    {
        // Eliminar todo lo que no sea dígito
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Si no empieza con 52, agregarlo
        if (substr($phone, 0, 2) !== '52') {
            $phone = '52' . ltrim($phone, '0');
        }
        
        return $phone;
    }

    private function enviarWhatsapp($telefono, $qrBase64, $caption)
    {
        $curl = curl_init();

        $postData = [
            'token' => $this->ultramsg_token,
            'to' => $telefono,
            'image' => $qrBase64,
            'caption' => $caption
        ];

        curl_setopt_array($curl, [                
            CURLOPT_URL => "https://api.ultramsg.com/instance{$this->instance_id}/messages/image",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_HTTPHEADER => [
                "content-type: application/x-www-form-urlencoded"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($err) {
            throw new Exception("cURL Error: " . $err);
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . $response);
        }

        if ($httpCode !== 200 || !isset($decodedResponse['sent'])) {
            throw new Exception("API Error: " . $response);
        }

        return $decodedResponse;
    }
}

if (isset($_GET['action'])) {
    (new EnviarWhatsappController())->handleRequest();
}