<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';

class EnviarCorreoController
{
    private $conn;

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
                case 'enviarCorreo':
                    $this->enviarCorreoAction();
                    break;

                default:
                    throw new Exception('Acción no válida', 400);
            }
        } catch (Exception $e) {
            http_response_code($e->getCode() ?: 500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function enviarCorreoAction()
{
    header('Content-Type: application/json');

    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Método no permitido', 405);
        }

        $requiredFields = ['name', 'email', 'subject', 'qrBase64', 'folio', 'fecha', 'hora'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo $field es requerido", 400);
            }
        }

        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El formato del email no es válido", 400);
        }

          // Obtener el nombre manual si está presente
          $nombreDestinatario = $_POST['name'];
          if (!empty($_POST['manual_name'])) {
              $nombreDestinatario = $_POST['manual_name'];
          }

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'pruebasoti281@gmail.com';
        $mail->Password = 'ziyu teys evpa ubjr';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->SMTPDebug = 0;
        $mail->setFrom('pruebasoti281@gmail.com', 'Boletos Vinicola Parvada');
        $mail->addAddress($_POST['email'], $_POST['name']);
        $mail->addReplyTo('no-reply@dominio.com', 'No Responder');
        $mail->Subject = $_POST['subject'];

        $mail->addAddress($_POST['email'], $nombreDestinatario); 

        $qrData = base64_decode(preg_replace(
            '#^data:image/\w+;base64,#i',
            '',
            $_POST['qrBase64']
        ));
        $mail->addStringEmbeddedImage(
            $qrData,
            'qr',
            'codigo_qr.png',
            'base64',
            'image/png'
        );

        $rutaLogo = __DIR__ . '/../Assets/imagenQR.png';
        if (file_exists($rutaLogo)) {
            $mail->addEmbeddedImage($rutaLogo, 'par', 'logo_parvada.png');
        }

        $mail->isHTML(true);
        $mail->Body = $this->crearCuerpoCorreo([
            'name' => $nombreDestinatario,
            'folio' => $_POST['folio'],
            'fecha' => $_POST['fecha'],
            'hora' => $_POST['hora'],
            'subject' => $_POST['subject']
        ]);
        $mail->AltBody = 'Gracias por tu compra. Adjuntamos tu código QR. Folio: ' . $_POST['folio'];

        if (!$mail->send()) {
            throw new Exception('Error al enviar el correo: ' . $mail->ErrorInfo, 500);
        }

        error_log('Correo enviado exitosamente a: ' . $_POST['email']);

        echo json_encode([
            'success' => true,
            'modal' => true,
            'title' => 'Correo enviado',
            'message' => 'El correo ha sido enviado exitosamente a:<br><strong>' . $_POST['email'] . '</strong>',
            'folio' => $_POST['folio'],
            'icon' => 'success'
        ]);

    } catch (Exception $e) {
        error_log('Error en enviarCorreoAction: ' . $e->getMessage());
        http_response_code($e->getCode() ?: 500);
        echo json_encode([
            'success' => false,
            'modal' => true,
            'title' => 'Error',
            'message' => 'No se pudo enviar el correo: ' . $e->getMessage(),
            'icon' => 'error'
        ]);
    }
}

    private function crearCuerpoCorreo(array $datos): string
    {
        $nombreCliente = htmlspecialchars($datos['name'] ?? '');
        $folioBoleto = htmlspecialchars($datos['folio'] ?? '');
        $fechaEvento = isset($datos['fecha']) ? date('d/m/Y', strtotime($datos['fecha'])) : '';
        $horaEvento = isset($datos['hora']) ? date('H:i', strtotime($datos['hora'])) : '';

        return '
        <html>
            <body>
                <div style="background-color: #CCC; text-align: center; padding: 20px;">

                    <div style="background-color: #FFF; border: 10px solid #CCC; padding: 20px;">
                        <img src="cid:par" style="max-width: 100px;">
                        <h3>Estimado ' . $nombreCliente . ', por medio de este correo te compartimos tu código de entrada al evento</h3>
                        <h3>Favor de no compartir este código.</h3>
                        <br>
                        <!-- Imagen QR posicionada aquí -->
                        <img src="cid:qr" style="max-width: 420px; display: block; margin: 0 auto;">
                        <br>
                        <div style="text-align: center;">
                            <p>' . $folioBoleto . '</p>
                        </div>
                        <br>
                        <p>Preséntalo al llegar al evento.</p>
                        <p style="color:red;">No nos hacemos responsables del uso indebido del código.</p>
                    </div>
                </div>
            </body>
        </html>';
    }
}

if (isset($_GET['action'])) {
    (new EnviarCorreoController())->handleRequest();
}