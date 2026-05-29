<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Validar sesión del cajero
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'cajero') {
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
    $numero = trim($_POST['numero'] ?? '');
    $monto  = floatval($_POST['monto'] ?? 0);

    if ($numero === '' || $monto <= 0) {
        $mensaje = 'Por favor ingresa un número de cuenta válido y un monto mayor a cero.';
        $tipoMsg = 'error';
    } else {
        // Verificar existencia de cuenta
        $sql = "SELECT id, saldo FROM cuentas WHERE numero_cuenta = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $numero);
        $stmt->execute();
        $res = $stmt->get_result();
        $cuenta = $res->fetch_assoc();

        if ($cuenta) {
            $nuevoSaldo = $cuenta['saldo'] + $monto;

            // Actualizar saldo
            $upd = $conn->prepare('UPDATE cuentas SET saldo = ? WHERE id = ?');
            $upd->bind_param('di', $nuevoSaldo, $cuenta['id']);
            $upd->execute();

            // Registrar transacción
            $tipo = 'deposito';
            $desc = 'Depósito en ventanilla';
            $ins = $conn->prepare('INSERT INTO transacciones (cuenta_id, tipo, monto, descripcion) VALUES (?, ?, ?, ?)');
            $ins->bind_param('isds', $cuenta['id'], $tipo, $monto, $desc);
            $ins->execute();

            $mensaje = '✅ Depósito realizado exitosamente. Nuevo saldo: Q' . number_format($nuevoSaldo, 2);
            $tipoMsg = 'ok';
        } else {
            $mensaje = '❌ Cuenta no encontrada.';
            $tipoMsg = 'error';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Depósito | Cajero</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <div class="dashboard">
    <h1>💰 Realizar Depósito</h1>

    <?php if (!empty($mensaje)): ?>
      <div class="alert <?= $tipoMsg === 'ok' ? 'alert--success' : 'alert--danger' ?>">
        <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <label for="numero">Número de Cuenta:</label>
      <input class="input" type="text" id="numero" name="numero" placeholder="Ej. 100-200-300" required>

      <label for="monto">Monto (Q):</label>
      <input class="input" type="number" id="monto" name="monto" step="0.01" min="0.01" required>

      <button type="submit" class="btn btn--primary">Depositar</button>
      <a href="/cajero/dashboard.php" class="btn btn--secondary" style="margin-top:8px;">Regresar</a>
    </form>
  </div>
</body>
</html>
