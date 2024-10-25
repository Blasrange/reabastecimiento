<?php

// app/Reports.php
namespace App;

class Reports {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    // Función para generar los reportes
    public function generateReports($cliente_id) {
        // Obtener los datos de reabastecimientos filtrados por el cliente
        $replenishments = $this->getReplenishmentsByClient($cliente_id);

        if (empty($replenishments)) {
            return []; // Cambiado a un arreglo vacío si no hay datos
        }

        $reportesGenerados = []; // Arreglo para almacenar reportes generados

        foreach ($replenishments as $replenishment) {
            // Obtener unidades a reabastecer
            $unidadesReabastecer = $replenishment['unidades_reabastecer'] ?? 0; // Valor predeterminado 0
            $cajasReabastecer = ceil($unidadesReabastecer / 10); // 10 unidades por caja
            
            // Obtener la fecha actual en formato YYYY-MM-DD HH:MM:SS
            $createdAt = date('Y-m-d H:i:s');

            try {
                // Insertar los datos en la tabla de reportes
                $result = $this->db->execute(
                    "INSERT INTO reportes (sku, descripcion, lpn_inventario, localizacion_origen, lpn_max_min, localizacion_destino, estado, unidades_reabastecer, cajas_reabastecer, cliente_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $replenishment['sku'],
                        $replenishment['descripcion'],
                        $replenishment['lpn_inventario'],
                        $replenishment['localizacion_origen'],
                        $replenishment['lpn_max_min'],
                        $replenishment['localizacion_destino'],
                        $replenishment['estado'],
                        $unidadesReabastecer,
                        $cajasReabastecer,
                        $cliente_id,
                        $createdAt // Incluir la fecha de creación
                    ]
                );

                // Si la inserción es exitosa, añadir a los reportes generados
                if ($result) {
                    $this->log("Reporte generado para SKU: " . $replenishment['sku']);
                    $reportesGenerados[] = $replenishment;
                }
            } catch (\Exception $e) {
                // Capturar el error de la base de datos
                $this->log("Error al insertar reporte para SKU: " . $replenishment['sku'] . ". Error: " . $e->getMessage());
            }
        }

        return $reportesGenerados; // Retorna los reportes generados
    }

    // Función auxiliar para obtener los datos de reabastecimientos por cliente
    private function getReplenishmentsByClient($cliente_id) {
        // Consulta para obtener los datos de reabastecimiento del cliente
        $query = "SELECT sku, descripcion, lpn_inventario, localizacion_origen, lpn_max_min, localizacion_destino, estado, unidades_reabastecer
                  FROM reabastecimientos
                  WHERE cliente_id = ? AND estado = 'Pendiente'"; // Filtrar por estado 'DSP'

        return $this->db->fetchAll($query, [$cliente_id]); 
    }

    // Función para registrar mensajes en el log
    private function log($message) {
        error_log($message, 3, __DIR__ . '/../logs/report.log');
    }
}
