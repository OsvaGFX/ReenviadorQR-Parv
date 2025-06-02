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

            $mail->setFrom('pruebasoti281@gmail.com', 'Boletos Eventival');
            $mail->addAddress($_POST['email'], $_POST['name']);
            $mail->addReplyTo('no-reply@dominio.com', 'No Responder');
            $mail->Subject = $_POST['subject'];

            // Adjuntar imagen QR
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

            // Logos fijos
            $rutaLogo = __DIR__ . '/../Assets/logos/Logo Eventival color min.png';
            if (file_exists($rutaLogo)) {
                $mail->addEmbeddedImage($rutaLogo, 'par', 'logo_parvada.png');
            }

            // Imagen del evento
            $logoEventoPath = $this->obtenerLogoEvento($_POST['folio']);
            if ($logoEventoPath) {
                $mail->addEmbeddedImage($logoEventoPath, 'logoBarra', basename($logoEventoPath));
            }

            // Crear cuerpo del correo
            $mail->isHTML(true);
            $mail->Body = $this->crearCuerpoCorreo([
                'name' => $_POST['name'],
                'folio' => $_POST['folio'],
                'fecha' => $_POST['fecha'],
                'hora' => $_POST['hora'],
                'subject' => $_POST['subject'],
                'eventoLogo' => isset($logoEventoPath)
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
                'icon' => 'success' // Puede ser 'success', 'error', 'warning', 'info'
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


    private function obtenerLogoEvento(string $folio): ?string
    {
        try {
            $query = "
        SELECT e.IMG_LOGO
        FROM COMPRA c
        JOIN COMPRA_DETALLE cd ON c.COMPRA_ID = cd.COMPRA_FK
        JOIN BOLETOS b ON cd.BOLETO_FK = b.BOLETO_ID
        JOIN EVENTOS_HORARIOS eh ON b.EVENTO_HORARIO_FK = eh.EVENTO_HORARIO_ID
        JOIN EVENTOS e ON eh.EVENTO_FK = e.EVENTO_ID
        WHERE c.FOLIO = ?
        LIMIT 1
    ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$folio]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado && !empty($resultado['IMG_LOGO'])) {
                // Suponiendo que la ruta es relativa a la raíz del servidor
                $rutaAbsoluta = $_SERVER['DOCUMENT_ROOT'] . $resultado['IMG_LOGO'];

                if (file_exists($rutaAbsoluta)) {
                    return $rutaAbsoluta;
                }
            }

            return null;

        } catch (PDOException $e) {
            error_log('Error al obtener logo del evento: ' . $e->getMessage());
            return null;
        }
    }

    private function obtenerInfoBoletos(string $folioBoleto): array
    {
        $numBoletos = 0;
        $mensajeBoleto = "Este código es para un boleto comprado, favor de no compartirlo con nadie.";

        try {
            $query = "
            SELECT COUNT(*) AS BOLETOS
            FROM COMPRA C
            JOIN COMPRA_DETALLE CD ON C.COMPRA_ID = CD.COMPRA_FK
            WHERE C.FOLIO = :folio
        ";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':folio', $folioBoleto);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado && isset($resultado['BOLETOS'])) {
                $numBoletos = (int) $resultado['BOLETOS'];
            }

            if ($numBoletos > 1) {
                $mensajeBoleto = "Este código es para los $numBoletos boletos comprados, compártelo sólo con las personas que entrarán contigo al evento. Si tu compra es individual, favor de no compartirlo con nadie.";
            }

            return [
                'mensaje' => $mensajeBoleto,
                'cantidad' => $numBoletos
            ];

        } catch (PDOException $e) {
            error_log('Error al obtener información de boletos: ' . $e->getMessage());
            return [
                'mensaje' => $mensajeBoleto,
                'cantidad' => $numBoletos
            ];
        }
    }

    private function crearCuerpoCorreo(array $datos): string
    {
        $nombreCliente = htmlspecialchars($datos['name'] ?? '');
        $folioBoleto = htmlspecialchars($datos['folio'] ?? '');
        $fechaEvento = isset($datos['fecha']) ? date('d/m/Y', strtotime($datos['fecha'])) : '';
        $horaEvento = isset($datos['hora']) ? date('H:i', strtotime($datos['hora'])) : '';
        $eventoLogoHtml = '';

        $infoBoletos = $this->obtenerInfoBoletos($folioBoleto);
        $mensajeBoleto = $infoBoletos['mensaje'];

        if (!empty($datos['eventoLogo'])) {
            $eventoLogoHtml = '<img src="cid:logoBarra" style="max-width: 420px; display: block; margin: 10px auto;">';
        }

        return '
<html>
    <body>
        <div style="background-color: #CCC; text-align: center; padding: 20px;">
            <img src="cid:par" style="max-width: 420px;">
            <div style="background-color: #FFF; border: 10px solid #CCC; padding: 20px;">
                <h3>Estimado ' . $nombreCliente . ', por medio de este correo te compartimos tu código de entrada al evento</h3>
                <img src="cid:logoBarra" style="max-width: 420px;">
                ' . $eventoLogoHtml . '
                <h3>Favor de no compartir este código.</h3>
                <br>
                <img src="cid:qr" style="max-width: 420px; display: block; margin: 0 auto;">
                <br>
                <div style="text-align: center;">
                <h3>' . $mensajeBoleto . ' </h3>
                <img src="cid:logoBarra" style="max-width: 420px;">
                    <p>' . $folioBoleto . '</p>
                    <p>Fecha del evento: ' . $fechaEvento . ' a las ' . $horaEvento . '</p>
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