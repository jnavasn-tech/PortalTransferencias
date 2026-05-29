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
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';

  if ($email !== '' && $pass !== '') {
    $sql = "SELECT id, email, password FROM usuarios WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if ($user && password_verify($pass, $user['password'])) {
      session_regenerate_id(true);
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['role']    = 'usuario';
      header("Location: /usuario/dashboard.php");
      exit();
    } else {
      $error = 'Correo o clave incorrectos.';
    }
  } else {
    $error = 'Complete todos los campos.';
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><title>Login Usuario</title>
  <link rel="stylesheet" href="./css/style.css">
</head>
<body>
    <div class="auth">
    <div class="auth-card">
  <div class="auth-card">
    <h1>Usuario</h1>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <label>Correo electrónico</label>
      <input class="input" type="email" name="email" required>
      <label>Contraseña</label>
      <input class="input" type="password" name="password" required>
      <button class="btn btn--primary w-full">Entrar</button>
    </form>
    <div class="auth-links">
      <a href="registro_usuario.php">Crear cuenta</a> |
      <a href="index.php">Volver</a>
    </div>
    </div>
      </div>
  </div>
</body>
</html>
