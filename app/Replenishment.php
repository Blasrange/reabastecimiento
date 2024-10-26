<?php
// app/Replenishment.php

namespace App;

class Replenishment {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function generateReplenishments($clienteId) {
        // Obtener la lista de materiales para el cliente específico
        $materials = $this->db->fetchAll("SELECT * FROM maestra_materiales WHERE cliente_id = ?", [$clienteId]);

        if ($materials === false) {
            error_log("Error al obtener la maestra de materiales para el cliente ID: $clienteId.", 3, __DIR__ . '/../logs/replenishment.log');
            return [];
        }

        $replenishments = []; // Inicializar el arreglo para almacenar reabastecimientos

        foreach ($materials as $material) {
            $sku = $material['sku'];
            $lpnMaestra = $material['lpn'];
            $localizacionMaestra = $material['localizacion'];
            $stockMin = $material['stock_minimo'];
            $stockMax = $material['stock_maximo'];
            $descripcion = $material['descripcion'];

            // Verificar el estado permitido para este cliente
            $estadoPermitido = $this->db->fetchOne("SELECT estado FROM estado_cliente WHERE cliente_id = ?", [$clienteId]);

            if (!$estadoPermitido) {
                error_log("No se encontró el estado permitido para el cliente ID: $clienteId", 3, __DIR__ . '/../logs/replenishment.log');
                continue;
            }

            // Verificar el stock actual del inventario para este SKU, localización específica, y estado permitido
            $inventory = $this->db->fetchAll(
                "SELECT * FROM inventarios WHERE sku = ? AND localizacion = ? AND estado = ? AND cliente_id = ? ORDER BY fecha_entrada ASC", 
                [$sku, $localizacionMaestra, $estadoPermitido, $clienteId]
            );

            if ($inventory === false || count($inventory) === 0) {
                error_log("No se encontraron datos para el SKU: $sku en la localización: $localizacionMaestra con estado permitido: $estadoPermitido para el cliente ID: $clienteId", 3, __DIR__ . '/../logs/replenishment.log');
                continue;
            }

            // Calcular el total disponible en la ubicación de la maestra de materiales
            $totalAvailableMaestra = 0;
            $loteMaestra = null;
            $fechaVencimientoMaestra = null;

            foreach ($inventory as $item) {
                $totalAvailableMaestra += $item['disponible'];

                // Obtener lote y fecha de vencimiento del inventario
                if ($loteMaestra === null && $item['disponible'] > 0) {
                    $loteMaestra = $item['lote'];
                    $fechaVencimientoMaestra = $item['fecha_vencimiento'];
                    $estadoMaestra = $item['estado'];
                }
            }

            // Si el stock disponible en la localización de la maestra está por debajo del mínimo
            if ($totalAvailableMaestra < $stockMin) {
                $unitsToReplenish = $stockMax - $totalAvailableMaestra;

                // Buscar en otras localizaciones del mismo SKU, estado permitido, y ordenado por fecha de entrada (FIFO)
                $inventoryOtherLocations = $this->db->fetchAll(
                    "SELECT * FROM inventarios WHERE sku = ? AND localizacion != ? AND estado = ? AND cliente_id = ? AND 
                    localizacion NOT LIKE '%10' AND localizacion NOT LIKE '%10-2' 
                    ORDER BY fecha_entrada ASC", 
                    [$sku, $localizacionMaestra, $estadoPermitido, $clienteId]
                );

                if ($inventoryOtherLocations === false || count($inventoryOtherLocations) === 0) {
                    error_log("No se encontraron otras ubicaciones para el SKU: $sku con estado permitido: $estadoPermitido para el cliente ID: $clienteId", 3, __DIR__ . '/../logs/replenishment.log');
                    continue;
                }

                $totalTaken = 0;

                foreach ($inventoryOtherLocations as $otherLocationItem) {
                    $availableInOtherLocation = $otherLocationItem['disponible'];
                    $lpnInventario = $otherLocationItem['lpn'];
                    $localizacionOrigen = $otherLocationItem['localizacion'];
                    $lote = $otherLocationItem['lote'];
                    $fechaVencimiento = $otherLocationItem['fecha_vencimiento'];
                    $estado = $otherLocationItem['estado'];

                    // Si hay unidades disponibles en esta localización
                    if ($availableInOtherLocation > 0) {
                        $unitsToTake = min($availableInOtherLocation, $unitsToReplenish - $totalTaken);

                        // Solo proceder si las unidades a reabastecer son mayores a 10
                        if ($unitsToTake > 10) {
                            // Intentar insertar en la tabla de reabastecimientos
                            $result = $this->db->execute(
                                "INSERT INTO reabastecimientos (sku, descripcion, lpn_inventario, localizacion_origen, unidades_reabastecer, lote, fecha_vencimiento, lpn_max_min, localizacion_destino, estado, cliente_id, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())", // Usamos NOW() para obtener la fecha actual
                                [$sku, $descripcion, $lpnInventario, $localizacionOrigen, $unitsToTake, $lote, $fechaVencimiento, $lpnMaestra, $localizacionMaestra, 'Pendiente', $clienteId]
                            );

                            if (!$result) {
                                error_log("Error al insertar reabastecimiento para SKU: $sku, Unidades: $unitsToTake", 3, __DIR__ . '/../logs/replenishment.log');
                            } else {
                                error_log("Reabastecimiento generado para SKU: $sku con unidades: $unitsToTake para cliente ID: $clienteId", 3, __DIR__ . '/../logs/replenishment.log');

                                // Agregar a la lista de reabastecimientos generados
                                $replenishments[] = [
                                    'sku' => $sku,
                                    'descripcion' => $descripcion,
                                    'lpn_inventario' => $lpnInventario,
                                    'localizacion_origen' => $localizacionOrigen,
                                    'unidades_reabastecer' => $unitsToTake,
                                    'lote' => $lote,
                                    'fecha_vencimiento' => $fechaVencimiento,
                                    'lpn_max_min' => $lpnMaestra,
                                    'localizacion_destino' => $localizacionMaestra,
                                    'estado' => $estado,
                                    'cliente_id' => $clienteId,
                                    'created_at' => date('Y-m-d H:i:s'), // Para referencia, aunque la base de datos ya manejará la fecha
                                ];

                                $totalTaken += $unitsToTake;
                            }

                            // Si ya hemos tomado suficientes unidades, salir del loop
                            if ($totalTaken >= $unitsToReplenish) {
                                break;
                            }
                        }
                    }
                }
            }
        }

        return $replenishments; // Retornar la lista de reabastecimientos generados
    }
}
