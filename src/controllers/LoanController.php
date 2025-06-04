<?php
namespace App\Controllers;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use App\Models\Loan;

class LoanController
{
    private $mysqli;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Función helper para manejar respuestas JSON
     * @param ResponseInterface $response
     * @param array $data
     * @return ResponseInterface
     */
    // Helper function to handle the response
    function createJsonResponse(ResponseInterface $response, array $data): ResponseInterface
    {
        $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->getBody()->write(json_encode($data));
        return $response;
    }

    /**
     * Registra un nuevo préstamo.
     * POST /loans
     * @param { "numeroInventario": "string", "idUsuario": "number" } en el cuerpo de la petición
     * @return 201 (Loan created successfully) o 400 (Required data is missing)
     */
    public function createLoan(RequestInterface $request, ResponseInterface $response, array $args)
    {
        $body = $request->getParsedBody();

        // Validar campos requeridos
        if (!isset($body['numeroInventario']) || !isset($body['idUsuario'])) {
            return createJsonResponse($response->withStatus(400), [
                'status' => 400,
                'message' => 'Required data (numeroInventario, idUsuario) is missing'
            ]);
        }

        $numeroInventario = $body['numeroInventario'];
        $idUsuario = $body['idUsuario'];

        // Llama al procedimiento almacenado para crear un préstamo
        $query = "CALL sp_create_loan(?, ?, @loan_status, @loan_message)";
        $stmt = $this->mysqli->prepare($query);
        if (!$stmt) {
            return createJsonResponse($response->withStatus(500), [
                'status' => 500,
                'message' => 'Error preparing query: ' . $this->mysqli->error
            ]);
        }
        $stmt->bind_param('si', $numeroInventario, $idUsuario);
        $result = $stmt->execute();

        if ($result) {
            $selectOutput = $this->mysqli->query("SELECT @loan_status AS status, @loan_message AS message");
            $output = $selectOutput->fetch_assoc();

            if ($output['status'] === 'success') {
                // Obtener detalles del préstamo recién creado (asumiendo que el SP los devuelve)
                $selectLoanQuery = "SELECT id, numeroInventario, idUsuario, fechaPrestamo, fechaLimite, fechaDevolucion FROM prestamo WHERE numeroInventario = ? AND idUsuario = ? ORDER BY id DESC LIMIT 1";
                $selectStmt = $this->mysqli->prepare($selectLoanQuery);
                if ($selectStmt) {
                    $selectStmt->bind_param('si', $numeroInventario, $idUsuario);
                    $selectStmt->execute();
                    $loanResult = $selectStmt->get_result();
                    $loan = $loanResult->fetch_assoc();
                } else {
                    $loan = ['numeroInventario' => $numeroInventario, 'idUsuario' => $idUsuario];
                }

                return createJsonResponse($response->withStatus(201), [
                    'status' => 201,
                    'message' => 'Loan created successfully',
                    'result' => [
                        'id' => $loan['id'] ?? null,
                        'numeroInventario' => $loan['numeroInventario'],
                        'idUsuario' => $loan['idUsuario'],
                        'fechaPrestamo' => $loan['fechaPrestamo'] ?? null,
                        'fechaLimite' => $loan['fechaLimite'] ?? null,
                        'fechaDevolucion' => $loan['fechaDevolucion'] ?? null
                    ]
                ]);
            } else {
                return createJsonResponse($response->withStatus(400), [
                    'status' => 400,
                    'message' => $output['message']
                ]);
            }
        } else {
            return createJsonResponse($response->withStatus(500), [
                'status' => 500,
                'message' => 'Error executing loan creation procedure: ' . $this->mysqli->error
            ]);
        }
    }

    /**
     * Registra la devolución de un libro.
     * PUT /loans/return
     * @param { "numeroInventario": "string", "idUsuario": "number" } en el cuerpo de la petición
     * @return 200 (Book returned successfully) o 400 (Required data is missing)
     */
    public function returnLoan(RequestInterface $request, ResponseInterface $response, array $args)
    {
        $body = $request->getParsedBody();

        // Validar campos requeridos
        if (!isset($body['numeroInventario']) || !isset($body['idUsuario'])) {
            return createJsonResponse($response->withStatus(400), [
                'status' => 400,
                'message' => 'Required data (numeroInventario, idUsuario) is missing'
            ]);
        }

        $numeroInventario = $body['numeroInventario'];
        $idUsuario = $body['idUsuario'];

        // Llama al procedimiento almacenado para registrar la devolución
        $query = "CALL sp_return_loan(?, ?, @return_status, @return_message)";
        $stmt = $this->mysqli->prepare($query);
        if (!$stmt) {
            return createJsonResponse($response->withStatus(500), [
                'status' => 500,
                'message' => 'Error preparing query: ' . $this->mysqli->error
            ]);
        }
        $stmt->bind_param('si', $numeroInventario, $idUsuario);
        $result = $stmt->execute();

        if ($result) {
            $selectOutput = $this->mysqli->query("SELECT @return_status AS status, @return_message AS message");
            $output = $selectOutput->fetch_assoc();

            if ($output['status'] === 'success') {
                // Obtener detalles del préstamo actualizado después de la devolución
                $selectLoanQuery = "SELECT id, numeroInventario, idUsuario, fechaPrestamo, fechaLimite, fechaDevolucion FROM prestamo WHERE numeroInventario = ? AND idUsuario = ? ORDER BY id DESC LIMIT 1";
                $selectStmt = $this->mysqli->prepare($selectLoanQuery);
                if ($selectStmt) {
                    $selectStmt->bind_param('si', $numeroInventario, $idUsuario);
                    $selectStmt->execute();
                    $loanResult = $selectStmt->get_result();
                    $loan = $loanResult->fetch_assoc();
                } else {
                    $loan = ['numeroInventario' => $numeroInventario, 'idUsuario' => $idUsuario];
                }

                return createJsonResponse($response->withStatus(200), [
                    'status' => 200,
                    'message' => 'Book returned successfully',
                    'result' => [
                        'id' => $loan['id'] ?? null,
                        'numeroInventario' => $loan['numeroInventario'],
                        'idUsuario' => $loan['idUsuario'],
                        'fechaPrestamo' => $loan['fechaPrestamo'] ?? null,
                        'fechaLimite' => $loan['fechaLimite'] ?? null,
                        'fechaDevolucion' => $loan['fechaDevolucion'] ?? null
                    ]
                ]);
            } else {
                return createJsonResponse($response->withStatus(400), [
                    'status' => 400,
                    'message' => $output['message']
                ]);
            }
        } else {
            return createJsonResponse($response->withStatus(500), [
                'status' => 500,
                'message' => 'Error executing loan return procedure: ' . $this->mysqli->error
            ]);
        }
    }
}