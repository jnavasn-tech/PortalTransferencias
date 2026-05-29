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

// Obtener cuenta del usuario
$uid = (int)$_SESSION['user_id'];

$sql = 'SELECT c.id, c.saldo, c.numero_cuenta, c.nombre_cliente
        FROM cuentas c
        JOIN usuarios u ON c.id = u.cuenta_id
        WHERE u.id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $uid);
$stmt->execute();
$cuenta = $stmt->get_result()->fetch_assoc();

if (!$cuenta) {
    die('<p>No se encontró la cuenta asociada a este usuario.</p>');
}

$cuentaId      = (int)$cuenta['id'];
$saldoActual   = (float)$cuenta['saldo'];
$numeroCuenta  = htmlspecialchars($cuenta['numero_cuenta'], ENT_QUOTES, 'UTF-8');
$nombreCliente = htmlspecialchars($cuenta['nombre_cliente'], ENT_QUOTES, 'UTF-8');

// Totales históricos
$tot = $conn->prepare("
    SELECT
        IFNULL(SUM(CASE WHEN tipo='deposito' THEN monto END),0) AS total_depositos,
        IFNULL(SUM(CASE WHEN tipo='retiro' THEN monto END),0) AS total_retiros,
        IFNULL(SUM(CASE WHEN tipo='transferencia' THEN monto END),0) AS total_transferencias
    FROM transacciones
    WHERE cuenta_id = ?
");
$tot->bind_param('i', $cuentaId);
$tot->execute();
$totales = $tot->get_result()->fetch_assoc();

// Historial
$hist = $conn->prepare('SELECT creado_en, tipo, monto, descripcion FROM transacciones WHERE cuenta_id = ? ORDER BY creado_en DESC');
$hist->bind_param('i', $cuentaId);
$hist->execute();
$res = $hist->get_result();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Estado de Cuenta | Banca UMG</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <div class="dashboard">
    <h1>💳 Estado de Cuenta</h1>

    <!-- Información del cliente -->
    <p><strong>Cliente:</strong> <?= $nombreCliente ?></p>
    <p><strong>Número de cuenta:</strong> <?= $numeroCuenta ?></p>
    <p><strong>Saldo actual:</strong> Q <?= number_format($saldoActual, 2) ?></p>

    <!-- Resumen histórico -->
    <h2>📊 Resumen histórico</h2>
    <table>
      <thead>
        <tr>
          <th>Total Depósitos</th>
          <th>Total Retiros</th>
          <th>Total Transferencias (salidas)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Q <?= number_format($totales['total_depositos'], 2) ?></td>
          <td>Q <?= number_format($totales['total_retiros'], 2) ?></td>
          <td>Q <?= number_format($totales['total_transferencias'], 2) ?></td>
        </tr>
      </tbody>
    </table>

    <!-- Historial detallado -->
    <h2>🧾 Detalle de transacciones</h2>
    <table>
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Tipo</th>
          <th>Monto (Q)</th>
          <th>Descripción</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($t = $res->fetch_assoc()) { ?>
          <tr>
            <td><?= htmlspecialchars($t['creado_en'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars(ucfirst($t['tipo']), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((float)$t['monto'], 2) ?></td>
            <td><?= htmlspecialchars($t['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>

    <div style="margin-top: 16px;">
      <a href="/usuario/dashboard.php" class="btn btn--secondary">⬅ Regresar al Panel</a>
    </div>
  </div>
</body>
</html>
