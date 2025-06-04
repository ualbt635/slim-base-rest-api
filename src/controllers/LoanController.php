<?php
namespace App\Controllers;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
// No estás usando App\Models\Loan aquí porque los SP manejan la lógica
// y devuelven directamente el estado/mensaje o los datos del préstamo.
// Si quisieras usar el modelo Loan, lo harías después de obtener datos del SP si devuelve detalles.

class LoanController
{
    private $mysqli;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Función helper para manejar respuestas JSON
     */
    private function createJsonResponse(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status);
    }

    /**
     * Registra un nuevo préstamo llamando a un procedimiento almacenado.
     * POST /prestamos
     * Body: { "numeroInventario": "string", "idUsuario": "number" }
     */
    public function createLoan(RequestInterface $request, ResponseInterface $response, array $args)
    {
        $body = $request->getParsedBody();

        if (!isset($body['numeroInventario']) || !isset($body['idUsuario'])) {
            return $this->createJsonResponse($response, [
                'status' => 400, // HTTP Bad Request
                'message' => 'Datos requeridos (numeroInventario, idUsuario) faltantes'
            ], 400);
        }

        $numeroInventario = $body['numeroInventario'];
        $idUsuario = (int)$body['idUsuario']; // Asegurar que es entero

        // Llamada al procedimiento almacenado sp_create_loan
        // Asumimos que tu SP se llama 'sp_create_loan' y tiene los parámetros de salida.
        $query = "CALL sp_create_loan(?, ?, @loan_status, @loan_message)";
        $stmt = $this->mysqli->prepare($query);

        if (!$stmt) {
            return $this->createJsonResponse($response, [
                'status' => 500,
                'message' => 'Error preparando llamada al procedimiento: ' . $this->mysqli->error
            ], 500);
        }

        $stmt->bind_param('si', $numeroInventario, $idUsuario);
        
        try {
            $stmt->execute();
            $stmt->close(); // Cerrar el statement antes de obtener los parámetros de salida

            // Obtener los valores de salida del procedimiento
            $selectOutput = $this->mysqli->query("SELECT @loan_status AS status, @loan_message AS message");
            if (!$selectOutput) {
                 return $this->createJsonResponse($response, [
                    'status' => 500,
                    'message' => 'Error obteniendo salida del procedimiento: ' . $this->mysqli->error
                ], 500);
            }
            $output = $selectOutput->fetch_assoc();
            $selectOutput->free();

            if ($output['status'] === 'success') {
                // Opcional: Si el SP no devuelve los detalles del préstamo creado, podrías hacer una consulta aquí
                // para obtenerlos y devolverlos en 'result'.
                // Por ahora, solo devolvemos el mensaje de éxito.
                // Para obtener los datos del último préstamo y devolverlos:
                $selectLoanQuery = "SELECT id, numeroInventario, idUsuario, fechaPrestamo, fechaLimite 
                                    FROM prestamo 
                                    WHERE numeroInventario = ? AND idUsuario = ? 
                                    ORDER BY id DESC LIMIT 1";
                $selectStmt = $this->mysqli->prepare($selectLoanQuery);
                if ($selectStmt) {
                    $selectStmt->bind_param('si', $numeroInventario, $idUsuario);
                    $selectStmt->execute();
                    $loanResult = $selectStmt->get_result();
                    $loanDetails = $loanResult->fetch_assoc();
                    $selectStmt->close();

                    return $this->createJsonResponse($response, [
                        'status' => 201, // HTTP Created
                        'message' => $output['message'],
                        'result' => $loanDetails ?: null // Enviar detalles si se encontraron
                    ], 201);
                } else {
                     return $this->createJsonResponse($response, [
                        'status' => 201, 
                        'message' => $output['message'] . ' (No se pudieron obtener detalles del préstamo)'
                    ], 201);
                }

            } else {
                // Si el SP indica un error (ej. 'User not found', 'Ejemplar is not available')
                // Devolvemos un código de error del cliente (4xx) apropiado.
                // Por ejemplo, si el mensaje contiene "not found", podría ser 404.
                // Si es "not available", podría ser 409 Conflict o 400 Bad Request.
                $httpErrorCode = 400; // Default a Bad Request
                if (strpos(strtolower($output['message']), 'not found') !== false) {
                    $httpErrorCode = 404;
                } elseif (strpos(strtolower($output['message']), 'not available') !== false) {
                    $httpErrorCode = 409; // Conflict, o 422 Unprocessable Entity
                }
                return $this->createJsonResponse($response, [
                    'status' => $httpErrorCode, // O el status que devuelva tu SP si es un código http
                    'message' => $output['message']
                ], $httpErrorCode);
            }

        } catch (\mysqli_sql_exception $e) {
            // Esto captura las excepciones SQL que tu SIGNAL en el SP podría lanzar.
            // El mensaje $e->getMessage() será el MESSAGE_TEXT de tu SIGNAL.
            $httpErrorCode = 400; // Default a Bad Request
            if (strpos(strtolower($e->getMessage()), 'not found') !== false) {
                $httpErrorCode = 404;
            } elseif (strpos(strtolower($e->getMessage()), 'not available') !== false) {
                $httpErrorCode = 409;
            }
             return $this->createJsonResponse($response, [
                'status' => $httpErrorCode,
                'message' => 'Error al ejecutar procedimiento de creación de préstamo: ' . $e->getMessage()
            ], $httpErrorCode);
        }
    }


    /**
     * Registra la devolución de un libro llamando a un procedimiento almacenado.
     * PUT /prestamos/devolver (o la ruta que definas en Slim)
     * Body: { "numeroInventario": "string", "idUsuario": "number" } (o solo numeroInventario si es suficiente)
     */
        public function returnLoan(RequestInterface $request, ResponseInterface $response, array $args)
    {
        $body = $request->getParsedBody();

        // CORRECCIÓN: Asegurarse de que ambos campos requeridos estén presentes
        if (!isset($body['numeroInventario']) || !isset($body['idUsuario'])) { 
            return $this->createJsonResponse($response, [
                'status' => 400,
                'message' => 'Datos requeridos (numeroInventario e idUsuario) faltantes para la devolución' // Mensaje corregido
            ], 400);
        }

        $numeroInventario = $body['numeroInventario'];
        // CORRECCIÓN: Obtener idUsuario del cuerpo y asegurar que es un entero
        $idUsuario = (int)$body['idUsuario']; 

        // CORRECCIÓN: La consulta CALL debe tener dos '?' para los dos parámetros IN
        $query = "CALL sp_return_loan(?, ?, @return_status, @return_message)"; 
        $stmt = $this->mysqli->prepare($query);

        if (!$stmt) {
            return $this->createJsonResponse($response, [
                'status' => 500,
                'message' => 'Error preparando llamada al procedimiento de devolución: ' . $this->mysqli->error
            ], 500);
        }

        // CORRECCIÓN: Enlazar ambos parámetros IN: numeroInventario (string) e idUsuario (integer)
        $stmt->bind_param('si', $numeroInventario, $idUsuario); 
        
        try {
            $stmt->execute();
            $stmt->close();

            $selectOutput = $this->mysqli->query("SELECT @return_status AS status, @return_message AS message");
            if (!$selectOutput) {
                 return $this->createJsonResponse($response, [
                    'status' => 500,
                    'message' => 'Error obteniendo salida del procedimiento de devolución: ' . $this->mysqli->error
                ], 500);
            }
            $output = $selectOutput->fetch_assoc();
            $selectOutput->free();

            if ($output['status'] === 'success') {
                $selectLoanQuery = "SELECT id, numeroInventario, idUsuario, fechaPrestamo, fechaLimite, fechaDevolucion
                                    FROM prestamo 
                                    WHERE numeroInventario = ? AND idUsuario = ?
                                    ORDER BY fechaDevolucion DESC, id DESC LIMIT 1";
                $selectStmt = $this->mysqli->prepare($selectLoanQuery);
                if ($selectStmt) {
                    $selectStmt->bind_param('si', $numeroInventario, $idUsuario); // Usar ambos parámetros aquí también
                    $selectStmt->execute();
                    $loanResult = $selectStmt->get_result();
                    $loanDetails = $loanResult->fetch_assoc();
                    $selectStmt->close();

                    return $this->createJsonResponse($response, [
                        'status' => 200,
                        'message' => $output['message'],
                        'result' => $loanDetails ?: null
                    ], 200);
                } else {
                     return $this->createJsonResponse($response, [
                        'status' => 200,
                        'message' => $output['message'] . ' (No se pudieron obtener detalles del préstamo devuelto)'
                    ], 200);
                }
            } else {
                $httpErrorCode = 400;
                if (strpos(strtolower($output['message']), 'not found') !== false) {
                    $httpErrorCode = 404;
                }
                return $this->createJsonResponse($response, [
                    'status' => $httpErrorCode,
                    'message' => $output['message']
                ], $httpErrorCode);
            }
        } catch (\mysqli_sql_exception $e) {
            $httpErrorCode = 400;
             if (strpos(strtolower($e->getMessage()), 'not found') !== false) {
                $httpErrorCode = 404;
            }
            return $this->createJsonResponse($response, [
                'status' => $httpErrorCode,
                'message' => 'Error al ejecutar procedimiento de devolución: ' . $e->getMessage()
            ], $httpErrorCode);
        }
    }
}
