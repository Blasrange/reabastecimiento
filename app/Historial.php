<?php

namespace App;

class Historial {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    // Función para generar el historial a partir de los reportes
    public function generateHistorial($cliente_id) {
        // Obtener los reportes por cliente
        $replenishments = $this->getReplenishmentsByClient($cliente_id);

        if (empty($replenishments)) {
            return [];
        }

        // Agrupar los reportes por SKU y turno
        $historialData = [];

        foreach ($replenishments as $replenishment) {
            $sku = $replenishment['sku'];
            $turno = $this->getTurno(); // Asignar turno (puedes cambiar esta lógica)
            $fecha_hora = date('Y-m-d H:i:s');

            // Calcular las cajas
            $unidadesReabastecer = $replenishment['unidades_reabastecer'] ?? 0;
            $cajasReabastecer = ceil($unidadesReabastecer / 10);

            // Agrupar datos por SKU y turno
            $key = $sku . '|' . $turno;
            if (!isset($historialData[$key])) {
                $historialData[$key] = [
                    'fecha_hora' => $fecha_hora,
                    'sku' => $sku,
                    'unidades' => $unidadesReabastecer,
                    'cajas' => $cajasReabastecer,
                    'turno' => $turno
                ];
            } else {
                // Sumar unidades y cajas si ya existe
                $historialData[$key]['unidades'] += $unidadesReabastecer;
                $historialData[$key]['cajas'] += $cajasReabastecer;
            }
        }

        // Insertar el historial agrupado en la base de datos
        foreach ($historialData as $historial) {
            $this->db->execute(
                "INSERT INTO historial (fecha_hora, sku, unidades, cajas, turno, cliente_id)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $historial['fecha_hora'],
                    $historial['sku'],
                    $historial['unidades'],
                    $historial['cajas'],
                    $historial['turno'],
                    $cliente_id
                ]
            );
        }

        return $historialData;
    }

    // Función auxiliar para obtener los datos de reabastecimiento por cliente
    private function getReplenishmentsByClient($cliente_id) {
        $query = "SELECT sku, unidades_reabastecer FROM reabastecimientos WHERE cliente_id = ? AND estado = 'Pendiente'";
        return $this->db->fetchAll($query, [$cliente_id]);
    }

    // Función auxiliar para determinar el turno (puedes personalizarla)
    private function getTurno() {
        $hora = date('H');
        return ($hora >= 6 && $hora < 14) ? 1 : (($hora >= 14 && $hora < 22) ? 2 : 3);
    }
}
