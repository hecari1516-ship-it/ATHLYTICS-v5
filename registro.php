<?php
require 'config.php';

if (isset($_SESSION['entrenador_id'])) { header('Location: plantel.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $pass2  = $_POST['password2'] ?? '';

    if ($nombre === '' || $correo === '' || strlen($pass) < 6) {
        $error = 'Completa todos los campos. La contraseña debe tener al menos 6 caracteres.';
    } elseif ($pass !== $pass2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM entrenadores WHERE correo = ?");
        $check->execute([$correo]);
        if ($check->fetchColumn() > 0) {
            $error = 'Ya existe una cuenta con ese correo.';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO entrenadores (nombre, correo, password, rol) VALUES (?, ?, ?, 'entrenador')");
            $stmt->execute([$nombre, $correo, $hash]);
            $_SESSION['entrenador_id'] = $pdo->lastInsertId();
            $_SESSION['entrenador_nombre'] = $nombre;
            header('Location: plantel.php?bienvenida=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATHLYTICS — Crear cuenta de entrenador</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <h1>ATHLY<span style="color:var(--lime)">TICS</span></h1>
    <div class="sub">Crea tu cuenta de entrenador — tu propio plantel, privado y separado del resto.</div>

    <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
      <div class="field">
        <label>Nombre completo</label>
        <input type="text" name="nombre" required autofocus placeholder="Ej. Marisol Peña" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Correo</label>
        <input type="email" name="correo" required placeholder="tucorreo@club.com" value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Contraseña</label>
        <input type="password" name="password" required placeholder="Mínimo 6 caracteres">
      </div>
      <div class="field">
        <label>Confirmar contraseña</label>
        <input type="password" name="password2" required placeholder="Repite tu contraseña">
      </div>
      <button class="btn" type="submit">Crear cuenta</button>
    </form>
    <p class="muted" style="margin-top:18px; font-size:12px;">
      ¿Ya tienes cuenta? <a href="index.php">Inicia sesión</a>
    </p>
  </div>
</div>
</body>
</html>
