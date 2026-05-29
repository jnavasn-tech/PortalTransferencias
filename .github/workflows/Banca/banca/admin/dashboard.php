<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Guard: solo admins
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'admin')) {
  header('Location: /index.php');
  exit();
}

// Intentar tomar de sesión
$adminUsuario = $_SESSION['admin_usuario'] ?? null;

// Si no hay en sesión, consultar BD (conexión inline, sin db.php)
if (!$adminUsuario) {
  $host_name = 'db5018789145.hosting-data.io';
  $database  = 'dbs14848135';
  $user_name = 'dbu3208202';
  $password  = 'Banca-123.';

  $conn = new mysqli($host_name, $user_name, $password, $database);
  if ($conn->connect_error) {
    die('MySQL error: ' . $conn->connect_error);
  }
  $conn->set_charset('utf8mb4');

  $id = (int)$_SESSION['user_id'];
  $stmt = $conn->prepare('SELECT usuario FROM administradores WHERE id = ? LIMIT 1');
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  $adminUsuario = $row['usuario'] ?? 'Administrador';
  $_SESSION['admin_usuario'] = $adminUsuario; // cache para futuras cargas
}

// Escapar para HTML
$adminUsuario = htmlspecialchars($adminUsuario, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Panel Administrador</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <div class="dashboard">
    <h1>Panel del Administrador</h1>
    <p>Conectado como: <b><?= $adminUsuario ?></b></p>

    <div style="margin-top:1rem">
      <a href="/admin/gestionar_cajeros.php">Gestionar Cajeros</a><br>
      <a href="/admin/monitor.php">Monitor</a><br>
      <a href="/logout.php">Salir</a>
    </div>
  </div>
</body>
</html>
