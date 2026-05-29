<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Verificar rol
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre  = trim($_POST['nombre'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $claveP  = $_POST['clave'] ?? '';

    if ($nombre === '' || $usuario === '' || $claveP === '') {
        $mensaje = 'Todos los campos son obligatorios.';
        $tipoMsg = 'error';
    } else {
        $clave = password_hash($claveP, PASSWORD_BCRYPT);

        // Verificar si el usuario ya existe
        $chk = $conn->prepare('SELECT id FROM cajeros WHERE usuario = ?');
        $chk->bind_param('s', $usuario);
        $chk->execute();
        $existe = $chk->get_result()->fetch_assoc();

        if ($existe) {
            $mensaje = 'El usuario de cajero ya existe. Elige otro.';
            $tipoMsg = 'error';
        } else {
            $stmt = $conn->prepare('INSERT INTO cajeros (nombre, usuario, password) VALUES (?,?,?)');
            $stmt->bind_param('sss', $nombre, $usuario, $clave);

            if ($stmt->execute()) {
                $mensaje = 'Cajero agregado correctamente.';
                $tipoMsg = 'ok';
                $nombre = $usuario = '';
            } else {
                $mensaje = 'No fue posible agregar el cajero. Intenta de nuevo.';
                $tipoMsg = 'error';
            }
        }
    }
}

// Obtener lista de cajeros
$res = $conn->query('SELECT * FROM cajeros ORDER BY creado_en DESC');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Gestionar Cajeros</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <div class="dashboard">
    <h2>Gestionar Cajeros</h2>

    <?php if (!empty($mensaje)): ?>
      <div class="alert <?= $tipoMsg === 'ok' ? 'alert--success' : 'alert--danger' ?>">
        <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="post" autocomplete="off" class="mb-4">
      <label>Nombre:</label>
      <input class="input" type="text" name="nombre" value="<?= htmlspecialchars($nombre ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

      <label>Usuario:</label>
      <input class="input" type="text" name="usuario" value="<?= htmlspecialchars($usuario ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

      <label>Clave:</label>
      <input class="input" type="password" name="clave" required>

      <button type="submit" class="btn btn--primary">Agregar Cajero</button>
      <a href="/admin/dashboard.php" class="btn btn--secondary" style="display:inline-block;margin-top:8px;">Regresar</a>
    </form>

    <table>
      <thead>
        <tr><th>Nombre</th><th>Usuario</th><th>Estado</th></tr>
      </thead>
      <tbody>
        <?php while ($c = $res->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($c['usuario'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($c['estado'], ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
