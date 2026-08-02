<?php
require 'config.php';
require 'auth_check.php';

$entrenadorId = $_SESSION['entrenador_id'];
$sesionId = (int)($_GET['sesion_id'] ?? 0);

// --- Paso 1: crear la sesión ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_sesion'])) {
    $fecha = $_POST['fecha'];
    $tipo = $_POST['tipo'];
    $duracion = (int)$_POST['duracion_min'];
    $stmt = $pdo->prepare("INSERT INTO sesiones (entrenador_id, fecha, tipo, duracion_min) VALUES (?,?,?,?)");
    $stmt->execute([$entrenadorId, $fecha, $tipo, $duracion]);
    $sesionId = $pdo->lastInsertId();
    header("Location: registrar_sesion.php?sesion_id=$sesionId");
    exit;
}

// --- Paso 2: capturar asistencia + RPE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_asistencia'])) {
    $sesionId = (int)$_POST['sesion_id'];
    $jugadoresIds = $_POST['jugador_id'] ?? [];
    $stmt = $pdo->prepare("INSERT INTO asistencia (sesion_id, jugador_id, asistio, rpe)
                            VALUES (?,?,?,?)
                            ON DUPLICATE KEY UPDATE asistio=VALUES(asistio), rpe=VALUES(rpe)");
    foreach ($jugadoresIds as $jid) {
        $jid = (int)$jid;
        $asistio = isset($_POST["asistio_$jid"]) ? 1 : 0;
        $rpe = $asistio ? (int)($_POST["rpe_$jid"] ?? 0) : null;
        $stmt->execute([$sesionId, $jid, $asistio, $rpe ?: null]);
    }
    header("Location: plantel.php?msg=guardado");
    exit;
}

$sesionActual = null;
if ($sesionId) {
    $s = $pdo->prepare("SELECT * FROM sesiones WHERE id=? AND entrenador_id=?");
    $s->execute([$sesionId, $entrenadorId]);
    $sesionActual = $s->fetch();
}

$jugadores = $pdo->prepare("SELECT id, nombre, posicion FROM jugadores WHERE entrenador_id=? ORDER BY nombre");
$jugadores->execute([$entrenadorId]);
$jugadores = $jugadores->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATHLYTICS — Registrar sesión</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php $activo = 'sesion'; require 'nav.php'; ?>
<div class="wrap">

<?php if (!$sesionActual): ?>
  <div class="page-head">
    <div><h1>Nueva sesión de entrenamiento</h1></div>
    <a href="partidos.php" class="btn btn-sm btn-ghost">Ver partidos</a>
  </div>
  <form method="post" class="panel" style="max-width:420px;">
    <input type="hidden" name="crear_sesion" value="1">
    <div class="field">
      <label>Fecha</label>
      <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="field">
      <label>Tipo de sesión</label>
      <select name="tipo" required>
        <option value="Físico">Físico</option>
        <option value="Técnico">Técnico</option>
        <option value="Táctico">Táctico</option>
        <option value="Partido amistoso">Partido amistoso</option>
      </select>
    </div>
    <div class="field">
      <label>Duración (minutos)</label>
      <input type="number" name="duracion_min" min="10" max="240" value="70" required>
    </div>
    <button class="btn" type="submit">Continuar →</button>
  </form>

<?php else: ?>
  <h1>Pase de lista + esfuerzo (RPE)</h1>
  <p class="muted" style="margin-bottom:20px;">
    <?= htmlspecialchars($sesionActual['tipo']) ?> · <?= $sesionActual['fecha'] ?> · <?= $sesionActual['duracion_min'] ?> min
  </p>

  <form method="post">
    <input type="hidden" name="guardar_asistencia" value="1">
    <input type="hidden" name="sesion_id" value="<?= $sesionActual['id'] ?>">

    <?php foreach ($jugadores as $j): ?>
      <input type="hidden" name="jugador_id[]" value="<?= $j['id'] ?>">
      <div class="rpe-row" id="row-<?= $j['id'] ?>">
        <span class="rpe-name"><?= htmlspecialchars($j['nombre']) ?> <span class="muted">(<?= $j['posicion'] ?>)</span></span>
        <label class="asistio-toggle">
          <input type="checkbox" name="asistio_<?= $j['id'] ?>" checked onchange="toggleFila(<?= $j['id'] ?>)"> Asistió
        </label>
        <div class="rpe-scale" data-jugador="<?= $j['id'] ?>">
          <?php for ($n=1;$n<=10;$n++): ?>
            <button type="button" data-val="<?= $n ?>" onclick="marcarRPE(<?= $j['id'] ?>, <?= $n ?>, this)"><?= $n ?></button>
          <?php endfor; ?>
        </div>
        <input type="hidden" name="rpe_<?= $j['id'] ?>" id="rpe-input-<?= $j['id'] ?>" value="5">
      </div>
    <?php endforeach; ?>

    <button class="btn" type="submit" style="margin-top:10px; max-width:260px;">Guardar sesión</button>
  </form>
<?php endif; ?>

</div>

<script>
function marcarRPE(jugadorId, valor, btn) {
  document.getElementById('rpe-input-' + jugadorId).value = valor;
  const scale = btn.parentElement;
  [...scale.children].forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}
function toggleFila(jugadorId) {
  const row = document.getElementById('row-' + jugadorId);
  const checked = row.querySelector('input[type=checkbox]').checked;
  row.style.opacity = checked ? '1' : '0.4';
}
</script>
</body>
</html>
