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

// Variables
$mensaje = '';
$tipoMsg = 'ok';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $destino   = trim($_POST['destino'] ?? '');
    $alias     = trim($_POST['alias'] ?? '');
    $monto_max = floatval($_POST['monto_max'] ?? 0);
    $max_diario = intval($_POST['max_diario'] ?? 0);
    $uid       = $_SESSION['user_id'];

    // Validar campos
    if ($destino === '' || $alias === '' || $monto_max <= 0 || $max_diario <= 0) {
        $mensaje = 'Por favor completa todos los campos correctamente.';
        $tipoMsg = 'error';
    } else {
        // Validar que la cuenta destino exista
        $sql = 'SELECT id FROM cuentas WHERE numero_cuenta = ?';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $destino);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            // Insertar cuenta de tercero
            $insert = 'INSERT INTO cuentas_terceros (usuario_id, cuenta_destino, alias, monto_maximo, transacciones_max) VALUES (?, ?, ?, ?, ?)';
            $stmt = $conn->prepare($insert);
            $stmt->bind_param('issdi', $uid, $destino, $alias, $monto_max, $max_diario);
            if ($stmt->execute()) {
                $mensaje = '✅ Cuenta de tercero agregada exitosamente.';
                $tipoMsg = 'ok';
            } else {
                $mensaje = '❌ Error al guardar la cuenta de tercero.';
                $tipoMsg = 'error';
            }
        } else {
            $mensaje = '❌ La cuenta destino no existe.';
            $tipoMsg = 'error';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Agregar Cuenta de Tercero | Usuario</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <div class="dashboard">
    <h1>🏦 Registrar Cuenta de Tercero</h1>

    <?php if (!empty($mensaje)): ?>
      <div class="alert <?= $tipoMsg === 'ok' ? 'alert--success' : 'alert--danger' ?>">
        <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <label for="destino">Número de Cuenta Destino:</label>
      <input class="input" type="text" id="destino" name="destino" placeholder="Ej. 100-200-300" required>

      <label for="alias">Alias:</label>
      <input class="input" type="text" id="alias" name="alias" placeholder="Ej. Cuenta de Juan Pérez" required>

      <label for="monto_max">Monto Máximo Permitido (Q):</label>
      <input class="input" type="number" id="monto_max" name="monto_max" step="0.01" min="0.01" required>

      <label for="max_diario">Máx. Transacciones Diarias:</label>
      <input class="input" type="number" id="max_diario" name="max_diario" min="1" required>

      <button type="submit" class="btn btn--primary">Agregar Cuenta</button>
      <a href="/usuario/dashboard.php" class="btn btn--secondary" style="margin-top:8px;">Regresar</a>
    </form>
  </div>
</body>
</html>
