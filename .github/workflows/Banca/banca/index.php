<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Banca en Línea</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --navy:  #0a1628;
      --navy2: #112240;
      --gold:  #c9a84c;
      --gold2: #e8c97a;
      --white: #ffffff;
      --muted: #8892a4;
      --border: rgba(201,168,76,0.12);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--navy);
      color: var(--white);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
      position: relative;
      overflow: hidden;
    }
    body::before {
      content: '';
      position: fixed; inset: 0;
      background:
        radial-gradient(ellipse 80% 50% at 50% -10%, rgba(201,168,76,0.07) 0%, transparent 60%);
      pointer-events: none;
    }

    /* ---- Header ---- */
    header {
      text-align: center;
      margin-bottom: 3.5rem;
      position: relative; z-index: 1;
    }
    .logo-icon {
      width: 64px; height: 64px;
      background: linear-gradient(135deg, var(--gold), var(--gold2));
      border-radius: 18px;
      display: inline-flex; align-items: center; justify-content: center;
      margin-bottom: 1.2rem;
      box-shadow: 0 10px 32px rgba(201,168,76,0.3);
    }
    .logo-icon svg { width: 32px; height: 32px; color: var(--navy); }
    header h1 {
      font-family: 'Playfair Display', serif;
      font-size: 2.2rem;
      color: var(--white);
      letter-spacing: 0.01em;
    }
    header p {
      color: var(--muted);
      font-size: 0.9rem;
      margin-top: 0.5rem;
      letter-spacing: 0.04em;
    }

    /* ---- Grid de cards ---- */
    .cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1.2rem;
      width: 100%;
      max-width: 860px;
      position: relative; z-index: 1;
    }

    .card {
      background: var(--navy2);
      border: 1px solid var(--border);
      border-radius: 18px;
      padding: 1.8rem 1.6rem;
      text-decoration: none;
      color: var(--muted);
      transition: all 0.3s;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      position: relative;
      overflow: hidden;
    }
    .card::after {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 2px;
      background: linear-gradient(90deg, transparent, var(--gold), transparent);
      opacity: 0;
      transition: opacity 0.3s;
    }
    .card:hover {
      border-color: rgba(201,168,76,0.35);
      color: var(--white);
      transform: translateY(-4px);
      box-shadow: 0 16px 40px rgba(0,0,0,0.4);
    }
    .card:hover::after { opacity: 1; }

    /* Card destacada: Portal */
    .card.featured {
      background: linear-gradient(135deg, #1a2f50 0%, #0e1e38 100%);
      border-color: rgba(201,168,76,0.3);
      grid-column: span 2;
    }
    @media (max-width: 600px) { .card.featured { grid-column: span 1; } }

    .card-icon {
      width: 46px; height: 46px;
      background: rgba(201,168,76,0.1);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
    }
    .card-icon svg { width: 22px; height: 22px; color: var(--gold); }

    .card h3 {
      font-family: 'Playfair Display', serif;
      font-size: 1.05rem;
      color: var(--white);
      margin-bottom: 0.2rem;
    }
    .card p { font-size: 0.83rem; line-height: 1.5; }

    .card-arrow {
      display: flex; align-items: center; gap: 0.4rem;
      font-size: 0.82rem;
      color: var(--gold);
      margin-top: auto;
      font-weight: 500;
    }
    .card-arrow svg { width: 14px; height: 14px; transition: transform 0.2s; }
    .card:hover .card-arrow svg { transform: translateX(4px); }

    /* Badge "Recomendado" */
    .badge-rec {
      position: absolute;
      top: 1rem; right: 1rem;
      background: linear-gradient(135deg, var(--gold), var(--gold2));
      color: var(--navy);
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      padding: 0.25rem 0.6rem;
      border-radius: 20px;
    }

    /* ---- Divider entre secciones ---- */
    .section-label {
      width: 100%;
      max-width: 860px;
      font-size: 0.72rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.1em;
      margin-bottom: 0.7rem;
      margin-top: 1.5rem;
      position: relative; z-index: 1;
    }

    /* ---- Footer ---- */
    footer {
      margin-top: 3rem;
      color: var(--muted);
      font-size: 0.78rem;
      text-align: center;
      position: relative; z-index: 1;
    }
  </style>
</head>
<body>

  <header>
    <div class="logo-icon">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M3 6l9-4 9 4v2H3V6zM3 10h18v10H3V10zM9 14v4M15 14v4"/>
      </svg>
    </div>
    <h1>Bienvenido al Banco</h1>
    <p>Servicios bancarios en línea — seguros y confiables</p>
  </header>

  <!-- Portal destacado -->
  <div class="section-label">Portal de clientes</div>
  <div class="cards-grid" style="max-width:860px; margin-bottom:0;">
    <a href="portal_transferencias.php" class="card featured">
      <span class="badge-rec">⭐ Recomendado</span>
      <div class="card-icon" style="background: rgba(201,168,76,0.15);">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/>
          <polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/>
        </svg>
      </div>
      <div>
        <h3>Portal de Transferencias</h3>
        <p>Accede a tu cuenta, realiza transferencias a terceros, consulta tu estado de cuenta y gestiona tus destinatarios — todo en un solo lugar.</p>
      </div>
      <span class="card-arrow">
        Entrar al portal
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6"/>
        </svg>
      </span>
    </a>
  </div>

  <!-- Accesos de staff -->
  <div class="section-label" style="margin-top:2rem;">Acceso para personal</div>
  <div class="cards-grid">

    <a href="login_admin.php" class="card">
      <div class="card-icon">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
        </svg>
      </div>
      <div>
        <h3>Administrador</h3>
        <p>Panel de gestión de cajeros y monitor de transferencias.</p>
      </div>
      <span class="card-arrow">
        Iniciar sesión
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6"/>
        </svg>
      </span>
    </a>

    <a href="login_cajero.php" class="card">
      <div class="card-icon">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
          <line x1="1" y1="10" x2="23" y2="10"/>
        </svg>
      </div>
      <div>
        <h3>Cajero</h3>
        <p>Crear cuentas, realizar depósitos y retiros en ventanilla.</p>
      </div>
      <span class="card-arrow">
        Iniciar sesión
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6"/>
        </svg>
      </span>
    </a>

    <a href="login_usuario.php" class="card">
      <div class="card-icon">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
      </div>
      <div>
        <h3>Usuario (clásico)</h3>
        <p>Acceso clásico a la cuenta de usuario.</p>
      </div>
      <span class="card-arrow">
        Iniciar sesión
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6"/>
        </svg>
      </span>
    </a>

    <a href="registro_usuario.php" class="card">
      <div class="card-icon">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <line x1="19" y1="8" x2="19" y2="14"/>
          <line x1="22" y1="11" x2="16" y2="11"/>
        </svg>
      </div>
      <div>
        <h3>Registrar Usuario</h3>
        <p>Crear acceso en línea con número de cuenta y DPI.</p>
      </div>
      <span class="card-arrow">
        Registrarse
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6"/>
        </svg>
      </span>
    </a>

  </div>

  <footer>
    &copy; <?= date('Y') ?> Banco en Línea &mdash; Todos los derechos reservados
  </footer>

</body>
</html>
