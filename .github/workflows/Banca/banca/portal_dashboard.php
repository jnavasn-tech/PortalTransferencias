<?php
// portal_dashboard.php
session_start();
require_once "db.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: portal_transferencias.php");
    exit;
}

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
function csrf_field() {
    return '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8').'">';
}
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            http_response_code(403); exit("CSRF inválido.");
        }
    }
}

$uid = (int)$_SESSION['usuario_id'];

/* ---- Obtener datos del usuario y su cuenta ---- */
$stmt = $conn->prepare("
    SELECT u.email, c.*
    FROM usuarios u
    JOIN cuentas c ON c.id = u.cuenta_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$cuenta = $stmt->get_result()->fetch_assoc();

if (!$cuenta) {
    echo "Error: no se encontró tu cuenta."; exit;
}
$cuenta_id = (int)$cuenta['id'];

/* ---- Vista activa ---- */
$vista = $_GET['vista'] ?? 'inicio';  // inicio | transferir | terceros | historial

$msg = ''; $msg_tipo = '';

/* ============================
   POST: Agregar tercero
   ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_tercero') {
    csrf_check();
    $destino   = trim($_POST['destino'] ?? '');
    $alias     = trim($_POST['alias'] ?? '');
    $monto_max = (float)($_POST['monto_max'] ?? 0);
    $max_diario= (int)($_POST['max_diario'] ?? 0);

    if ($destino === '' || $alias === '' || $monto_max <= 0 || $max_diario <= 0) {
        $msg = 'Completa todos los campos correctamente.';
        $msg_tipo = 'error';
        $vista = 'terceros';
    } else {
        $chk = $conn->prepare("SELECT id FROM cuentas WHERE numero_cuenta = ?");
        $chk->bind_param("s", $destino);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $ins = $conn->prepare("INSERT INTO cuentas_terceros (usuario_id, cuenta_destino, alias, monto_maximo, transacciones_max) VALUES (?,?,?,?,?)");
            $ins->bind_param("issdi", $uid, $destino, $alias, $monto_max, $max_diario);
            $ins->execute();
            $msg = '¡Cuenta de tercero agregada!';
            $msg_tipo = 'ok';
        } else {
            $msg = 'La cuenta destino no existe.';
            $msg_tipo = 'error';
        }
        $vista = 'terceros';
    }
}

/* ============================
   POST: Transferencia
   ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transferir') {
    csrf_check();
    $tercero_id = (int)($_POST['tercero'] ?? 0);
    $monto      = (float)($_POST['monto'] ?? 0);

    $t_stmt = $conn->prepare("SELECT * FROM cuentas_terceros WHERE id = ? AND usuario_id = ?");
    $t_stmt->bind_param("ii", $tercero_id, $uid);
    $t_stmt->execute();
    $tercero = $t_stmt->get_result()->fetch_assoc();

    if (!$tercero) {
        $msg = 'Cuenta de tercero no válida.'; $msg_tipo = 'error';
    } elseif ($monto <= 0) {
        $msg = 'El monto debe ser mayor a cero.'; $msg_tipo = 'error';
    } elseif ($monto > $tercero['monto_maximo']) {
        $msg = 'El monto supera el límite permitido (Q ' . number_format($tercero['monto_maximo'], 2) . ').'; $msg_tipo = 'error';
    } else {
        // Contar transferencias de hoy
        $hoy  = date('Y-m-d');
        $c_st = $conn->prepare("SELECT COUNT(*) c FROM transferencias WHERE origen_id = ? AND DATE(creado_en) = ?");
        $c_st->bind_param("is", $cuenta_id, $hoy);
        $c_st->execute();
        $cnt = $c_st->get_result()->fetch_assoc()['c'];

        if ($cnt >= $tercero['transacciones_max']) {
            $msg = 'Se alcanzó el límite de transferencias diarias para esta cuenta.'; $msg_tipo = 'error';
        } elseif ($cuenta['saldo'] < $monto) {
            $msg = 'Saldo insuficiente.'; $msg_tipo = 'error';
        } else {
            $conn->begin_transaction();
            try {
                $destino_num = $tercero['cuenta_destino'];
                $conn->query("UPDATE cuentas SET saldo = saldo - $monto WHERE id = $cuenta_id");
                $conn->query("UPDATE cuentas SET saldo = saldo + $monto WHERE numero_cuenta = '".$conn->real_escape_string($destino_num)."'");

                $dest_id_q = $conn->query("SELECT id FROM cuentas WHERE numero_cuenta = '".$conn->real_escape_string($destino_num)."'");
                $dest_id   = $dest_id_q->fetch_assoc()['id'];

                $conn->query("INSERT INTO transferencias (origen_id, destino_id, monto) VALUES ($cuenta_id, $dest_id, $monto)");

                $alias_esc = $conn->real_escape_string($tercero['alias']);
                $conn->query("INSERT INTO transacciones (cuenta_id, tipo, monto, descripcion) VALUES ($cuenta_id, 'transferencia', $monto, 'Transferencia a $alias_esc')");

                $conn->commit();

                // Refrescar saldo
                $r = $conn->prepare("SELECT saldo FROM cuentas WHERE id = ?");
                $r->bind_param("i", $cuenta_id);
                $r->execute();
                $cuenta['saldo'] = $r->get_result()->fetch_assoc()['saldo'];

                $msg = '¡Transferencia realizada con éxito!'; $msg_tipo = 'ok';
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $conn->rollback();
                $msg = 'Error: ' . $e->getMessage(); $msg_tipo = 'error';
            }
        }
    }
    $vista = 'transferir';
}

/* ---- Cargar terceros ---- */
$terceros_res = $conn->prepare("SELECT * FROM cuentas_terceros WHERE usuario_id = ?");
$terceros_res->bind_param("i", $uid);
$terceros_res->execute();
$terceros = $terceros_res->get_result()->fetch_all(MYSQLI_ASSOC);

/* ---- Cargar historial ---- */
$hist = $conn->prepare("SELECT * FROM transacciones WHERE cuenta_id = ? ORDER BY creado_en DESC LIMIT 30");
$hist->bind_param("i", $cuenta_id);
$hist->execute();
$historial = $hist->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal de Transferencias</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
  <style>
    :root {
      --navy:   #0a1628;
      --navy2:  #112240;
      --navy3:  #1a2f50;
      --gold:   #c9a84c;
      --gold2:  #e8c97a;
      --cream:  #f5f0e8;
      --white:  #ffffff;
      --muted:  #8892a4;
      --border: rgba(201,168,76,0.12);
      --red:    #e07a6e;
      --green:  #5ecf8e;
      --sidebar-w: 240px;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--navy);
      color: var(--white);
      display: flex;
      min-height: 100vh;
    }

    /* ============ SIDEBAR ============ */
    .sidebar {
      width: var(--sidebar-w);
      background: var(--navy2);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      padding: 1.5rem 0;
      position: fixed;
      top: 0; left: 0; bottom: 0;
      z-index: 10;
    }

    .sidebar-logo {
      padding: 0 1.5rem 1.5rem;
      border-bottom: 1px solid var(--border);
      margin-bottom: 1rem;
    }
    .sidebar-logo .icon-wrap {
      width: 38px; height: 38px;
      background: linear-gradient(135deg, var(--gold), var(--gold2));
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 0.7rem;
    }
    .sidebar-logo .icon-wrap svg { width: 20px; height: 20px; color: var(--navy); }
    .sidebar-logo h2 {
      font-family: 'Playfair Display', serif;
      font-size: 1rem;
      color: var(--white);
      line-height: 1.2;
    }
    .sidebar-logo p { font-size: 0.75rem; color: var(--muted); margin-top: 2px; }

    .nav-label {
      font-size: 0.65rem;
      color: var(--muted);
      letter-spacing: 0.1em;
      text-transform: uppercase;
      padding: 0.5rem 1.5rem 0.4rem;
      font-weight: 500;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.65rem 1.5rem;
      color: var(--muted);
      text-decoration: none;
      font-size: 0.9rem;
      transition: all 0.2s;
      border-left: 3px solid transparent;
    }
    .nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }
    .nav-item:hover { color: var(--white); background: rgba(201,168,76,0.06); }
    .nav-item.active {
      color: var(--gold);
      border-left-color: var(--gold);
      background: rgba(201,168,76,0.08);
    }

    .sidebar-bottom {
      margin-top: auto;
      padding: 1rem 1.5rem 0;
      border-top: 1px solid var(--border);
    }
    .user-pill {
      display: flex;
      align-items: center;
      gap: 0.7rem;
      margin-bottom: 0.8rem;
    }
    .user-avatar {
      width: 34px; height: 34px;
      background: linear-gradient(135deg, var(--gold), var(--gold2));
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--navy);
      flex-shrink: 0;
    }
    .user-info p { font-size: 0.82rem; color: var(--white); font-weight: 500; }
    .user-info span { font-size: 0.72rem; color: var(--muted); }

    .logout-btn {
      display: flex; align-items: center; gap: 0.5rem;
      padding: 0.55rem 0;
      color: var(--muted);
      text-decoration: none;
      font-size: 0.83rem;
      transition: color 0.2s;
    }
    .logout-btn:hover { color: var(--red); }
    .logout-btn svg { width: 16px; height: 16px; }

    /* ============ MAIN ============ */
    .main {
      margin-left: var(--sidebar-w);
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      background:
        radial-gradient(ellipse 70% 50% at 80% 0%, rgba(201,168,76,0.05) 0%, transparent 60%);
    }

    .topbar {
      padding: 1.4rem 2rem;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .topbar h1 {
      font-family: 'Playfair Display', serif;
      font-size: 1.4rem;
      color: var(--white);
    }
    .topbar .cuenta-badge {
      display: flex; align-items: center; gap: 0.5rem;
      background: rgba(201,168,76,0.1);
      border: 1px solid var(--border);
      padding: 0.4rem 1rem;
      border-radius: 20px;
      font-size: 0.83rem;
      color: var(--gold);
    }

    .content {
      flex: 1;
      padding: 2rem;
    }

    /* ---- Alert ---- */
    .alert {
      padding: 0.8rem 1rem;
      border-radius: 8px;
      font-size: 0.88rem;
      margin-bottom: 1.5rem;
      display: flex; align-items: center; gap: 0.5rem;
    }
    .alert.error { background: rgba(192,57,43,0.15); border: 1px solid rgba(192,57,43,0.3); color: var(--red); }
    .alert.ok    { background: rgba(26,122,74,0.15);  border: 1px solid rgba(26,122,74,0.3);  color: var(--green); }

    /* ---- Cards de resumen ---- */
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.2rem;
      margin-bottom: 2rem;
    }
    .summary-card {
      background: var(--navy2);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 1.3rem 1.5rem;
    }
    .summary-card .label {
      font-size: 0.75rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.07em;
      margin-bottom: 0.6rem;
    }
    .summary-card .value {
      font-family: 'Playfair Display', serif;
      font-size: 1.7rem;
      color: var(--white);
    }
    .summary-card .value.gold { color: var(--gold); }
    .summary-card .sub { font-size: 0.8rem; color: var(--muted); margin-top: 0.3rem; }

    /* ---- Section title ---- */
    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.1rem;
      color: var(--white);
      margin-bottom: 1.2rem;
      display: flex; align-items: center; gap: 0.5rem;
    }
    .section-title::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
      margin-left: 0.5rem;
    }

    /* ---- Panel form ---- */
    .panel {
      background: var(--navy2);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 1.8rem;
      margin-bottom: 2rem;
    }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 700px) { .form-grid { grid-template-columns: 1fr; } }

    .field { margin-bottom: 0; }
    .field label {
      display: block;
      font-size: 0.75rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.07em;
      margin-bottom: 0.4rem;
      font-weight: 500;
    }
    .field input, .field select {
      width: 100%;
      padding: 0.7rem 1rem;
      background: rgba(10,22,40,0.7);
      border: 1px solid rgba(201,168,76,0.15);
      border-radius: 8px;
      color: var(--white);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.9rem;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .field input:focus, .field select:focus {
      border-color: var(--gold);
      box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
    }
    .field select option { background: var(--navy2); }

    .btn {
      padding: 0.7rem 1.5rem;
      border: none;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-gold {
      background: linear-gradient(135deg, var(--gold), var(--gold2));
      color: var(--navy);
      box-shadow: 0 3px 14px rgba(201,168,76,0.2);
    }
    .btn-gold:hover { opacity: 0.9; transform: translateY(-1px); }
    .btn-outline {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--muted);
    }
    .btn-outline:hover { border-color: var(--gold); color: var(--gold); }

    .form-actions { margin-top: 1.2rem; display: flex; gap: 0.8rem; align-items: center; }

    /* ---- Tabla ---- */
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
    thead th {
      text-align: left;
      padding: 0.7rem 1rem;
      color: var(--muted);
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      font-weight: 500;
      border-bottom: 1px solid var(--border);
    }
    tbody td {
      padding: 0.8rem 1rem;
      border-bottom: 1px solid rgba(201,168,76,0.06);
      color: var(--white);
      vertical-align: middle;
    }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover td { background: rgba(201,168,76,0.04); }

    .badge {
      display: inline-block;
      padding: 0.2rem 0.6rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    .badge-dep  { background: rgba(26,122,74,0.2);  color: var(--green); }
    .badge-ret  { background: rgba(192,57,43,0.2);  color: var(--red); }
    .badge-trf  { background: rgba(201,168,76,0.15); color: var(--gold); }

    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
      color: var(--muted);
    }
    .empty-state svg { width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.3; }
    .empty-state p { font-size: 0.9rem; }

    /* inicio quick-actions */
    .quick-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem;
    }
    .quick-card {
      background: var(--navy2);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 1.3rem;
      text-decoration: none;
      color: var(--muted);
      transition: all 0.25s;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 0.7rem;
    }
    .quick-card:hover {
      border-color: var(--gold);
      color: var(--white);
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    }
    .quick-icon {
      width: 40px; height: 40px;
      background: rgba(201,168,76,0.1);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
    }
    .quick-icon svg { width: 20px; height: 20px; color: var(--gold); }
    .quick-card span { font-size: 0.9rem; font-weight: 500; }
  </style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="icon-wrap">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M3 6l9-4 9 4v2H3V6zM3 10h18v10H3V10zM9 14v4M15 14v4"/>
      </svg>
    </div>
    <h2>Portal Banca</h2>
    <p>en Línea</p>
  </div>

  <span class="nav-label">Menú</span>

  <a href="?vista=inicio"
     class="nav-item <?= $vista === 'inicio' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
      <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
    </svg>
    Inicio
  </a>

  <a href="?vista=transferir"
     class="nav-item <?= $vista === 'transferir' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/>
      <polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/>
    </svg>
    Transferir
  </a>

  <a href="?vista=terceros"
     class="nav-item <?= $vista === 'terceros' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
      <circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
    </svg>
    Cuentas Terceros
  </a>

  <a href="?vista=historial"
     class="nav-item <?= $vista === 'historial' ? 'active' : '' ?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
      <polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>
      <line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
    </svg>
    Estado de Cuenta
  </a>

  <div class="sidebar-bottom">
    <div class="user-pill">
      <div class="user-avatar">
        <?= strtoupper(substr($cuenta['email'] ?? 'U', 0, 1)) ?>
      </div>
      <div class="user-info">
        <p><?= htmlspecialchars(explode('@', $cuenta['email'])[0], ENT_QUOTES, 'UTF-8') ?></p>
        <span>Cliente</span>
      </div>
    </div>
    <a href="logout.php" class="logout-btn">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
        <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Cerrar sesión
    </a>
  </div>
