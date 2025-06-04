<?php
namespace App\Controllers;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use App\Models\Book;

class BookController
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
    private function createJsonResponse(ResponseInterface $response, array $data): ResponseInterface
    {
        $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->getBody()->write(json_encode($data));
        return $response;
    }

    /**
     * Obtiene todos los libros.
     * GET /books
     * @return 200 (Lista de libros) o 204 (No books found)
     */
    public function getAllBooks(RequestInterface $request, ResponseInterface $response, array $args)
    {
        $query = 'SELECT * FROM libro';
        $stmt = $this->mysqli->prepare($query);
        if (!$stmt) {
            return $this->createJsonResponse($response->withStatus(500), [
                'status' => 500,
                'message' => 'Error preparing query: ' . $this->mysqli->error
            ]);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $books = [];
            while ($row = $result->fetch_assoc()) {
                $book = new Book(
                    $row['id'],
                    $row['titulo'],
                    $row['autor'],
                    $row['editorial'],
                    $row['publicadoEn'],
                    $row['categoria'],
                    $row['created_at'] ?? null,
                    $row['updated_at'] ?? null
                );
                $books[] = $book->toArray();
            }
            return $this->createJsonResponse($response, [
                'status' => 200,
                'result' => $books
            ]);
        } else {
            return $this->createJsonResponse($response->withStatus(204), [
                'status' => 204,
                'message' => 'No books found'
            ]);
        }
    }

    /**
     * Obtiene un libro específico por ID.
     * GET /books/{id}
     * @return 200 (Libro encontrado) o 204 (Book not found)
     */
    public function getBookById(RequestInterface $request, ResponseInterface $response, array $args)
    {
        $bookId = $args['id'];

        $query = "SELECT * FROM libro WHERE id = ?";
        $stmt = $this->mysqli->prepare($query);
        if (!$stmt) {
            return $this->createJsonResponse($response->withStatus(500), [
                'status' => 500,
                'message' => 'Error preparing query: ' . $this->mysqli->error
            ]);
        }
        $stmt->bind_param('i', $bookId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $book = new Book(
                $row['id'],
                $row['titulo'],
                $row['autor'],
                $row['editorial'],
                $row['publicadoEn'],
                $row['categoria'],
                $row['created_at'] ?? null,
                $row['updated_at'] ?? null
            );
            return $this->createJsonResponse($response, [
                'status' => 200,
                'result' => $book->toArray()
            ]);
        } else {
            return $this->createJsonResponse($response->withStatus(204), [
                'status' => 204,
                'message' => 'Book not found'
            ]);
        }
    }

    /**
     * Crea un nuevo libro.
     * POST /books
     * @return 201 (Book created successfully) o 400 (Error creating book)
     */
    public function createBook(RequestInterface $request, ResponseInterface $response, array $args)
    {
        $body = $request->getParsedBody();

        // Validar campos requeridos
        if (
            !isset($body['titulo']) ||
            !isset($body['autor']) ||
            !isset($body['editorial']) ||
            !isset($body['publicadoEn']) ||
            !isset($body['categoria'])
        ) {
            return $this->createJsonResponse($response->withStatus(400), [
                'status' => 400,
                'message' => 'Required data (titulo, autor, editorial, publicadoEn, categoria) is missing'
            ]);
        }

        $titulo = $body['titulo'];
        $autor = $body['autor'];
        $editorial = $body['editorial'];
        $publicadoEn = $body['publicadoEn'];
        $categoria = $body['categoria'];
        $createdAt = date('Y-m-d H:i:s');
        $updatedAt = date('Y-m-d H:i:s');

        $query = "INSERT INTO libro (titulo, autor, editorial, publicadoEn, categoria, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->mysqli->prepare($query);
        if (!$stmt) {
            return $this->createJsonResponse($response->withStatus(500), [
                'status' => 500,
                'message' => 'Error preparing query: ' . $this->mysqli->error
            ]);
        }
        $stmt->bind_param('sssiss', $titulo, $autor, $editorial, $publicadoEn, $categoria, $createdAt, $updatedAt);
        $result = $stmt->execute();

        if ($result) {
            $bookId = $this->mysqli->insert_id;
            return $this->createJsonResponse($response->withStatus(201), [
                'status' => 201,
                'message' => 'Book created successfully',
                'result' => [
                    'id' => $bookId,
                    'titulo' => $titulo,
                    'autor' => $autor,
                    'editorial' => $editorial,
                    'publicadoEn' => $publicadoEn,
                    'categoria' => $categoria,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt
                ]
            ]);
        } else {
            return $this->createJsonResponse($response->withStatus(400), [
                'status' => 400,
                'message' => 'Error creating book: ' . $this->mysqli->error
            ]);
        }
    }

    /**
     * Actualiza un libro existente.
     * PUT /books/{id}
     * @return 200 (Book updated successfully) o 204 (Book not found) o 400 (Error updating book)
     */
    public function updateBook(RequestInterface $request, ResponseInterface $response, array $args)
    {
        $bookId = $args['id'];
        $body = $request->getParsedBody();

        // Primero, verificar si el libro existe
        $checkQuery = "SELECT id FROM libro WHERE id = ?";
        $checkStmt = $this->mysqli->prepare($checkQuery);
        if (!$checkStmt) {
            return $this->createJsonResponse($response->withStatus(500), [
                'status' => 500,
                'message' => 'Error preparing query: ' . $this->mysqli->error
            ]);
        }
        $checkStmt->bind_param('i', $bookId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows === 0) {
            return $this->createJsonResponse($response->withStatus(204), [
                'status' => 204,
                'message' => 'Book not found'
            ]);
        }

        $setClauses = [];
        $bindParams = '';
        $bindValues = [];

        // Construir la consulta de actualización dinámicamente para campos opcionales
        if (isset($body['titulo'])) {
            $setClauses[] = 'titulo = ?';
            $bindParams .= 's';
            $bindValues[] = $body['titulo'];
        }
        if (isset($body['autor'])) {
            $setClauses[] = 'autor = ?';
            $bindParams .= 's';
            $bindValues[] = $body['autor'];
        }
        if (isset($body['editorial'])) {
            $setClauses[] = 'editorial = ?';
            $bindParams .= 's';
            $bindValues[] = $body['editorial'];
        }
        if (isset($body['publicadoEn'])) {
            $setClauses[] = 'publicadoEn = ?';
            $bindParams .= 'i';
            $bindValues[] = $body['publicadoEn'];
        }
        if (isset($body['categoria'])) {
            $setClauses[] = 'categoria = ?';
            $bindParams .= 's';
            $bindValues[] = $body['categoria'];
        }

        // Si no hay campos para actualizar, devolver un error
        if (empty($setClauses)) {
            return $this->createJsonResponse($response->withStatus(400), [
                'status' => 400,
                'message' => 'No valid fields provided for update'
            ]);
        }

        // Siempre actualizar 'updated_at'
        $setClauses[] = 'updated_at = ?';
        $bindParams .= 's';
        $updatedAt = date('Y-m-d H:i:s');
        $bindValues[] = $updatedAt;

        $query = "UPDATE libro SET " . implode(', ', $setClauses) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($query);
        if (!$stmt) {
            return $this->createJsonResponse($response->withStatus(500), [
                'status' => 500,
                'message' => 'Error preparing query: ' . $this->mysqli->error
            ]);
        }

        // Añadir el ID del libro a los valores a enlazar
        $bindParams .= 'i';
        $bindValues[] = $bookId;

        // Usar call_user_func_array para bind_param con un número dinámico de argumentos
        $refValues = array_merge([$bindParams], array_map(function (&$value) {
            return $value;
        }, $bindValues));
        call_user_func_array([$stmt, 'bind_param'], $refValues);

        $result = $stmt->execute();

        if ($result) {
            // Obtener el libro actualizado para devolverlo en la respuesta
            $selectUpdatedQuery = "SELECT * FROM libro WHERE id = ?";
            $selectUpdatedStmt = $this->mysqli->prepare($selectUpdatedQuery);
            if (!$selectUpdatedStmt) {
                return $this->createJsonResponse($response->withStatus(500), [
                    'status' => 500,
                    'message' => 'Error preparing query: ' . $this->mysqli->error
                ]);
            }
            $selectUpdatedStmt->bind_param('i', $bookId);
            $selectUpdatedStmt->execute();
            $updatedBookResult = $selectUpdatedStmt->get_result();
            $row = $updatedBookResult->fetch_assoc();
            $updatedBook = new Book(
                $row['id'],
                $row['titulo'],
                $row['autor'],
                $row['editorial'],
                $row['publicadoEn'],
                $row['categoria'],
                $row['created_at'] ?? null,
                $row['updated_at'] ?? null
            );

            return $this->createJsonResponse($response->withStatus(200), [
                'status' => 200,
                'message' => 'Book updated successfully',
                'result' => $updatedBook->toArray()
            ]);
        } else {
            return $this->createJsonResponse($response->withStatus(400), [
                'status' => 400,
                'message' => 'Error updating book: ' . $this->mysqli->error
            ]);
        }
    }

    /**
     * Elimina un libro existente.
     * DELETE /books/{id}
     * @return 200 (Book deleted successfully) o 204 (Book not found) o 400 (Error deleting book)
     */
    public function deleteBook(RequestInterface $request, ResponseInterface $response, array $args)
    {
        $bookId = $args['id'];

        // Primero, verificar si el libro existe
        $checkQuery = "SELECT id FROM libro WHERE id = ?";
        $checkStmt = $this->mysqli->prepare($checkQuery);
        if (!$checkStmt) {
            return $this->createJsonResponse($response->withStatus(500), [
                'status' => 500,
                'message' => 'Error preparing query: ' . $this->mysqli->error
            ]);
        }
        $checkStmt->bind_param('i', $bookId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows === 0) {
            return $this->createJsonResponse($response->withStatus(204), [
                'status' => 204,
                'message' => 'Book not found'
            ]);
        }

        $query = "DELETE FROM libro WHERE id = ?";
        $stmt = $this->mysqli->prepare($query);
        if (!$stmt) {
            return $this->createJsonResponse($response->withStatus(500), [
                'status' => 500,
                'message' => 'Error preparing query: ' . $this->mysqli->error
            ]);
        }
        $stmt->bind_param('i', $bookId);
        $result = $stmt->execute();

        if ($result) {
            return $this->createJsonResponse($response->withStatus(200), [
                'status' => 200,
                'message' => 'Book deleted successfully'
            ]);
        } else {
            return $this->createJsonResponse($response->withStatus(400), [
                'status' => 400,
                'message' => 'Error deleting book: ' . $this->mysqli->error
            ]);
        }
    }
}