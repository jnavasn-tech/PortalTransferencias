<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    $sql = "SELECT id, usuario, password FROM administradores WHERE usuario = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $res = $stmt->get_result();
    $admin = $res->fetch_assoc();

    if ($admin && password_verify($pass, $admin['password'])) {
      session_regenerate_id(true);
      $_SESSION['user_id'] = $admin['id'];
      $_SESSION['role']    = 'admin';
      header("Location: admin/dashboard.php");
      exit();
    } else {
      $error = 'Usuario o clave incorrectos.';
    }
  } else {
    $error = 'Complete todos los campos.';
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><title>Doble Factor Administrador</title>
  <link rel="stylesheet" href="./css/style.css">
</head>
<body>
  <div class="auth">
    <div class="auth-card">
  <div class="auth-card">
    <h1>Administrador</h1>
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
