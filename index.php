<?php require 'config.php';

if (isset($_SESSION['entrenador_id'])) { header('Location: plantel.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $pass   = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM entrenadores WHERE correo = ?");
    $stmt->execute([$correo]);
    $entrenador = $stmt->fetch();

    if ($entrenador && password_verify($pass, $entrenador['password'])) {
        $_SESSION['entrenador_id'] = $entrenador['id'];
        $_SESSION['entrenador_nombre'] = $entrenador['nombre'];
        header('Location: plantel.php');
        exit;
    } else {
        $error = 'Correo o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATHLYTICS — Iniciar sesión</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <h1>ATHLY<span style="color:var(--lime)">TICS</span></h1>
    <div class="sub">Monitoreo de carga y rendimiento para entrenadores</div>

    <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="field">
        <label>Correo</label>
        <input type="email" name="correo" required autofocus placeholder="admin@athlytics.com">
      </div>
      <div class="field">
        <label>Contraseña</label>
        <input type="password" name="password" required placeholder="••••••••">
      </div>
      <button class="btn" type="submit">Entrar</button>
    </form>
    <p class="muted" style="margin-top:18px; font-size:12px;">
      ¿Eres un entrenador nuevo? <a href="registro.php">Crea tu cuenta →</a>
    </p>
  </div>
</div>
</body>
</html>
