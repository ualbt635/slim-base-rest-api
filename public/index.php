<?php

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/db.php'; 

use Slim\Factory\AppFactory;

// Asignar la conexión a $GLOBALS['mysqli'] para que los controladores la usen
$GLOBALS['mysqli'] = $mysqli;

// Crear la aplicación
$app = AppFactory::create();

// Configurar Slim para procesar datos JSON
$app->addBodyParsingMiddleware();

// Cargar las rutas desde un archivo separado 
require dirname(__DIR__) . '/src/routes/generalRoutes.php';

// Ruta base
$app->setBasePath('/slim-base-rest-api');

// Ejecutar la aplicación 
$app->run();