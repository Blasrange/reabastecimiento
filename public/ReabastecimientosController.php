<?php
// public/ReabastecimientosController.php

namespace App;

session_start();

// Habilitar la visualización de errores (solo para desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../app/db.php'; // Cargar la clase Database
require_once '../app/Replenishment.php'; // Cargar la lógica de reabastecimientos

use App\Database;
use App\Replenishment;

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'], $_SESSION['cliente_id'])) {
    header('Location: login.php');
    exit;
}

// Inicializar variables
$cliente_id = $_SESSION['cliente_id'];

// Crear conexión a la base de datos
$database = new Database();

// Crear instancia de reabastecimientos
$reabastecimientosObj = new Replenishment($database);

// Verificar si el botón "Actualizar" fue presionado
if (isset($_POST['actualizar'])) {
    // Obtener los reabastecimientos generados
    $nuevosReabastecimientos = $reabastecimientosObj->generateReplenishments($cliente_id);

    // Verificar si hay reabastecimientos previos en la sesión
    if (isset($_SESSION['reabastecimientos'])) {
        // Combinar los nuevos reabastecimientos con los existentes, evitando duplicados
        $reabastecimientosExistentes = $_SESSION['reabastecimientos'];
        
        // Crear un array de SKUs existentes para evitar duplicados
        $existingReabastecimientos = [];
        foreach ($reabastecimientosExistentes as $reabastecimiento) {
            $existingReabastecimientos[$reabastecimiento['sku'] . '|' . $reabastecimiento['lpn_inventario']] = $reabastecimiento;
        }

        // Agregar o actualizar reabastecimientos
        foreach ($nuevosReabastecimientos as $nuevoReabastecimiento) {
            $key = $nuevoReabastecimiento['sku'] . '|' . $nuevoReabastecimiento['lpn_inventario'];
            if (isset($existingReabastecimientos[$key])) {
                // Actualizar unidades si ya existe
                if ($existingReabastecimientos[$key]['unidades_reabastecer'] !== $nuevoReabastecimiento['unidades_reabastecer']) {
                    $existingReabastecimientos[$key]['unidades_reabastecer'] = $nuevoReabastecimiento['unidades_reabastecer'];
                    $existingReabastecimientos[$key]['cajas_reabastecer'] = ceil($nuevoReabastecimiento['unidades_reabastecer'] / 10);
                }
            } else {
                // Agregar si no está duplicado
                $existingReabastecimientos[$key] = $nuevoReabastecimiento;
            }
        }

        // Convertir el array de reabastecimientos de vuelta a un índice numérico
        $_SESSION['reabastecimientos'] = array_values($existingReabastecimientos);
    } else {
        // Si no hay reabastecimientos previos, simplemente almacenarlos
        $_SESSION['reabastecimientos'] = $nuevosReabastecimientos;
    }

    // Verificar si se generaron reabastecimientos
    if (empty($_SESSION['reabastecimientos'])) {
        $_SESSION['error_message'] = "No se generaron reabastecimientos.";
    } else {
        unset($_SESSION['error_message']); // Limpiar mensajes de error si la generación fue exitosa
    }
}

// Cargar los reabastecimientos desde la sesión si existen
$reabastecimientos = $_SESSION['reabastecimientos'] ?? [];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reabastecimientos</title>
    <link rel="stylesheet" href="assets/css/estilos.css"> <!-- Agrega tu CSS aquí -->
    <style>
        .btn-back {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            margin: 20px 0;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s, transform 0.2s;
        }

        .btn-back:hover {
            background-color: #0056b3;
            transform: scale(1.05);
        }

        .btn-actualizar {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        .btn-actualizar:hover {
            background-color: #218838;
        }

        .form-upload {
            margin: 0px 0; /* Espaciado */
            border: 1px solid #ccc; /* Borde */
            padding: 1px; /* Espaciado interno */
            border-radius: 5px; /* Bordes redondeados */
            background-color: #f9f9f9; /* Fondo */
        }

        .total {
            font-weight: bold;
            margin-top: 20px;
        }

        /* Estilo para alinear el título y el formulario */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background-color: white; /* Fondo blanco */
        }

        .header h1 {
            margin: 0;
        }

        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 98%;
            background-color: white;
            z-index: 1000; /* Asegúrate de que esté sobre otros elementos */
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); /* Añadir sombra para destacar */
        }

        /* Añadir margen al contenido para que no quede debajo del header fijo */
        body {
            margin-top: 100px;
        }
        
        .total {
            position: fixed;
            bottom: 0;
            width: 100%;
            background-color: #f8f9fa;
            text-align: center;
            padding: 10px;
            box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1);
            z-index: 1000; /* Asegúrate de que esté sobre otros elementos */
        }
        
    </style>
<body>
    <div class="header">
        <a href="dashboard.php" style="text-decoration: none; color: black;">
            <h1>Reabastecimientos</h1>
        </a>
        <form method="POST" style="display: inline;">
            <button type="submit" name="actualizar" class="btn-refresh">Actualizar</button>
        </form>
    </div>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="error-message">
            <?php echo $_SESSION['error_message']; ?>
        </div>
    <?php endif; ?>

    <table border="0">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Descripción</th>
                <th>LPN Inventario</th>
                <th>Localización Origen</th>
                <th>Unidades a Reabastecer</th>
                <th>Lote</th>
                <th>fecha Vencimiento</th>
                <th>LPN Max Min</th>
                <th>Localización Destino</th>
                <th>Estado</th>                
                
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($reabastecimientos)): ?>
                <?php foreach ($reabastecimientos as $reabastecimiento): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($reabastecimiento['sku']); ?></td>
                        <td><?php echo htmlspecialchars($reabastecimiento['descripcion'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($reabastecimiento['lpn_inventario'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($reabastecimiento['localizacion_origen'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($reabastecimiento['unidades_reabastecer']); ?></td>
                        <td><?php echo htmlspecialchars($reabastecimiento['lote']); ?></td>
                        <td><?php echo htmlspecialchars($reabastecimiento['fecha_vencimiento']); ?></td>
                        <td><?php echo htmlspecialchars($reabastecimiento['lpn_max_min'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($reabastecimiento['localizacion_destino'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($reabastecimiento['estado']); ?></td>                     
                        
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9">No se encontraron reabastecimientos</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="total">
        <p>Total de Reabastecimientos: <?php echo count($reabastecimientos); ?></p>
    </div>
</body>
</html>