<?php
// public/dashboard.php //Esta es la página principal que verán los usuarios después de iniciar sesión y seleccionar cliente y ciudad.
session_start();

require_once '../app/db.php'; // Asegúrate de que la ruta sea correcta
require_once '../app/Auth.php';
require '../vendor/autoload.php'; // Autoload de Composer
use App\Database;
use App\Auth;

// Verificar si el usuario está autenticado y ha seleccionado cliente y ciudad
if (!isset($_SESSION['user_id'], $_SESSION['cliente_id'], $_SESSION['ciudad_id'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$auth = new Auth($database);

// Obtener información del cliente y la ciudad
$stmt = $database->pdo->prepare('SELECT nombre FROM clientes WHERE id = ?');
$stmt->execute([$_SESSION['cliente_id']]);
$cliente = $stmt->fetch();

$stmt = $database->pdo->prepare('SELECT nombre FROM ciudades WHERE id = ?');
$stmt->execute([$_SESSION['ciudad_id']]);
$ciudad = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Reabastecimiento</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <style>
        /* Estilos básicos para el dashboard */
        body {
            font-family: Arial, sans-serif;
            background-color: #eef2f3;
            margin: 0;
            padding: 0;
        }
        .header {
            background-color: #5cb85c;
            color: #fff;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .nav a {
            color: #fff;
            margin-left: 5px;
            text-decoration: none;
            font-weight: bold;
        }
        .nav a:hover {
            text-decoration: underline;
        }
        .content {
            padding: 90px;
            text-align: left; /* Alinea el contenido a la izquierda */
            
        }
        .info {
            margin-bottom: 1px;
            text-align: left; /* Alinea el texto a la izquierda */
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>Sistema de Reabastecimiento</h1>
        </div>
        <div class="nav">
            <a href="inventarioscontrolle.php">Inventarios</a>
            <a href="maestraMaterialesController.php">Maestra de Materiales</a>
            <a href="ReabastecimientosController.php">Reabastecimientos</a>
            <a href="ReportsController.php">Reportes</a>
            <a href="HistorialController.php">Historial</a>
            <!--a href="Kardex.php">Kardex</a-->
            <a href="logout.php">Cerrar Sesión</a>
        </div>
    </div>
    <div class="content">
        <div class="info">
            <h2>Bienvenido, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
            <p>Cliente seleccionado: <strong><?php echo htmlspecialchars($cliente['nombre']); ?></strong></p>
            <p>Ciudad seleccionada: <strong><?php echo htmlspecialchars($ciudad['nombre']); ?></strong></p>
        </div>
        <!-- Aquí puedes agregar más contenido o enlaces rápidos -->
    </div>
</body>
</html>
