<?php

namespace App\Models;

class Loan
{
    private $id;
    private $book_inventory_number; // Esto es el 'inventoryNumber' de la API
    private $user_id;             // Esto es el 'userId' de la API
    private $loan_date;
    private $return_date;

    public function __construct($id, $book_inventory_number, $user_id, $loan_date = null, $return_date = null)
    {
        $this->id = $id;
        $this->book_inventory_number = $book_inventory_number;
        $this->user_id = $user_id;
        $this->loan_date = $loan_date;
        $this->return_date = $return_date;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'inventoryNumber' => $this->book_inventory_number, // Mapeado al nombre de la API
            'userId' => $this->user_id,                       // Mapeado al nombre de la API
            'loan_date' => $this->loan_date,
            'return_date' => $this->return_date
        ];
    }
}