<?php
namespace App\Helpers;

use Psr\Http\Message\ResponseInterface;

// Helper function to handle the response
function createJsonResponse(ResponseInterface $response, array $data): ResponseInterface
{
    $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    $response->getBody()->write(json_encode($data));
    return $response;
}