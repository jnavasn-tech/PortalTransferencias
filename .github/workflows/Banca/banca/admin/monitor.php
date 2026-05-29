<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Verificar rol (solo admin)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
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

// Validar formato de fechas
function safeDate($s) {
    return (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) ? $s : null;
}

// Parámetros de consulta
$hoy = date('Y-m-d');
$desde = safeDate($_GET['desde'] ?? $hoy);
$hasta = safeDate($_GET['hasta'] ?? $hoy);

if (isset($_GET['fecha']) && safeDate($_GET['fecha'])) {
    $desde = $hasta = $_GET['fecha'];
}

if (!$desde) $desde = $hoy;
if (!$hasta) $hasta = $hoy;

// Función KPI para consultas simples
function kpi($conn, $sql, $params = []) {
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_row();
    return $row ? $row[0] : 0;
}

// KPIs
$cuentas    = kpi($conn, "SELECT COUNT(*) FROM cuentas WHERE DATE(creado_en) BETWEEN ? AND ?", [$desde, $hasta]);
$usuarios   = kpi($conn, "SELECT COUNT(*) FROM usuarios WHERE DATE(creado_en) BETWEEN ? AND ?", [$desde, $hasta]);
$trans      = kpi($conn, "SELECT COUNT(*) FROM transacciones WHERE DATE(creado_en) BETWEEN ? AND ?", [$desde, $hasta]);
$depositos  = kpi($conn, "SELECT IFNULL(SUM(monto),0) FROM transacciones WHERE tipo='deposito' AND DATE(creado_en) BETWEEN ? AND ?", [$desde, $hasta]);
$retiros    = kpi($conn, "SELECT IFNULL(SUM(monto),0) FROM transacciones WHERE tipo='retiro' AND DATE(creado_en) BETWEEN ? AND ?", [$desde, $hasta]);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Monitor | Banca UMG</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <div class="dashboard">
    <h1>📊 Panel de Monitoreo</h1>
    <p class="muted">Consulta de indicadores operativos — <b><?= htmlspecialchars($desde) ?></b> a <b><?= htmlspecialchars($hasta) ?></b></p>

    <form method="get" class="mb-4">
      <div class="row">
        <div>
          <label for="fecha">Un solo día:</label>
          <input class="input" type="date" id="fecha" name="fecha" value="<?= htmlspecialchars($desde) ?>">
        </div>
        <span class="muted">— o —</span>
        <div>
          <label for="desde">Desde:</label>
          <input class="input" type="date" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>">
        </div>
        <div>
          <label for="hasta">Hasta:</label>
          <input class="input" type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
        </div>
        <div>
          <button type="submit" class="btn btn--primary">Consultar</button>
          <a href="/admin/monitor.php" class="btn btn--secondary">Limpiar</a>
        </div>
      </div>
    </form>

    <h2>Indicadores Generales</h2>
    <table>
      <thead>
        <tr>
          <th>Cuentas creadas</th>
          <th>Usuarios nuevos</th>
          <th>Transacciones</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?= $cuentas ?></td>
          <td><?= $usuarios ?></td>
          <td><?= $trans ?></td>
        </tr>
      </tbody>
    </table>

    <h2>Montos Totales (Q)</h2>
    <table>
      <thead>
        <tr>
          <th>Depósitos</th>
          <th>Retiros</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?= number_format($depositos, 2) ?></td>
          <td><?= number_format($retiros, 2) ?></td>
        </tr>
      </tbody>
    </table>

    <div style="margin-top:20px;">
      <a href="/admin/dashboard.php" class="btn btn--secondary">← Regresar al Panel</a>
    </div>
  </div>
</body>
</html>
