<?php
class Entity {
    protected $id;
    protected $nama;

    public function __construct($id, $nama) {
        $this->id = $id;
        $this->nama = $nama;
    }

    public function getId() {
        return $this->id;
    }

    public function getNama() {
        return $this->nama;
    }

    public function displayInfo() {
        return "ID: " . $this->id .
               ", Nama: " . $this->nama;
    }
}
?>