<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Verificar sesión y rol
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'usuario') {
    header('Location: /index.php');
    exit;
}

// Conexión directa (sin db.php)
$host_name = 'db5018789145.hosting-data.io';
$database  = 'dbs14848135';
$user_name = 'dbu3208202';
$password  = 'Banca-123.';

$conn = new mysqli($host_name, $user_name, $password, $database);
if ($conn->connect_error) {
    die('<p>Error al conectar con MySQL: ' . $conn->connect_error . '</p>');
}
$conn->set_charset('utf8mb4');

// Obtener información del usuario
$uid = (int)$_SESSION['user_id'];

$sql = 'SELECT c.nombre_cliente, c.numero_cuenta
        FROM usuarios u
        JOIN cuentas c ON c.id = u.cuenta_id
        WHERE u.id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $uid);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();

$nombreCliente = htmlspecialchars($info['nombre_cliente'] ?? 'Cliente', ENT_QUOTES, 'UTF-8');
$numeroCuenta  = htmlspecialchars($info['numero_cuenta'] ?? '-', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Panel del Usuario | Banca UMG</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <div class="dashboard">
    <h1>👤 Panel del Usuario</h1>
    <p>Bienvenido, <b><?= $nombreCliente ?></b></p>
    <p>Número de cuenta: <b><?= $numeroCuenta ?></b></p>

    <div class="menu">
      <a href="/usuario/agregar_tercero.php" class="btn btn--primary">Agregar Cuenta de Tercero</a>
      <a href="/usuario/transferir.php" class="btn btn--warning">Transferir</a>
      <a href="/usuario/estado_cuenta.php" class="btn btn--info">Estado de Cuenta</a>
      <a href="/logout.php" class="btn btn--secondary">Cerrar Sesión</a>
    </div>
  </div>
</body>
</html>
