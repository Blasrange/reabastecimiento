<?php
// app/inventarios.php
namespace App;

use PDO;
use PDOException;

class inventarios {
    private $db;
    private $cliente_id;

    public function __construct(Database $database, $cliente_id) {
        $this->db = $database->pdo;
        $this->cliente_id = $cliente_id;
    }

    public function getAllItems() {
        $stmt = $this->db->prepare('SELECT * FROM inventarios WHERE cliente_id = :cliente_id ORDER BY id');
        $stmt->execute([':cliente_id' => $this->cliente_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addItem($data) {
        // Asegúrate de que $data tenga todos los campos necesarios
        $data[':cliente_id'] = $this->cliente_id;

        $stmt = $this->db->prepare('
            INSERT INTO inventarios (codigo, lpn, localizacion, area_picking, sku, sku2, descripcion, precio, tipo_material, categoria_material, unidades, cajas, reserva, disponible, udm, embalaje, fecha_entrada, estado, lote, fecha_fabricacion, fecha_vencimiento, fpc, peso, serial, cliente_id) 
            VALUES (:codigo, :lpn, :localizacion, :area_picking, :sku, :sku2, :descripcion, :precio, :tipo_material, :categoria_material, :unidades, :cajas, :reserva, :disponible, :udm, :embalaje, :fecha_entrada, :estado, :lote, :fecha_fabricacion, :fecha_vencimiento, :fpc, :peso, :serial, :cliente_id)
        ');

        try {
            return $stmt->execute($data);
        } catch (PDOException $e) {
            error_log("Error al agregar artículo: " . $e->getMessage());
            return false;
        }
    }

    public function updateItem($id, $data) {
        $data[':id'] = $id; 
        $data[':cliente_id'] = $this->cliente_id; 

        $stmt = $this->db->prepare('
            UPDATE inventarios 
            SET codigo = :codigo, lpn = :lpn, localizacion = :localizacion, area_picking = :area_picking, 
                sku = :sku, sku2 = :sku2, descripcion = :descripcion, precio = :precio, 
                tipo_material = :tipo_material, categoria_material = :categoria_material, 
                unidades = :unidades, cajas = :cajas, reserva = :reserva, 
                disponible = :disponible, udm = :udm, fecha_entrada = :fecha_entrada, 
                estado = :estado, lote = :lote, fecha_fabricacion = :fecha_fabricacion, 
                fecha_vencimiento = :fecha_vencimiento, fpc = :fpc, peso = :peso, 
                serial = :serial
            WHERE id = :id AND cliente_id = :cliente_id
        ');

        try {
            return $stmt->execute($data);
        } catch (PDOException $e) {
            error_log("Error al actualizar artículo: " . $e->getMessage());
            return false;
        }
    }

    public function deleteItem($id) {
        $stmt = $this->db->prepare('DELETE FROM inventarios WHERE id = :id AND cliente_id = :cliente_id');
        try {
            return $stmt->execute([
                ':id' => $id,
                ':cliente_id' => $this->cliente_id
            ]);
        } catch (PDOException $e) {
            error_log("Error al eliminar artículo: " . $e->getMessage());
            return false;
        }
    }

    public function getItem($id) {
        $stmt = $this->db->prepare('SELECT * FROM inventarios WHERE id = :id AND cliente_id = :cliente_id');
        $stmt->execute([
            ':id' => $id,
            ':cliente_id' => $this->cliente_id
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateOrInsertInventory($data) {
        $stmt = $this->db->prepare('SELECT id FROM inventarios WHERE codigo = :codigo AND cliente_id = :cliente_id');
        $stmt->execute([
            ':codigo' => $data['codigo'],
            ':cliente_id' => $this->cliente_id
        ]);

        $inventarioExistente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($inventarioExistente) {
            return $this->updateItem($inventarioExistente['id'], $data);
        } else {
            return $this->addItem($data);
        }
    }
}
?>