</aside>

<!-- ===== MAIN ===== -->
<main class="main">
  <div class="topbar">
    <h1>
      <?php
        $titles = ['inicio'=>'Bienvenido', 'transferir'=>'Transferir Fondos', 'terceros'=>'Cuentas de Terceros', 'historial'=>'Estado de Cuenta'];
        echo $titles[$vista] ?? 'Portal';
      ?>
    </h1>
    <div class="cuenta-badge">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
      </svg>
      <?= htmlspecialchars($cuenta['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?>
    </div>
  </div>

  <div class="content">

    <?php if ($msg): ?>
      <div class="alert <?= $msg_tipo ?>">
        <?= $msg_tipo === 'ok'
          ? '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>'
          : '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>' ?>
        <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- =================== INICIO =================== -->
    <?php if ($vista === 'inicio'): ?>
      <div class="summary-grid">
        <div class="summary-card">
          <div class="label">Saldo disponible</div>
          <div class="value gold">Q <?= number_format($cuenta['saldo'], 2) ?></div>
          <div class="sub">Cuenta <?= htmlspecialchars($cuenta['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="summary-card">
          <div class="label">Cuentas de terceros</div>
          <div class="value"><?= count($terceros) ?></div>
          <div class="sub">registradas</div>
        </div>
        <div class="summary-card">
          <div class="label">Movimientos</div>
          <div class="value"><?= count($historial) ?></div>
          <div class="sub">últimos registros</div>
        </div>
      </div>

      <p class="section-title">Accesos Rápidos</p>
      <div class="quick-grid">
        <a href="?vista=transferir" class="quick-card">
          <div class="quick-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/>
              <polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/>
            </svg>
          </div>
          <span>Nueva transferencia</span>
        </a>
        <a href="?vista=terceros" class="quick-card">
          <div class="quick-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
            </svg>
          </div>
          <span>Agregar tercero</span>
        </a>
        <a href="?vista=historial" class="quick-card">
          <div class="quick-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
          </div>
          <span>Ver movimientos</span>
        </a>
      </div>

      <!-- Últimos movimientos resumidos -->
      <?php if (!empty($historial)): ?>
        <p class="section-title">Últimos movimientos</p>
        <div class="panel" style="padding: 0; overflow: hidden;">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Fecha</th><th>Tipo</th><th>Descripción</th><th style="text-align:right">Monto</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (array_slice($historial, 0, 5) as $t): ?>
                  <tr>
                    <td style="color:var(--muted); font-size:0.82rem"><?= htmlspecialchars($t['creado_en'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                      <span class="badge badge-<?= $t['tipo'] === 'deposito' ? 'dep' : ($t['tipo'] === 'retiro' ? 'ret' : 'trf') ?>">
                        <?= ucfirst(htmlspecialchars($t['tipo'], ENT_QUOTES, 'UTF-8')) ?>
                      </span>
                    </td>
                    <td style="color:var(--muted)"><?= htmlspecialchars($t['descripcion'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="text-align:right; font-weight:500; color:<?= $t['tipo']==='deposito' ? 'var(--green)' : 'var(--red)' ?>">
                      <?= $t['tipo']==='deposito' ? '+' : '-' ?>Q <?= number_format($t['monto'], 2) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    <!-- =================== TRANSFERIR =================== -->
    <?php elseif ($vista === 'transferir'): ?>
      <div class="summary-card" style="margin-bottom:1.5rem; max-width:260px;">
        <div class="label">Saldo disponible</div>
        <div class="value gold">Q <?= number_format($cuenta['saldo'], 2) ?></div>
      </div>

      <?php if (empty($terceros)): ?>
        <div class="panel">
          <div class="empty-state">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <p>Aún no tienes cuentas de terceros registradas.<br>
               <a href="?vista=terceros" style="color:var(--gold)">Agregar una cuenta</a></p>
          </div>
        </div>
      <?php else: ?>
        <div class="panel">
          <form method="POST" action="?vista=transferir">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="transferir">
            <div class="form-grid">
              <div class="field">
                <label>Cuenta destino</label>
                <select name="tercero" required>
                  <?php foreach ($terceros as $t): ?>
                    <option value="<?= $t['id'] ?>">
                      <?= htmlspecialchars($t['alias'], ENT_QUOTES, 'UTF-8') ?>
                      (<?= htmlspecialchars($t['cuenta_destino'], ENT_QUOTES, 'UTF-8') ?>)
                      — Límite Q <?= number_format($t['monto_maximo'], 2) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label>Monto (Q)</label>
                <input type="number" step="0.01" min="0.01" name="monto" placeholder="0.00" required>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-gold">Realizar Transferencia</button>
              <a href="?vista=inicio" class="btn btn-outline">Cancelar</a>
            </div>
          </form>
        </div>

        <p class="section-title" style="font-size:0.9rem; color:var(--muted)">Mis cuentas de terceros</p>
        <div class="panel" style="padding:0; overflow:hidden;">
          <div class="table-wrap">
            <table>
              <thead><tr><th>Alias</th><th>N° Cuenta</th><th>Límite monto</th><th>Máx. diario</th></tr></thead>
              <tbody>
                <?php foreach ($terceros as $t): ?>
                  <tr>
                    <td><?= htmlspecialchars($t['alias'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="color:var(--muted)"><?= htmlspecialchars($t['cuenta_destino'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>Q <?= number_format($t['monto_maximo'], 2) ?></td>
                    <td><?= (int)$t['transacciones_max'] ?> transac.</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    <!-- =================== TERCEROS =================== -->
    <?php elseif ($vista === 'terceros'): ?>
      <p class="section-title">Agregar cuenta de tercero</p>
      <div class="panel">
        <form method="POST" action="?vista=terceros">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_tercero">
          <div class="form-grid">
            <div class="field">
              <label>Número de cuenta destino</label>
              <input type="text" name="destino" placeholder="001-123456-7" required>
            </div>
            <div class="field">
              <label>Alias (nombre para recordar)</label>
              <input type="text" name="alias" placeholder="Ej. Mamá, Renta, etc." required>
            </div>
            <div class="field">
              <label>Monto máximo por transferencia (Q)</label>
              <input type="number" step="0.01" min="1" name="monto_max" placeholder="0.00" required>
            </div>
            <div class="field">
              <label>Máx. transferencias por día</label>
              <input type="number" min="1" name="max_diario" placeholder="Ej. 3" required>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-gold">Agregar Cuenta</button>
          </div>
        </form>
      </div>

      <p class="section-title">Cuentas registradas</p>
      <?php if (empty($terceros)): ?>
        <div class="panel">
          <div class="empty-state">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
            </svg>
            <p>No tienes cuentas de terceros registradas aún.</p>
          </div>
        </div>
      <?php else: ?>
        <div class="panel" style="padding:0; overflow:hidden;">
          <div class="table-wrap">
            <table>
              <thead><tr><th>Alias</th><th>N° Cuenta</th><th>Límite monto</th><th>Máx. diario</th></tr></thead>
              <tbody>
                <?php foreach ($terceros as $t): ?>
                  <tr>
                    <td><?= htmlspecialchars($t['alias'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="color:var(--muted)"><?= htmlspecialchars($t['cuenta_destino'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>Q <?= number_format($t['monto_maximo'], 2) ?></td>
                    <td><?= (int)$t['transacciones_max'] ?> / día</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    <!-- =================== HISTORIAL =================== -->
    <?php elseif ($vista === 'historial'): ?>
      <div class="summary-card" style="margin-bottom:1.5rem; max-width:260px;">
        <div class="label">Saldo actual</div>
        <div class="value gold">Q <?= number_format($cuenta['saldo'], 2) ?></div>
        <div class="sub"><?= htmlspecialchars($cuenta['nombre_cliente'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
      </div>

      <?php if (empty($historial)): ?>
        <div class="panel">
          <div class="empty-state">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
            <p>No hay movimientos registrados.</p>
          </div>
        </div>
      <?php else: ?>
        <div class="panel" style="padding:0; overflow:hidden;">
          <div class="table-wrap">
            <table>
              <thead>
                <tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th style="text-align:right">Monto</th></tr>
              </thead>
              <tbody>
                <?php foreach ($historial as $t): ?>
                  <tr>
                    <td style="color:var(--muted); font-size:0.82rem; white-space:nowrap">
                      <?= htmlspecialchars($t['creado_en'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td>
                      <span class="badge badge-<?= $t['tipo'] === 'deposito' ? 'dep' : ($t['tipo'] === 'retiro' ? 'ret' : 'trf') ?>">
                        <?= ucfirst(htmlspecialchars($t['tipo'], ENT_QUOTES, 'UTF-8')) ?>
                      </span>
                    </td>
                    <td style="color:var(--muted)"><?= htmlspecialchars($t['descripcion'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="text-align:right; font-weight:500; color:<?= $t['tipo']==='deposito' ? 'var(--green)' : 'var(--red)' ?>">
                      <?= $t['tipo']==='deposito' ? '+' : '-' ?>Q <?= number_format($t['monto'], 2) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  </div><!-- /content -->
</main>

</body>
</html>
