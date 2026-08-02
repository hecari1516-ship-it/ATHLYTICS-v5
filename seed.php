<?php
require 'config.php';

$check = $pdo->query("SELECT COUNT(*) FROM entrenadores")->fetchColumn();

if ($check > 0) {
    die('El entrenador ya existe. Borra este archivo (seed.php) por seguridad y ve a index.php a iniciar sesión.');
}

$nombre = 'Carlos Medina';
$correo = 'admin@athlytics.com';
$passwordPlano = 'admin123';
$hash = password_hash($passwordPlano, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO entrenadores (nombre, correo, password, rol) VALUES (?, ?, ?, 'admin')");
$stmt->execute([$nombre, $correo, $hash]);

echo "<div style='font-family:sans-serif;padding:40px;background:#10201B;color:#F2F1E8;'>";
echo "<h2>Entrenador administrador creado correctamente ✔</h2>";
echo "<p>Correo: <b>$correo</b></p>";
echo "<p>Contraseña: <b>$passwordPlano</b></p>";
echo "<p>Este usuario queda como <b>admin</b>: puede ver la gestión de entrenadores. Cualquier otro entrenador puede registrarse por su cuenta en <a style='color:#B7F03C' href='registro.php'>registro.php</a>.</p>";
echo "<p><a style='color:#B7F03C' href='index.php'>Ir al login →</a></p>";
echo "<p style='color:#8FA79B'>Por seguridad, borra o renombra seed.php después de usarlo.</p>";
echo "</div>";
