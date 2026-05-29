<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Verificar rol: solo cajero
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
    $nombre = trim($_POST['nombre'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $dpi    = trim($_POST['dpi'] ?? '');
    $saldo  = $_POST['saldo'] ?? 0;

    if ($nombre === '' || $numero === '' || $dpi === '') {
        $mensaje = 'Todos los campos son obligatorios.';
        $tipoMsg = 'error';
    } elseif (!is_numeric($saldo) || $saldo < 0) {
        $mensaje = 'El saldo inicial debe ser un número válido.';
        $tipoMsg = 'error';
    } else {
        // Validar duplicado
        $chk = $conn->prepare('SELECT id FROM cuentas WHERE numero_cuenta = ?');
        $chk->bind_param('s', $numero);
        $chk->execute();
        $existe = $chk->get_result()->fetch_assoc();

        if ($existe) {
            $mensaje = 'El número de cuenta ya existe.';
            $tipoMsg = 'error';
        } else {
            $stmt = $conn->prepare('INSERT INTO cuentas (numero_cuenta, nombre_cliente, dpi, saldo) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('sssd', $numero, $nombre, $dpi, $saldo);
            if ($stmt->execute()) {
                $mensaje = '✅ Cuenta creada exitosamente.';
                $tipoMsg = 'ok';
                $nombre = $numero = $dpi = '';
            } else {
                $mensaje = '❌ Error al crear la cuenta.';
                $tipoMsg = 'error';
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Crear Cuenta | Cajero</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <div class="dashboard">
    <h1>Crear Nueva Cuenta</h1>

    <?php if (!empty($mensaje)): ?>
      <div class="alert <?= $tipoMsg === 'ok' ? 'alert--success' : 'alert--danger' ?>">
        <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <label for="nombre">Nombre del Cliente:</label>
      <input class="input" type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($nombre ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

      <label for="numero">Número de Cuenta:</label>
      <input class="input" type="text" id="numero" name="numero" value="<?= htmlspecialchars($numero ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

      <label for="dpi">DPI:</label>
      <input class="input" type="text" id="dpi" name="dpi" value="<?= htmlspecialchars($dpi ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

      <label for="saldo">Saldo Inicial (Q):</label>
      <input class="input" type="number" id="saldo" name="saldo" step="0.01" min="0" required>

      <button type="submit" class="btn btn--primary">Crear Cuenta</button>
      <a href="/cajero/dashboard.php" class="btn btn--secondary" style="display:inline-block;margin-top:8px;">Regresar</a>
    </form>
  </div>
</body>
</html>
