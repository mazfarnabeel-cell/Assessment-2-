<?php
require_once 'Entity.php';

class Menu extends Entity {
    private $harga;
    private $jenis;

    public function __construct($id, $nama, $harga, $jenis) {
        parent::__construct($id, $nama);

        $this->harga = $harga;
        $this->jenis = $jenis;
    }

    public function getHarga() {
        return $this->harga;
    }

    public function getJenis() {
        return $this->jenis;
    }

    public function displayInfo() {
        return $this->jenis . " - " .
               parent::displayInfo() .
               ", Harga: Rp" .
               number_format($this->harga, 0, ',', '.');
    }
}
?>