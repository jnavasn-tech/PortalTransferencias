<?php
// index.php (login unificado por rol con usuario o correo)
header('Content-Type: text/html; charset=utf-8');

  $host_name = 'db5018789145.hosting-data.io';
  $database = 'dbs14848135';
  $user_name = 'dbu3208202';
  $password = 'Banca-123.';

  $conn = new mysqli($host_name, $user_name, $password, $database);

  if ($conn->connect_error) {
    die('<p>Failed to connect to MySQL: '. $conn->connect_error .'</p>');
  } 
session_start();
// CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$error = '';

// Función genérica para admins y cajeros (usan campo "usuario")
function intentar_login_usuario($conn, $tabla, $usuario, $password) {
    $sql = "SELECT id, usuario, password FROM {$tabla} WHERE usuario = ? LIMIT 1";
    if (!$stmt = $conn->prepare($sql)) return [false, null];
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if ($row && password_verify($password, $row['password'])) {
        return [true, $row['id']];
    }
    return [false, null];
}

// Función específica para clientes (usan correo en la tabla "usuarios")
function intentar_login_email($conn, $email, $password) {
    $sql = "SELECT id, email, password FROM usuarios WHERE email = ? LIMIT 1";
    if (!$stmt = $conn->prepare($sql)) return [false, null];
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if ($row && password_verify($password, $row['password'])) {
        return [true, $row['id']];
    }
    return [false, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        $error = 'Solicitud inválida.';
    } else {
        $usuario  = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($usuario !== "" && filter_var($usuario, FILTER_VALIDATE_EMAIL)) {
            // Se ingresó un correo: buscar en clientes
            list($ok, $id) = intentar_login_email($conn, $usuario, $password);
            if ($ok) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $id;
                $_SESSION['role']    = 'usuario';
                header("Location: ./usuario/dashboard.php");
                exit;
            }
        } else {
            // Orden de verificación para admin y cajero
$mapa = [
    ['tabla' => 'administradores', 'rol' => 'admin',  'destino' => './login_admin.php'],
    ['tabla' => 'cajeros',         'rol' => 'cajero', 'destino' => './login_cajero.php'],
];

            foreach ($mapa as $m) {
                list($ok, $id) = intentar_login_usuario($conn, $m['tabla'], $usuario, $password);
                if ($ok) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $id;
                    $_SESSION['role']    = $m['rol'];
                    header("Location: {$m['destino']}");
                    exit;
                }
            }
        }

        // Si ninguno coincidió
        $error = 'Usuario o clave incorrectos.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Banca • Acceso</title>
  <link rel="stylesheet" href="./css/style.css">
</head>
  <div class="grid cards"> <section class="card"> <h3>Integrantes del Grupo</h3> <div class="grid cards" style="align-items:stretch"> 
    <figure class="card" style="text-align:center"> 
      <img src="img/marlon.png" alt="Marlon Isai Varela Marroquín" style="width:120px;height:120px;border-radius:999px;object-fit:cover;border:2px solid #e5e7eb;margin-bottom:8px"> 
      <figcaption><strong>Marlon Isai Varela Marroquín</strong></figcaption> 
      <figcaption><strong>0900-10-2203</strong></figcaption> 
    </figure>
    <figure class="card" style="text-align:center"> 
      <img src="img/josue.png" alt="Josué Fernando Navas Nájera" style="width:120px;height:120px;border-radius:999px;object-fit:cover;border:2px solid #e5e7eb;margin-bottom:8px"> 
      <figcaption><strong>Josué Fernando Navas Nájera</strong></figcaption> 
      <figcaption><strong>0900-08-4717</strong></figcaption> 
    </figure> 
    <figure class="card" style="text-align:center">
       <img src="img/william.png" alt="William Natanael Vásquez Navichoc" style="width:120px;height:120px;border-radius:999px;object-fit:cover;border:2px solid #e5e7eb;margin-bottom:8px"> 
       <figcaption><strong>William Natanael Vásquez Navichoc</strong></figcaption> 
       <figcaption><strong>0900 09 13357</strong></figcaption> 
</figure>
</div>
<body>
  <div class="auth">
    <div class="auth-card">
      <div class="auth-header">
        <div class="brand"><span class="brand__dot"></span> Banco UMG</div>
      </div>

      <h1 class="auth-title">Iniciar sesión</h1>
      <?php if (!empty($error)): ?>
        <div class="alert alert--danger mb-4"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="post" class="mb-4">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-4">
          <label for="usuario">Usuario o Correo</label>
          <input class="input" id="usuario" name="usuario" type="text" placeholder="Usuario o correo" required>
        </div>
        <div class="mb-4">
          <label for="password">Clave</label>
          <input class="input" id="password" name="password" type="password" placeholder="Clave" required>
        </div>
        <button type="submit" class="btn btn--primary w-full">Entrar</button>
      </form>

      <div class="auth-links">
        <a href="login_admin.php">| Login Administrador |</a>
        <a href="login_cajero.php">| Login Cajero |</a>
        <a href="login_usuario.php">| Login Usuario |</a>
      </div>
    </div>
  </div>
</body>
</html>