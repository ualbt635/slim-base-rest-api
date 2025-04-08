# Slim Base REST API

Este proyecto es una base para construir APIs REST utilizando el framework [Slim](https://www.slimframework.com/). Está diseñado para ser simple, ligero y fácil de extender.

## Propósito

El propósito de este proyecto es proporcionar una estructura básica para desarrollar APIs RESTful con Slim, incluyendo:
- Manejo de rutas.
- Middleware para procesar datos JSON.
- Middleware para manejo de errores.
- Respuestas en formato JSON.

El proyecto se puede ejecutar en un servidor local si se tiene instalado PHP, Composer y un servidor web como Apache o Nginx, o se puede desplegar con Docker

## Requisitos

* Para la instalación en local:
  - PHP 7.4 o superior.
  - Composer para la gestión de dependencias.
  - Servidor web (Apache, Nginx, etc.) con PHP integrado.
* Para la instalación con Docker:
  - Docker y Docker Compose.

## Instalación

1. Clona este repositorio:
   ```bash
   git clone <URL_DEL_REPOSITORIO>
   cd slim-base-rest-api
   ```

2. Para la instalación local:
   - Ejecuta el siguiente comando para instalar las dependencias:
   ```bash
   composer install
   ```

   Para la instalación con Docker:
   - Despliega el entorno con:
   ```bash
   docker-compose up -d
   ```

## Uso

### Rutas disponibles

- **GET /**  
  Devuelve un mensaje de bienvenida en formato JSON:
  ```json
  {
      "status": 200,
      "message": "Welcome to the API"
  }
  ```

- **Cualquier otra ruta**  
  Devuelve un error 404 en formato JSON:
  ```json
  {
      "status": 404,
      "message": "Route not found"
  }
  ```

### Ejecución del servidor de desarrollo

Si se está usando el entorno local, se puede ejecutar un servidor de desarrollo con el siguiente comando:
```bash
php -S localhost:8080 -t public
```

Luego, accede a la API en [http://localhost:8080](http://localhost:8080).

## Personalización

Puedes agregar nuevas rutas en el archivo `public/index.php`. Por ejemplo:
```php
$app->get('/hello/{name}', function (RequestInterface $request, ResponseInterface $response, array $args) {
    $name = $args['name'];
    $data = [
        'status' => 200,
        'message' => "Hello, $name!"
    ];
    return createJsonResponse($response, $data);
});
```