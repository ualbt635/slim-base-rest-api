<?php
namespace App\Models;

class Book
{
    private $id;
    private $titulo;
    private $autor;
    private $editorial;
    private $publicadoEn;
    private $categoria;
    private $createdAt;
    private $updatedAt;

    public function __construct($id, $titulo, $autor, $editorial, $publicadoEn, $categoria, $createdAt, $updatedAt)
    {
        $this->id = $id;
        $this->titulo = $titulo;
        $this->autor = $autor;
        $this->editorial = $editorial;
        $this->publicadoEn = $publicadoEn;
        $this->categoria = $categoria;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'autor' => $this->autor,
            'editorial' => $this->editorial,
            'publicadoEn' => $this->publicadoEn,
            'categoria' => $this->categoria,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }
}