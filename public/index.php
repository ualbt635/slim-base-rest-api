<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Factory\AppFactory;
use Slim\Middleware\ErrorMiddleware;

// Crear la aplicación
$app = AppFactory::create();

// Configurar Slim para procesar datos JSON
$app->addBodyParsingMiddleware();

// Configurar Slim para manejar errores
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Helper function to handle the response
function createJsonResponse(ResponseInterface $response, array $data): ResponseInterface
{
    // Set the response content type
    $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');

    // Write the response
    $response->getBody()->write(json_encode($data));

    // Return the response
    return $response;
}

// Definir la ruta de inicio
$app->get('/', function (RequestInterface $request, ResponseInterface $response) {
    $data = [
        'status' => 200,
        'message' => 'Welcome to the API'
    ];
    return createJsonResponse($response, $data);
});

// Interceptar todas las rutas no definidas
$app->any('{routes:.+}', function (RequestInterface $request, ResponseInterface $response) {
    $data = [
        'status' => 404,
        'message' => 'Route not found'
    ];
    return createJsonResponse($response->withStatus(404), $data);
});

// Ejecutar la aplicación
$app->run();
