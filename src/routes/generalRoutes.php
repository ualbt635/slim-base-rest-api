<?php

use App\Controllers\BookController;
use App\Controllers\LoanController;

$app->get('/books', [new BookController($GLOBALS['mysqli']), 'getAllBooks']);
$app->get('/books/{id}', [new BookController($GLOBALS['mysqli']), 'getBookById']);
$app->post('/books', [new BookController($GLOBALS['mysqli']), 'createBook']);
$app->put('/books/{id}', [new BookController($GLOBALS['mysqli']), 'updateBook']);
$app->delete('/books/{id}', [new BookController($GLOBALS['mysqli']), 'deleteBook']);

// Asegúrate de incluir las rutas para LoanController si las tienes
$app->post('/loans', [new LoanController($GLOBALS['mysqli']), 'createLoan']);
$app->put('/loans/return', [new LoanController($GLOBALS['mysqli']), 'returnLoan']);