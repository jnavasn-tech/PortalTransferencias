<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Verificar sesión y rol
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'cajero') {
    header('Location: /index.php');
    exit;
}

// Conexión directa a la BD
$host_name = 'db5018789145.hosting-data.io';
$database  = 'dbs14848135';
$user_name = 'dbu3208202';
$password  = 'Banca-123.';

$conn = new mysqli($host_name, $user_name, $password, $database);
if ($conn->connect_error) {
    die('<p>Error al conectar con MySQL: ' . $conn->connect_error . '</p>');
}
$conn->set_charset('utf8mb4');

// Obtener nombre del cajero
$nombreCajero = $_SESSION['user_name'] ?? null;

if (!$nombreCajero) {
    $id = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare('SELECT nombre FROM cajeros WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $nombreCajero = $res['nombre'] ?? 'Cajero';
}

// Escapar para HTML
$nombreCajero = htmlspecialchars($nombreCajero, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Panel del Cajero | Banca UMG</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <div class="dashboard">
    <h1>💳 Panel del Cajero</h1>
    <p>Conectado como: <b><?= $nombreCajero ?></b></p>

    <div class="menu">
      <a href="/cajero/crear_cuenta.php" class="btn btn--primary">Crear Cuenta</a>
      <a href="/cajero/deposito.php" class="btn btn--warning">Depósito</a>
      <a href="/cajero/retiro.php" class="btn btn--danger">Retiro</a>
      <a href="/logout.php" class="btn btn--secondary">Cerrar Sesión</a>
    </div>
  </div>
</body>
</html>
