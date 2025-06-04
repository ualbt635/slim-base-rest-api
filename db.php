<?php

require 'config.php'; 

// Configuración de la base de datos
$host = $config['host'];
$username = $config['username'];
$password = $config['password'];
$database = $config['database'];

// Conexión a la base de datos
$mysqli = new mysqli($host, $username, $password, $database); 

// Comprobar la conexión
if ($mysqli->connect_error) {
    die('Error de conexión: ' . $mysqli->connect_error);
}

return $mysqli;