<?php
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

/* --- Conexión directa a la base de datos --- */
$host_name = 'db5018789145.hosting-data.io';
$database  = 'dbs14848135';
$user_name = 'dbu3208202';
$password  = 'Banca-123.';

$conn = new mysqli($host_name, $user_name, $password, $database);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
  die('<p>Error de conexión: ' . htmlspecialchars($conn->connect_error) . '</p>');
}

/* --- Protección CSRF --- */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* --- Funciones auxiliares --- */
function csrf_field() {
  return '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') . '">';
}
function csrf_check() {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
      http_response_code(403);
      exit('Solicitud CSRF inválida.');
    }
  }
}

/* --- Lógica de registro --- */
$mensaje = '';
$tipoMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $numero_cuenta = trim($_POST['numero_cuenta'] ?? '');
  $dpi           = trim($_POST['dpi'] ?? '');
  $email         = trim($_POST['email'] ?? '');
  $pass1         = $_POST['password'] ?? '';
  $pass2         = $_POST['password2'] ?? '';

  if ($numero_cuenta === '' || $dpi === '' || $email === '' || $pass1 === '' || $pass2 === '') {
    $mensaje = 'Todos los campos son obligatorios.';
    $tipoMsg = 'alert--danger';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $mensaje = 'El correo electrónico no es válido.';
    $tipoMsg = 'alert--danger';
  } elseif ($pass1 !== $pass2) {
    $mensaje = 'Las contraseñas no coinciden.';
    $tipoMsg = 'alert--danger';
  } else {
    // Verificar que la cuenta exista
    $sql = "SELECT id FROM cuentas WHERE numero_cuenta = ? AND dpi = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $numero_cuenta, $dpi);
    $stmt->execute();
    $res = $stmt->get_result();
    $cuenta = $res->fetch_assoc();

    if (!$cuenta) {
      $mensaje = 'No existe una cuenta que coincida con esos datos.';
      $tipoMsg = 'alert--danger';
    } else {
      // Registrar usuario vinculado
      $hash = password_hash($pass1, PASSWORD_BCRYPT);
      $sql = "INSERT INTO usuarios (cuenta_id, email, password) VALUES (?, ?, ?)";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('iss', $cuenta['id'], $email, $hash);

      if ($stmt->execute()) {
        $mensaje = 'Usuario registrado correctamente. Ya puedes iniciar sesión.';
        $tipoMsg = 'alert--success';
      } else {
        $mensaje = 'Ocurrió un error al registrar el usuario.';
        $tipoMsg = 'alert--danger';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro de Usuario</title>
  <link rel="stylesheet" href="./css/style.css">
</head>
<body>
  <div class="auth">
    <div class="auth-card">
      <div class="auth-header">
        <div class="brand"><span class="brand__dot"></span> Banco UMG</div>
      </div>
      <h1 class="auth-title">Registro de Usuario</h1>
      <p>Para crear tu usuario, la cuenta debe existir y el DPI debe coincidir con el registrado por el cajero.</p>

      <?php if (!empty($mensaje)): ?>
        <div class="alert <?= $tipoMsg ?>">
          <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <?= csrf_field(); ?>

        <label for="numero_cuenta">Número de Cuenta:</label>
        <input type="text" id="numero_cuenta" name="numero_cuenta" required>

        <label for="dpi">DPI:</label>
        <input type="text" id="dpi" name="dpi" required>

        <label for="email">Correo electrónico:</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Contraseña:</label>
        <input type="password" id="password" name="password" required minlength="6">

        <label for="password2">Confirmar Contraseña:</label>
        <input type="password" id="password2" name="password2" required minlength="6">

        <button type="submit" class="btn btn--primary w-full">Crear usuario</button>
        <a href="index.php" class="btn btn--secondary w-full">Regresar</a>
      </form>
    </div>
  </div>
</body>
</html>
