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

$uid = (int)$_SESSION['user_id'];
$mensaje = '';
$tipoMsg = 'ok';

// Obtener cuenta principal del usuario
$sql = 'SELECT c.id, c.saldo, c.numero_cuenta, c.nombre_cliente 
        FROM cuentas c 
        JOIN usuarios u ON c.id = u.cuenta_id 
        WHERE u.id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $uid);
$stmt->execute();
$cuenta = $stmt->get_result()->fetch_assoc();

if (!$cuenta) {
    die('<p>No se encontró la cuenta asociada a este usuario.</p>');
}

// Procesar transferencia
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tercero_id = (int)($_POST['tercero'] ?? 0);
    $monto = (float)($_POST['monto'] ?? 0);

    if ($tercero_id <= 0 || $monto <= 0) {
        $mensaje = 'Por favor seleccione un tercero válido e ingrese un monto mayor a cero.';
        $tipoMsg = 'error';
    } else {
        // Verificar cuenta de tercero
        $sql = 'SELECT * FROM cuentas_terceros WHERE id = ? AND usuario_id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $tercero_id, $uid);
        $stmt->execute();
        $tercero = $stmt->get_result()->fetch_assoc();

        if ($tercero) {
            // Validar límites
            if ($monto > $tercero['monto_maximo']) {
                $mensaje = 'El monto excede el límite permitido.';
                $tipoMsg = 'error';
            } else {
                // Validar límite diario de transferencias
                $hoy = date('Y-m-d');
                $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM transferencias WHERE origen_id = ? AND DATE(creado_en) = ?');
                $stmt->bind_param('is', $cuenta['id'], $hoy);
                $stmt->execute();
                $c = $stmt->get_result()->fetch_assoc()['c'] ?? 0;

                if ($c >= $tercero['transacciones_max']) {
                    $mensaje = 'Has alcanzado el máximo de transferencias permitidas para hoy.';
                    $tipoMsg = 'error';
                } else {
                    // Ejecutar transacción
                    $conn->begin_transaction();
                    try {
                        // Verificar saldo suficiente
                        if ($cuenta['saldo'] < $monto) {
                            throw new Exception('Saldo insuficiente para realizar la transferencia.');
                        }

                        // Obtener cuenta destino
                        $stmt = $conn->prepare('SELECT id FROM cuentas WHERE numero_cuenta = ?');
                        $stmt->bind_param('s', $tercero['cuenta_destino']);
                        $stmt->execute();
                        $destino = $stmt->get_result()->fetch_assoc();

                        if (!$destino) {
                            throw new Exception('Cuenta destino no encontrada.');
                        }

                        // Actualizar saldos
                        $upd1 = $conn->prepare('UPDATE cuentas SET saldo = saldo - ? WHERE id = ?');
                        $upd1->bind_param('di', $monto, $cuenta['id']);
                        $upd1->execute();

                        $upd2 = $conn->prepare('UPDATE cuentas SET saldo = saldo + ? WHERE id = ?');
                        $upd2->bind_param('di', $monto, $destino['id']);
                        $upd2->execute();

                        // Registrar transferencias y transacciones
                        $ins1 = $conn->prepare('INSERT INTO transferencias (origen_id, destino_id, monto) VALUES (?, ?, ?)');
                        $ins1->bind_param('iid', $cuenta['id'], $destino['id'], $monto);
                        $ins1->execute();

                        $desc = 'Transferencia a ' . $tercero['alias'];
                        $ins2 = $conn->prepare('INSERT INTO transacciones (cuenta_id, tipo, monto, descripcion) VALUES (?, ?, ?, ?)');
                        $tipo = 'transferencia';
                        $ins2->bind_param('isds', $cuenta['id'], $tipo, $monto, $desc);
                        $ins2->execute();

                        $conn->commit();
                        $mensaje = '✅ Transferencia realizada correctamente.';
                        $tipoMsg = 'ok';
                    } catch (Exception $e) {
                        $conn->rollback();
                        $mensaje = '❌ Error: ' . $e->getMessage();
                        $tipoMsg = 'error';
                    }
                }
            }
        } else {
            $mensaje = 'Cuenta de tercero no encontrada.';
            $tipoMsg = 'error';
        }
    }
}

// Obtener lista de terceros del usuario
$res = $conn->query("SELECT id, alias, cuenta_destino FROM cuentas_terceros WHERE usuario_id = $uid ORDER BY creado_en DESC");
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Transferencias | Usuario</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <div class="dashboard">
    <h1>💸 Transferencias</h1>

    <?php if (!empty($mensaje)): ?>
      <div class="alert <?= $tipoMsg === 'ok' ? 'alert--success' : 'alert--danger' ?>">
        <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <label for="tercero">Cuenta de Tercero:</label>
      <select name="tercero" id="tercero" class="input" required>
        <option value="">-- Seleccione --</option>
        <?php while ($t = $res->fetch_assoc()) { ?>
          <option value="<?= $t['id'] ?>">
            <?= htmlspecialchars($t['alias'] . ' (' . $t['cuenta_destino'] . ')', ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php } ?>
      </select>

      <label for="monto">Monto (Q):</label>
      <input type="number" name="monto" id="monto" class="input" step="0.01" min="0.01" required>

      <button type="submit" class="btn btn--primary">Transferir</button>
      <a href="/usuario/dashboard.php" class="btn btn--secondary" style="margin-top:8px;">Regresar</a>
    </form>
  </div>
</body>
</html>
