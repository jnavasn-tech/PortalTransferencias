<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* Conexión directa (sin db.php) */
$host_name = 'db5018789145.hosting-data.io';
$database  = 'dbs14848135';
$user_name = 'dbu3208202';
$password  = 'Banca-123.';
$conn = new mysqli($host_name, $user_name, $password, $database);
if ($conn->connect_error) { die('MySQL error: ' . $conn->connect_error); }
$conn->set_charset('utf8mb4');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $usuario  = trim($_POST['usuario'] ?? '');
  $pass     = $_POST['password'] ?? '';

  if ($usuario !== '' && $pass !== '') {
    $sql = "SELECT id, usuario, password, estado FROM cajeros WHERE usuario = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $res = $stmt->get_result();
    $cajero = $res->fetch_assoc();

    if ($cajero && $cajero['estado'] === 'activo' && password_verify($pass, $cajero['password'])) {
      session_regenerate_id(true);
      $_SESSION['user_id'] = $cajero['id'];
      $_SESSION['role']    = 'cajero';
      header("Location: /cajero/dashboard.php");
      exit();
    } else {
      $error = 'Credenciales incorrectas o usuario bloqueado.';
    }
  } else {
    $error = 'Complete todos los campos.';
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><title>Login Cajero</title>
  <link rel="stylesheet" href="./css/style.css">
</head>
<body>
    <div class="auth">
    <div class="auth-card">
  <div class="auth-card">
    <h1>Cajero</h1>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <label>Usuario</label>
      <input class="input" name="usuario" required>
      <label>Contraseña</label>
      <input class="input" type="password" name="password" required>
      <button class="btn btn--primary w-full">Entrar</button>
    </form>
    <div class="auth-links"><a href="index.php">Volver</a></div>
  </div>
        </div>
        </div>
</body>
</html>
