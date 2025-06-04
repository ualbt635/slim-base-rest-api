<?php
namespace App\Controllers;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use App\Models\Book; // Asegúrate que la ruta a tu modelo es correcta

class BookController
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
     * Obtiene todos los libros.
     * GET /libros
     */
    public function getAllBooks(RequestInterface $request, ResponseInterface $response, array $args)
    {
        // Nota: Tu modelo Book tiene 'categoria', 'created_at', 'updated_at'.
        // La tabla 'libro' debe tener estas columnas o la consulta/modelo debe ajustarse.
        // Por ahora, asumiré que la tabla 'libro' tiene: id, titulo, autor, editorial, publicadoEn
        // y opcionalmente categoria, created_at, updated_at.
        $query = 'SELECT id, titulo, autor, editorial, publicadoEn, categoria FROM libro';
        $stmt = $this->mysqli->prepare($query);

        if (!$stmt) {
            return $this->createJsonResponse($response, [
                'status' => 500,
                'message' => 'Error preparando consulta: ' . $this->mysqli->error
            ], 500);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $books_data = [];

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // El constructor de tu Book model espera todos los campos.
                $book = new Book(
                    $row['id'],
                    $row['titulo'],
                    $row['autor'],
                    $row['editorial'],
                    $row['publicadoEn'], // Este es el nombre del campo en tu tabla según tus scripts originales
                    $row['categoria'] ?? null, // Asumir null si no existe en la BD
                    $row['created_at'] ?? null, // Asumir null
                    $row['updated_at'] ?? null  // Asumir null
                );
                $books_data[] = $book->toArray();
            }
            // En lugar de 'status' dentro del JSON, el código HTTP de la respuesta es el estándar.
            // Si quieres mantener 'status' en el cuerpo, puedes hacerlo.
            // La API de tus scripts cURL devolvía directamente el array de libros.
            return $this->createJsonResponse($response, $books_data, 200);
        } else {
            // Tus scripts cURL simplemente no mostraban nada o "No hay libros".
            // Una API RESTful suele devolver un array vacío con 200 OK o 204 No Content.
            return $this->createJsonResponse($response, [], 200); // O 204
        }
    }

    /**
     * Obtiene un libro específico por ID.
     * GET /libros/{id}
     */
    public function getBookById(RequestInterface $request, ResponseInterface $response, array $args)
    {
        $bookId = $args['id'];
        $query = "SELECT id, titulo, autor, editorial, publicadoEn, categoria FROM libro WHERE id = ?";
        $stmt = $this->mysqli->prepare($query);

        if (!$stmt) {
            return $this->createJsonResponse($response, ['status' => 500, 'message' => 'Error preparando consulta: ' . $this->mysqli->error], 500);
        }

        $stmt->bind_param('i', $bookId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $book = new Book(
                $row['id'],
                $row['titulo'],
                $row['autor'],
                $row['editorial'],
                $row['publicadoEn'],
                $row['categoria'] ?? null,
                $row['created_at'] ?? null,
                $row['updated_at'] ?? null
            );
            // Tu script libros_editar.php esperaba directamente el objeto libro.
            return $this->createJsonResponse($response, $book->toArray(), 200);
        } else {
            // Tu script libros_editar.php moría con "Libro no encontrado".
            // Una API RESTful suele devolver 404 Not Found.
            return $this->createJsonResponse($response, ['status' => 404, 'message' => 'Libro no encontrado'], 404);
        }
    }

    /**
     * Crea un nuevo libro.
     * POST /libros
     */
    public function createBook(RequestInterface $request, ResponseInterface $response, array $args)
    {
        $body = $request->getParsedBody();

        // Validar campos requeridos (basado en tus scripts originales, 'categoria' no era requerido)
        if (!isset($body['titulo']) || !isset($body['autor']) || !isset($body['editorial']) || !isset($body['publicadoEn'])) {
            return $this->createJsonResponse($response, [
                'status' => 400,
                'message' => 'Datos requeridos (titulo, autor, editorial, publicadoEn) faltantes'
            ], 400);
        }

        $titulo = $body['titulo'];
        $autor = $body['autor'];
        $editorial = $body['editorial'];
        $publicadoEn = $body['publicadoEn'];
        // Campos opcionales basados en el modelo Book, si se proporcionan
        $categoria = $body['categoria'] ?? null;
        // created_at y updated_at usualmente se manejan por la BD (DEFAULT CURRENT_TIMESTAMP)
        // o se establecen aquí si es necesario.
        // $createdAt = date('Y-m-d H:i:s');
        // $updatedAt = date('Y-m-d H:i:s');

        // Asumiendo que tu tabla 'libro' podría no tener 'created_at', 'updated_at', 'categoria'
        // o que tienen valores por defecto. Ajusta la consulta según tu tabla.
        $query = "INSERT INTO libro (titulo, autor, editorial, publicadoEn, categoria) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->mysqli->prepare($query);

        if (!$stmt) {
            return $this->createJsonResponse($response, ['status' => 500, 'message' => 'Error preparando consulta: ' . $this->mysqli->error], 500);
        }

        // El tipo para 'publicadoEn' en tu modelo/scripts parece ser string o año. Asumiré string por ahora.
        // Si 'publicadoEn' es un año (INT), usa 'i'. Si es string, usa 's'.
        // Si 'categoria' es string, usa 's'.
        $stmt->bind_param('sssss', $titulo, $autor, $editorial, $publicadoEn, $categoria);
        $result = $stmt->execute();

        if ($result) {
            $bookId = $this->mysqli->insert_id;
            // Devolver el libro recién creado, similar a como lo hacía el BookController original
            $newBookData = [
                'id' => $bookId,
                'titulo' => $titulo,
                'autor' => $autor,
                'editorial' => $editorial,
                'publicadoEn' => $publicadoEn,
                'categoria' => $categoria
                // 'created_at' => $createdAt, // Si los definiste
                // 'updated_at' => $updatedAt  // Si los definiste
            ];
            // Tu script libros_guardar.php solo mostraba alerta y redirigía.
            // Una API RESTful devuelve 201 Created con el recurso creado.
            return $this->createJsonResponse($response, [
                'status' => 'creado', // Similar a tus scripts
                'message' => 'Libro creado correctamente',
                'result' => $newBookData
            ], 201);
        } else {
            return $this->createJsonResponse($response, [
                'status' => 500, // O 400 Bad Request si es error de datos
                'message' => 'Error al crear el libro: ' . $stmt->error
            ], 500);
        }
    }

    /**
     * Actualiza un libro existente.
     * PUT /libros/{id}
     */
    public function updateBook(RequestInterface $request, ResponseInterface $response, array $args)
    {
        $bookId = $args['id'];
        $body = $request->getParsedBody();

        // Validar campos (al menos uno debe estar presente para actualizar)
        if (empty($body) || (!isset($body['titulo']) && !isset($body['autor']) && !isset($body['editorial']) && !isset($body['publicadoEn']) && !isset($body['categoria']))) {
            return $this->createJsonResponse($response, [
                'status' => 400,
                'message' => 'No se proporcionaron datos para actualizar o campos inválidos.'
            ], 400);
        }

        // Primero, verificar si el libro existe (opcional pero buena práctica)
        $checkQuery = "SELECT id FROM libro WHERE id = ?";
        $checkStmt = $this->mysqli->prepare($checkQuery);
        if (!$checkStmt) { return $this->createJsonResponse($response, ['status' => 500, 'message' => 'Error preparando consulta (check)'], 500); }
        $checkStmt->bind_param('i', $bookId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows === 0) {
            return $this->createJsonResponse($response, ['status' => 404, 'message' => 'Libro no encontrado para actualizar'], 404);
        }
        $checkStmt->close();


        // Construir la consulta de actualización dinámicamente
        $setClauses = [];
        $bindTypes = '';
        $bindValues = [];

        if (isset($body['titulo'])) {
            $setClauses[] = 'titulo = ?';
            $bindTypes .= 's';
            $bindValues[] = $body['titulo'];
        }
        if (isset($body['autor'])) {
            $setClauses[] = 'autor = ?';
            $bindTypes .= 's';
            $bindValues[] = $body['autor'];
        }
        if (isset($body['editorial'])) {
            $setClauses[] = 'editorial = ?';
            $bindTypes .= 's';
            $bindValues[] = $body['editorial'];
        }
        if (isset($body['publicadoEn'])) {
            $setClauses[] = 'publicadoEn = ?'; // Asumiendo string
            $bindTypes .= 's';
            $bindValues[] = $body['publicadoEn'];
        }
        if (isset($body['categoria'])) {
            $setClauses[] = 'categoria = ?';
            $bindTypes .= 's';
            $bindValues[] = $body['categoria'];
        }
        // Podrías añadir updated_at aquí si tu tabla lo tiene y quieres gestionarlo manualmente
        // $setClauses[] = 'updated_at = NOW()'; // O pasa el valor

        if (empty($setClauses)) {
             return $this->createJsonResponse($response, ['status' => 400, 'message' => 'Ningún campo válido para actualizar.'], 400);
        }

        $query = "UPDATE libro SET " . implode(', ', $setClauses) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($query);

        if (!$stmt) {
            return $this->createJsonResponse($response, ['status' => 500, 'message' => 'Error preparando consulta (update): ' . $this->mysqli->error], 500);
        }

        $bindTypes .= 'i'; // Para el ID al final
        $bindValues[] = $bookId;
        
        // Necesitamos pasar referencias para bind_param
        $refValues = [];
        foreach($bindValues as $key => $value) {
            $refValues[$key] = &$bindValues[$key];
        }
        array_unshift($refValues, $bindTypes); // Añadir los tipos al inicio

        call_user_func_array([$stmt, 'bind_param'], $refValues);
        $result = $stmt->execute();

        if ($result) {
            if ($stmt->affected_rows > 0) {
                // Tu script libros_actualizar.php esperaba status: 'actualizado'
                return $this->createJsonResponse($response, [
                    'status' => 'actualizado', // Manteniendo tu formato de status
                    'message' => 'Libro actualizado correctamente'
                ], 200);
            } else {
                 return $this->createJsonResponse($response, [
                    'status' => 'sin_cambios',
                    'message' => 'Libro no encontrado o datos sin cambios'
                ], 200); // O 304 Not Modified si sabes que no hubo error pero no cambios
            }
        } else {
            return $this->createJsonResponse($response, [
                'status' => 500,
                'message' => 'Error al actualizar el libro: ' . $stmt->error
            ], 500);
        }
    }

    /**
     * Elimina un libro existente.
     * DELETE /libros/{id}
     */
    public function deleteBook(RequestInterface $request, ResponseInterface $response, array $args)
    {
        $bookId = $args['id'];

        // Opcional: Verificar si el libro existe antes de intentar eliminarlo
        // (similar a updateBook, pero puedes omitirlo y solo verificar affected_rows)

        $query = "DELETE FROM libro WHERE id = ?";
        $stmt = $this->mysqli->prepare($query);

        if (!$stmt) {
            return $this->createJsonResponse($response, ['status' => 500, 'message' => 'Error preparando consulta: ' . $this->mysqli->error], 500);
        }

        $stmt->bind_param('i', $bookId);
        $result = $stmt->execute();

        if ($result) {
            if ($stmt->affected_rows > 0) {
                // Tu script libros_eliminar.php esperaba status: 'eliminado'
                return $this->createJsonResponse($response, [
                    'status' => 'eliminado', // Manteniendo tu formato
                    'message' => 'Libro eliminado correctamente'
                ], 200); // Algunas APIs devuelven 204 No Content para DELETE exitoso sin cuerpo de respuesta
            } else {
                return $this->createJsonResponse($response, ['status' => 404, 'message' => 'Libro no encontrado para eliminar'], 404);
            }
        } else {
            return $this->createJsonResponse($response, [
                'status' => 500,
                'message' => 'Error al eliminar el libro: ' . $stmt->error
            ], 500);
        }
    }
}