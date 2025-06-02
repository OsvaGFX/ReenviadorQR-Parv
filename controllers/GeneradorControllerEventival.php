<?php

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error fatal: ' . $error['message']
        ]);
    }
});

require_once "../config/database.php";

class GeneradorController
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

    public function obtenerClasificaciones()
    {
        try {
            $query = "SELECT CLASIFICACION_ID, DESCRIPCION 
                      FROM CLASIFICACIONES 
                      WHERE ESTATUS = 'A' AND TIPO_CLASIFICACION = 'C'
                      ORDER BY DESCRIPCION ASC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error al obtener clasificaciones: " . $e->getMessage());
            return false;
        }
    }

    public function buscarFolio(string $folio): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT c.NOMBRE_RECEPTOR
                FROM COMPRA c
                WHERE c.FOLIO = ?
                LIMIT 1
            ");

            $stmt->execute([$folio]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resultado) {
                return [
                    'success' => false,
                    'message' => 'Folio no encontrado. Se creara una nueva compra.'
                ];
            }


            return [
                'success' => true,
                'receptor' => $resultado['NOMBRE_RECEPTOR']
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error en la base de datos: ' . $e->getMessage()
            ];
        }
    }

    public function obtenerBoletosPorFolio(string $folio): array
    {
        try {
            $query = "
            SELECT 
                cla.CLASIFICACION_ID,
                cla.DESCRIPCION AS TIPO_BOLETO,
                COUNT(b.BOLETO_ID) AS CANTIDAD,
                eh.FECHA,
                eh.HORA_INICIO
            FROM COMPRA c
            JOIN COMPRA_DETALLE cd ON c.COMPRA_ID = cd.COMPRA_FK
            JOIN BOLETOS b ON cd.BOLETO_FK = b.BOLETO_ID
            JOIN EVENTOS_HORARIOS eh ON b.EVENTO_HORARIO_FK = eh.EVENTO_HORARIO_ID
            JOIN CLASIFICACIONES cla ON b.CLASIFICACION_FK = cla.CLASIFICACION_ID
            WHERE c.FOLIO = ?
            GROUP BY cla.CLASIFICACION_ID, cla.DESCRIPCION
        ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$folio]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error al obtener boletos: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerDatosCompra(string $folio): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    c.COMPRA_ID,
                    c.FOLIO,
                    c.FECHA_HORA
                FROM COMPRA c
                WHERE c.FOLIO = ?
                LIMIT 1
            ");

            $stmt->execute([$folio]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resultado) {
                return [
                    'success' => false,
                    'error' => 'Folio no encontrado'
                ];
            }

            return [
                'success' => true,
                'data' => $resultado
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Error en la base de datos: ' . $e->getMessage()
            ];
        }
    }

    public function crearCompra(array $datosCompra): array
    {
        try {
            if (empty($datosCompra['folio'])) {
                throw new Exception('El folio es obligatorio');
            }

            if (empty($datosCompra['nombre_receptor'])) {
                throw new Exception('El nombre del receptor es obligatorio');
            }

            if (empty($datosCompra['fecha']) || empty($datosCompra['hora'])) {
                throw new Exception('Fecha y hora son obligatorias');
            }

            if (empty($datosCompra['clasificaciones'])) {
                throw new Exception('Debe seleccionar al menos una clasificación');
            }

            $verificacionEvento = $this->verificarEvento($datosCompra['fecha'], $datosCompra['hora']);
            if (!$verificacionEvento['success'] || !$verificacionEvento['exists']) {
                throw new Exception('La fecha y hora proporcionadas no corresponden a ningún evento existente');
            }

            $clienteId = $this->obtenerClienteId($datosCompra['nombre_receptor']);

            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                INSERT INTO COMPRA (
                    FOLIO, 
                    CLIENTE_FK, 
                    NOMBRE_RECEPTOR, 
                    FECHA_HORA,
                    CORTESIA, 
                    ESTATUS,
                    FACTURA,
                    ENVIADO_MSP
                ) VALUES (?, ?, ?, NOW(), 'N', 'N', 'N', 'N')
            ");

            $stmt->execute([
                $datosCompra['folio'],
                $clienteId,
                $datosCompra['nombre_receptor']
            ]);

            $compraId = $this->conn->lastInsertId();

            foreach ($datosCompra['clasificaciones'] as $clasificacionId => $cantidad) {
                if ($cantidad > 0) {

                    $queryUltimo = "
                        SELECT CONSECUTIVO, PRECIO 
                        FROM BOLETOS 
                        WHERE CLASIFICACION_FK = ? 
                        ORDER BY CONSECUTIVO DESC 
                        LIMIT 1";

                    $stmtUltimo = $this->conn->prepare($queryUltimo);
                    $stmtUltimo->execute([$clasificacionId]);
                    $ultimoRegistro = $stmtUltimo->fetch(PDO::FETCH_ASSOC);

                    $nuevoConsecutivo = $ultimoRegistro ? ($ultimoRegistro['CONSECUTIVO'] + 1) : 1;
                    $precio = $ultimoRegistro ? $ultimoRegistro['PRECIO'] : 0;

                    $stmtBoletos = $this->conn->prepare("
                        INSERT INTO BOLETOS (
                            EVENTO_HORARIO_FK,
                            CLASIFICACION_FK,
                            ESTATUS,
                            CONSECUTIVO,
                            PRECIO,
                            FECHA_HORA_ESCANEO
                        ) VALUES (?, ?, 'V', ?, ?, '0000-00-00 00:00:00')
                    ");

                    $stmtDetalle = $this->conn->prepare("
                    INSERT INTO COMPRA_DETALLE (
                        COMPRA_FK,
                        BOLETO_FK
                    ) VALUES (?, ?)
                ");

                    for ($i = 0; $i < $cantidad; $i++) {
                        $stmtBoletos->execute([
                            $verificacionEvento['evento_horario_id'],
                            $clasificacionId,
                            $nuevoConsecutivo + $i,
                            $precio
                        ]);

                        $boletoId = $this->conn->lastInsertId();

                        $stmtDetalle->execute([$compraId, $boletoId]);
                    }
                }
            }

            $this->conn->commit();

            return [
                'success' => true,
                'compra_id' => $compraId,
                'folio' => $datosCompra['folio'],
                'cliente_id' => $clienteId,
                'evento_horario_id' => $verificacionEvento['evento_horario_id']
            ];

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log("Error en crearCompra: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function obtenerClienteId(string $nombre): int
    {
        $stmt = $this->conn->prepare("
        SELECT CLIENTE_ID 
        FROM CLIENTES 
        WHERE NOMBRE_COMPLETO = ? 
        LIMIT 1
    ");
        $stmt->execute([$nombre]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            throw new Exception("No se encontró un cliente con el nombre exacto: $nombre");
        }

        return (int) $cliente['CLIENTE_ID'];
    }

    public function verificarEvento(string $fecha, string $hora): array
    {
        try {
            $horaComparar = $hora . ':00';

            $stmt = $this->conn->prepare("
                SELECT 
                    EVENTO_HORARIO_ID,
                    COUNT(*) as total 
                FROM EVENTOS_HORARIOS 
                WHERE FECHA = ? 
                AND HORA_INICIO = ?
                LIMIT 1
            ");

            $stmt->execute([$fecha, $horaComparar]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            $existe = ($resultado && $resultado['total'] > 0);

            return [
                'success' => true,
                'exists' => $existe,
                'total' => $existe ? $resultado['total'] : 0,
                'evento_horario_id' => $existe ? $resultado['EVENTO_HORARIO_ID'] : null
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Error en la base de datos: ' . $e->getMessage(),
                'exists' => false,
                'total' => 0,
                'evento_horario_id' => null
            ];
        }
    }

    public function verificarQRAction()
    {
        header('Content-Type: application/json');

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !isset($data['folio'])) {
                throw new Exception('Datos inválidos');
            }

            $folio = $data['folio'];
            $qrPath = $_SERVER['DOCUMENT_ROOT'] . '/ReenviadorQR/QRS/' . $folio . '.png';

            echo json_encode([
                'success' => true,
                'exists' => file_exists($qrPath)
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function guardarQRAction()
    {
        header('Content-Type: application/json');

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST')
                throw new Exception('Método no permitido');
            $folio = $_POST['folio'] ?? '';
            if (empty($folio))
                throw new Exception('Folio no proporcionado');
            if (!isset($_FILES['qr']) || $_FILES['qr']['error'] !== UPLOAD_ERR_OK)
                throw new Exception('Archivo QR no recibido');

            $uploadDir = __DIR__ . '/../../REENVIADORQR/QRS/QRS_Eventival/';

            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $targetPath = $uploadDir . $folio . '.png';

            file_put_contents($targetPath, file_get_contents($_FILES['qr']['tmp_name']));

            echo json_encode([
                'success' => true,
                'message' => 'QR guardado en: ' . $targetPath,
                'url' => '/public/QRS/' . $folio . '.png'
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function obtenerDatosClientePorNombre($nombreReceptor)
    {
        try {
            $query = "SELECT 
                        c.CLIENTE_FK,
                        cli.CLIENTE_ID,
                        cli.CORREO,
                        cli.TELEFONO
                      FROM COMPRA c 
                      JOIN CLIENTES cli ON cli.CLIENTE_ID = c.CLIENTE_FK
                      WHERE c.NOMBRE_RECEPTOR = :nombre_receptor
                      ORDER BY c.FECHA_HORA DESC
                      LIMIT 1";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':nombre_receptor', $nombreReceptor, PDO::PARAM_STR);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                return [
                    'success' => true,
                    'data' => [
                        'cliente_id' => $resultado['CLIENTE_ID'],
                        'correo' => $resultado['CORREO'],
                        'telefono' => $resultado['TELEFONO']
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'No se encontró el cliente con ese nombre de receptor'
                ];
            }

        } catch (PDOException $e) {
            error_log("Error al obtener datos del cliente: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error en la base de datos al buscar cliente'
            ];
        }
    }

    public function buscarReceptores(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $searchTerm = $data['searchTerm'] ?? '';
            $page = $data['page'] ?? 1;
            $perPage = $data['perPage'] ?? 10;

            $offset = ($page - 1) * $perPage;

            $baseQuery = "SELECT 
            NOMBRE_COMPLETO,
            CORREO
        FROM CLIENTES
        WHERE NOMBRE_COMPLETO IS NOT NULL AND NOMBRE_COMPLETO != ''";

            $countQuery = "SELECT COUNT(*) as total FROM CLIENTES WHERE NOMBRE_COMPLETO IS NOT NULL AND NOMBRE_COMPLETO != ''";

            $params = [];
            $countParams = [];

            if (!empty($searchTerm)) {
                $searchTermLike = "%$searchTerm%";
                $whereClause = " AND (NOMBRE_COMPLETO LIKE :search_term OR CORREO LIKE :search_term)";

                $baseQuery .= $whereClause;
                $countQuery .= $whereClause;

                $params[':search_term'] = $searchTermLike;
                $countParams[':search_term'] = $searchTermLike;
            }

            $baseQuery .= " ORDER BY NOMBRE_COMPLETO ASC LIMIT :limit OFFSET :offset";

            $params[':limit'] = $perPage;
            $params[':offset'] = $offset;

            $stmtCount = $this->conn->prepare($countQuery);
            foreach ($countParams as $key => $value) {
                $stmtCount->bindValue($key, $value);
            }
            $stmtCount->execute();
            $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->conn->prepare($baseQuery);
            foreach ($params as $key => $value) {
                if ($key === ':limit' || $key === ':offset') {
                    $stmt->bindValue($key, (int) $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            $stmt->execute();
            $receptores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => [
                    'receptores' => $receptores,
                    'total' => (int) $total
                ]
            ];

        } catch (PDOException $e) {
            error_log("Error al buscar receptores: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al buscar receptores en la base de datos: ' . $e->getMessage()
            ];
        }
    }

    public function handleRequest(): void
    {
        header('Content-Type: application/json');

        try {
            $action = $_GET['action'] ?? '';
            $requestData = json_decode(file_get_contents('php://input'), true) ?? [];

            switch ($action) {
                case 'obtenerClasificaciones':
                    $clasificaciones = $this->obtenerClasificaciones();
                    if ($clasificaciones === false) {
                        throw new Exception('Error al obtener clasificaciones');
                    }
                    echo json_encode([
                        'success' => true,
                        'data' => $clasificaciones
                    ]);
                    break;

                case 'buscarFolio':
                    if (empty($requestData['folio'])) {
                        throw new Exception('Folio no proporcionado');
                    }
                    $resultado = $this->buscarFolio($requestData['folio']);
                    echo json_encode($resultado);
                    break;

                case 'obtenerBoletos':
                    if (empty($requestData['folio'])) {
                        throw new Exception('Folio no proporcionado');
                    }
                    $boletos = $this->obtenerBoletosPorFolio($requestData['folio']);
                    echo json_encode([
                        'success' => true,
                        'data' => $boletos
                    ]);
                    break;

                case 'obtenerDatosCompra':
                    if (empty($requestData['folio'])) {
                        throw new Exception('Folio no proporcionado');
                    }
                    $resultado = $this->obtenerDatosCompra($requestData['folio']);
                    echo json_encode($resultado);
                    break;

                case 'crearCompra':
                    if (empty($requestData['folio']) || empty($requestData['nombre_receptor'])) {
                        throw new Exception('Folio y nombre de receptor son obligatorios');
                    }
                    $resultado = $this->crearCompra($requestData);
                    echo json_encode($resultado);
                    break;

                case 'verificarEvento':
                    if (empty($requestData['fecha']) || empty($requestData['hora'])) {
                        throw new Exception('Fecha y hora son obligatorias');
                    }
                    $resultado = $this->verificarEvento($requestData['fecha'], $requestData['hora']);
                    echo json_encode($resultado);
                    break;

                case 'verificarQR':
                    $this->verificarQRAction();
                    break;

                case 'guardarQR':
                    $this->guardarQRAction();
                    break;

                case 'obtenerDatosClientePorNombre':
                    if (empty($requestData['nombre_receptor'])) {
                        throw new Exception('Nombre del receptor no proporcionado');
                    }
                    $resultado = $this->obtenerDatosClientePorNombre($requestData['nombre_receptor']);
                    echo json_encode($resultado);
                    break;

                case 'buscarReceptores':
                    $resultado = $this->buscarReceptores();
                    echo json_encode($resultado);
                    break;

                default:
                    throw new Exception('Acción no válida');

            }

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}

if (isset($_GET['action'])) {
    (new GeneradorController())->handleRequest();
}