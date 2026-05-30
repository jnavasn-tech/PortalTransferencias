<?php
// portal_transferencias.php
session_start();
require_once "db.php";

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

$accion  = $_GET['accion'] ?? 'login';   // login | registro
$mensaje = '';
$tipo_msg = 'error';

/* ============================
   ACCIÓN: LOGIN
   ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    csrf_check();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $mensaje = 'Por favor completa todos los campos.';
    } else {
        $sql  = "SELECT * FROM usuarios WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['usuario_id'] = $user['id'];
            header("Location: portal_dashboard.php");
            exit;
        } else {
            $mensaje = 'Correo o contraseña incorrectos.';
        }
    }
}

/* ============================
   ACCIÓN: REGISTRO
   ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registro') {
    csrf_check();
    $numero_cuenta = trim($_POST['numero_cuenta'] ?? '');
    $dpi           = trim($_POST['dpi'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $pass1         = $_POST['password'] ?? '';
    $pass2         = $_POST['password2'] ?? '';

    if ($numero_cuenta === '' || $dpi === '' || $email === '' || $pass1 === '' || $pass2 === '') {
        $mensaje = 'Todos los campos son obligatorios.';
        $accion  = 'registro';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Correo electrónico inválido.';
        $accion  = 'registro';
    } elseif ($pass1 !== $pass2) {
        $mensaje = 'Las contraseñas no coinciden.';
        $accion  = 'registro';
    } else {
        try {
            $hash = password_hash($pass1, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("CALL sp_registrar_usuario(?, ?, ?, ?)");
            $stmt->bind_param("ssss", $numero_cuenta, $dpi, $email, $hash);
            $stmt->execute();
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            $mensaje  = '¡Cuenta creada! Ya puedes iniciar sesión.';
            $tipo_msg = 'ok';
            $accion   = 'login';
        } catch (mysqli_sql_exception $e) {
            $mensaje = 'Error: ' . $e->getMessage();
            $accion  = 'registro';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal de Transferencias | Banco</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --navy:    #0a1628;
      --navy2:   #112240;
      --gold:    #c9a84c;
      --gold2:   #e8c97a;
      --cream:   #f5f0e8;
      --white:   #ffffff;
      --muted:   #8892a4;
      --red:     #c0392b;
      --green:   #1a7a4a;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--navy);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
      position: relative;
      overflow: hidden;
    }

    /* Fondo decorativo */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 80% 60% at 10% 0%, rgba(201,168,76,0.08) 0%, transparent 60%),
        radial-gradient(ellipse 60% 80% at 90% 100%, rgba(17,34,64,0.9) 0%, transparent 70%);
      pointer-events: none;
    }

    /* Líneas decorativas */
    body::after {
      content: '';
      position: fixed;
      top: -50%;
      right: -20%;
      width: 600px;
      height: 600px;
      border: 1px solid rgba(201,168,76,0.08);
      border-radius: 50%;
      pointer-events: none;
    }

    /* ---- Nav de vuelta ---- */
    .back-link {
      position: fixed;
      top: 1.5rem;
      left: 1.5rem;
      color: var(--muted);
      text-decoration: none;
      font-size: 0.85rem;
      letter-spacing: 0.04em;
      display: flex;
      align-items: center;
      gap: 0.4rem;
      transition: color 0.2s;
    }
    .back-link:hover { color: var(--gold); }
    .back-link svg { width: 16px; height: 16px; }

    /* ---- Logo / Header ---- */
    .brand {
      text-align: center;
      margin-bottom: 2.5rem;
      position: relative;
      z-index: 1;
    }
    .brand-icon {
      width: 56px; height: 56px;
      background: linear-gradient(135deg, var(--gold), var(--gold2));
      border-radius: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1rem;
      box-shadow: 0 8px 24px rgba(201,168,76,0.3);
    }
    .brand-icon svg { width: 28px; height: 28px; color: var(--navy); }
    .brand h1 {
      font-family: 'Playfair Display', serif;
      font-size: 1.7rem;
      color: var(--white);
      letter-spacing: 0.02em;
    }
    .brand p {
      color: var(--muted);
      font-size: 0.85rem;
      margin-top: 0.3rem;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }

    /* ---- Card principal ---- */
    .card {
      width: 100%;
      max-width: 440px;
      background: rgba(17, 34, 64, 0.85);
      border: 1px solid rgba(201,168,76,0.15);
      border-radius: 20px;
      padding: 2.5rem 2.5rem 2rem;
      backdrop-filter: blur(12px);
      box-shadow: 0 24px 64px rgba(0,0,0,0.5);
      position: relative;
      z-index: 1;
      animation: fadeUp 0.5s ease both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ---- Tabs ---- */
    .tabs {
      display: flex;
      gap: 0;
      background: rgba(10,22,40,0.6);
      border-radius: 10px;
      padding: 4px;
      margin-bottom: 2rem;
    }
    .tab-btn {
      flex: 1;
      padding: 0.6rem 1rem;
      border: none;
      background: transparent;
      color: var(--muted);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.9rem;
      font-weight: 500;
      border-radius: 7px;
      cursor: pointer;
      transition: all 0.25s;
      text-decoration: none;
      text-align: center;
      display: block;
    }
    .tab-btn.active {
      background: linear-gradient(135deg, var(--gold), var(--gold2));
      color: var(--navy);
      font-weight: 600;
      box-shadow: 0 2px 10px rgba(201,168,76,0.3);
    }
    .tab-btn:hover:not(.active) { color: var(--white); }

    /* ---- Mensajes ---- */
    .alert {
      padding: 0.8rem 1rem;
      border-radius: 8px;
      font-size: 0.88rem;
      margin-bottom: 1.4rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .alert.error { background: rgba(192,57,43,0.15); border: 1px solid rgba(192,57,43,0.35); color: #e07a6e; }
    .alert.ok    { background: rgba(26,122,74,0.15);  border: 1px solid rgba(26,122,74,0.35);  color: #5ecf8e; }

    /* ---- Formulario ---- */
    .form-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.3rem;
      color: var(--white);
      margin-bottom: 1.5rem;
    }

    .field { margin-bottom: 1.2rem; }
    .field label {
      display: block;
      font-size: 0.78rem;
      color: var(--muted);
      letter-spacing: 0.08em;
      text-transform: uppercase;
      margin-bottom: 0.45rem;
      font-weight: 500;
    }
    .field input {
      width: 100%;
      padding: 0.75rem 1rem;
      background: rgba(10,22,40,0.7);
      border: 1px solid rgba(201,168,76,0.15);
      border-radius: 9px;
      color: var(--white);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.95rem;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .field input:focus {
      border-color: var(--gold);
      box-shadow: 0 0 0 3px rgba(201,168,76,0.12);
    }
    .field input::placeholder { color: rgba(136,146,164,0.5); }

    .btn-submit {
      width: 100%;
      padding: 0.9rem;
      background: linear-gradient(135deg, var(--gold) 0%, var(--gold2) 100%);
      color: var(--navy);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.95rem;
      font-weight: 700;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      letter-spacing: 0.05em;
      transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
      box-shadow: 0 4px 20px rgba(201,168,76,0.25);
      margin-top: 0.5rem;
    }
    .btn-submit:hover {
      opacity: 0.92;
      transform: translateY(-1px);
      box-shadow: 0 8px 28px rgba(201,168,76,0.35);
    }
    .btn-submit:active { transform: translateY(0); }

    /* ---- Divider texto ---- */
    .divider {
      text-align: center;
      color: var(--muted);
      font-size: 0.82rem;
      margin-top: 1.5rem;
    }
    .divider a { color: var(--gold); text-decoration: none; font-weight: 500; }
    .divider a:hover { text-decoration: underline; }

    /* ---- Pie de card ---- */
    .card-footer {
      margin-top: 2rem;
      padding-top: 1.2rem;
      border-top: 1px solid rgba(201,168,76,0.1);
      display: flex;
      justify-content: center;
      gap: 1.5rem;
    }
    .card-footer a {
      color: var(--muted);
      font-size: 0.8rem;
      text-decoration: none;
      transition: color 0.2s;
    }
    .card-footer a:hover { color: var(--gold); }

    /* Password reveal toggle */
    .field-pw { position: relative; }
    .field-pw input { padding-right: 2.8rem; }
    .toggle-pw {
      position: absolute;
      right: 0.9rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: var(--muted);
      padding: 0;
      display: flex;
      align-items: center;
    }
    .toggle-pw:hover { color: var(--gold); }
    .toggle-pw svg { width: 18px; height: 18px; }

    /* Hint de contraseña */
    .hint { font-size: 0.78rem; color: var(--muted); margin-top: 0.35rem; }

    @media (max-width: 480px) {
      .card { padding: 2rem 1.5rem; }
    }
  </style>
</head>
<body>

  <a href="index.php" class="back-link">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
    </svg>
    Volver al inicio
  </a>

  <!-- Brand -->
  <div class="brand">
    <div class="brand-icon">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/>
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M8 12h8M12 8v8"/>
      </svg>
    </div>
    <h1>Portal de Transferencias</h1>
    <p>Banca en Línea Segura</p>
  </div>

  <!-- Card -->
  <div class="card">

    <!-- Tabs -->
    <div class="tabs">
      <a href="?accion=login"
         class="tab-btn <?= $accion === 'login' ? 'active' : '' ?>">
        Iniciar Sesión
      </a>
      <a href="?accion=registro"
         class="tab-btn <?= $accion === 'registro' ? 'active' : '' ?>">
        Registrarme
      </a>
    </div>

    <!-- Mensaje -->
    <?php if ($mensaje): ?>
      <div class="alert <?= $tipo_msg ?>">
        <?php if ($tipo_msg === 'error'): ?>
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
          </svg>
        <?php else: ?>
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
        <?php endif; ?>
        <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- ===================== FORM LOGIN ===================== -->
    <?php if ($accion === 'login'): ?>
      <p class="form-title">Bienvenido de vuelta</p>
      <form method="POST" action="?accion=login">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="login">

        <div class="field">
          <label>Correo electrónico</label>
          <input type="email" name="email" placeholder="tucorreo@email.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="field">
          <label>Contraseña</label>
          <div class="field-pw">
            <input type="password" name="password" id="pw-login" placeholder="••••••••" required>
            <button type="button" class="toggle-pw" onclick="togglePw('pw-login', this)">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit">Ingresar al Portal</button>
      </form>

      <div class="divider">
        ¿No tienes cuenta? <a href="?accion=registro">Regístrate aquí</a>
      </div>

    <!-- ===================== FORM REGISTRO ===================== -->
    <?php else: ?>
      <p class="form-title">Crear acceso en línea</p>
      <p style="color:var(--muted); font-size:0.85rem; margin-bottom:1.4rem; line-height:1.6;">
        Tu cuenta bancaria debe estar activa. El DPI debe coincidir con el registrado por el cajero.
      </p>
      <form method="POST" action="?accion=registro">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="registro">

        <div class="field">
          <label>Número de cuenta</label>
          <input type="text" name="numero_cuenta" placeholder="Ej. 001-123456-7"
                 value="<?= htmlspecialchars($_POST['numero_cuenta'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="field">
          <label>DPI</label>
          <input type="text" name="dpi" placeholder="1234 56789 0101"
                 value="<?= htmlspecialchars($_POST['dpi'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="field">
          <label>Correo electrónico</label>
          <input type="email" name="email" placeholder="tucorreo@email.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="field">
          <label>Contraseña</label>
          <div class="field-pw">
            <input type="password" name="password" id="pw-reg1" placeholder="Mínimo 6 caracteres" minlength="6" required>
            <button type="button" class="toggle-pw" onclick="togglePw('pw-reg1', this)">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="field">
          <label>Confirmar contraseña</label>
          <div class="field-pw">
            <input type="password" name="password2" id="pw-reg2" placeholder="Repite tu contraseña" minlength="6" required>
            <button type="button" class="toggle-pw" onclick="togglePw('pw-reg2', this)">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit">Crear Acceso al Portal</button>
      </form>

      <div class="divider">
        ¿Ya tienes cuenta? <a href="?accion=login">Inicia sesión</a>
      </div>
    <?php endif; ?>

    <div class="card-footer">
      <a href="index.php">Inicio</a>
      <a href="#">Ayuda</a>
      <a href="#">Privacidad</a>
    </div>
  </div>

  <script>
    function togglePw(id, btn) {
      const input = document.getElementById(id);
      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      btn.innerHTML = isHidden
        ? `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
             <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
             <line x1="1" y1="1" x2="23" y2="23"/>
           </svg>`
        : `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
             <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
             <circle cx="12" cy="12" r="3"/>
           </svg>`;
    }
  </script>

</body>
</html>
