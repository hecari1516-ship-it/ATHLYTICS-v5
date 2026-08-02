<?php
require 'config.php';
require 'auth_check.php';

$entrenadorId = $_SESSION['entrenador_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_partido'])) {
    $deporteId = (int)$_POST['deporte_id'];
    $rival = trim($_POST['rival']);
    $fecha = $_POST['fecha'];
    $sede = $_POST['sede'];
    $formacionId = $_POST['formacion_id'] !== '' ? (int)$_POST['formacion_id'] : null;
    $stmt = $pdo->prepare("INSERT INTO partidos (entrenador_id, deporte_id, formacion_id, rival, fecha, sede) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$entrenadorId, $deporteId, $formacionId, $rival, $fecha, $sede]);
    header('Location: resultado_partido.php?id=' . $pdo->lastInsertId());
    exit;
}

$formacionesStmt = $pdo->prepare("SELECT * FROM formaciones WHERE entrenador_id=? ORDER BY creado_en DESC");
$formacionesStmt->execute([$entrenadorId]);
$formaciones = $formacionesStmt->fetchAll();

$partidosStmt = $pdo->prepare("SELECT p.*, d.nombre AS deporte_nombre, d.id AS dep_id FROM partidos p JOIN deportes d ON d.id=p.deporte_id WHERE p.entrenador_id=? ORDER BY p.fecha DESC");
$partidosStmt->execute([$entrenadorId]);
$partidos = $partidosStmt->fetchAll();

$activo = 'partidos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATHLYTICS — Partidos</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require 'nav.php'; ?>
<div class="wrap">
  <div class="page-head">
    <div>
      <div class="eyebrow">Calendario</div>
      <h1>Partidos</h1>
    </div>
    <a href="registrar_sesion.php" class="btn btn-sm btn-ghost">Registrar entrenamiento</a>
  </div>

  <div class="panel" style="max-width:560px;">
    <h3>ASIGNAR NUEVO PARTIDO</h3>
    <form method="post">
      <input type="hidden" name="crear_partido" value="1">
      <div class="field">
        <label>Deporte</label>
        <select name="deporte_id" id="deporte_id_partido" required onchange="filtrarFormaciones()">
          <?php foreach ($DEPORTES as $id => $d): ?>
            <option value="<?= $id ?>"><?= $d['nombre'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Rival</label>
        <input type="text" name="rival" required placeholder="Ej. Deportivo Norte">
      </div>
      <div class="flex-between" style="gap:16px;">
        <div class="field" style="flex:1;">
          <label>Fecha</label>
          <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="field" style="flex:1;">
          <label>Sede</label>
          <select name="sede">
            <option value="Local">Local</option>
            <option value="Visitante">Visitante</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label>Formación a usar — opcional</label>
        <select name="formacion_id" id="formacion_id">
          <option value="">Sin formación asignada</option>
          <?php foreach ($formaciones as $f): ?>
            <option value="<?= $f['id'] ?>" data-deporte="<?= $f['deporte_id'] ?>"><?= htmlspecialchars($f['nombre']) ?> (<?= htmlspecialchars($f['esquema']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn" type="submit">Crear partido y capturar resultado →</button>
    </form>
  </div>

  <?php if (!empty($partidos)): ?>
  <div class="panel" style="margin-top:24px;">
    <h3>HISTORIAL DE PARTIDOS</h3>
    <table class="data">
      <tr><th>Fecha</th><th>Deporte</th><th>Rival</th><th>Sede</th><th>Resultado</th><th></th></tr>
      <?php foreach ($partidos as $p): $dInfo = deporte_info($DEPORTES, (int)$p['dep_id']); ?>
      <tr>
        <td><?= $p['fecha'] ?></td>
        <td><span class="sport-chip-mini"><?= $dInfo['icono'] ?></span> <?= $dInfo['nombre'] ?></td>
        <td><?= htmlspecialchars($p['rival']) ?></td>
        <td><?= $p['sede'] ?></td>
        <td class="mono"><?= $p['resultado_favor'] !== null ? $p['resultado_favor'].' - '.$p['resultado_contra'] : 'Sin capturar' ?></td>
        <td><a href="resultado_partido.php?id=<?= $p['id'] ?>">Ver / editar →</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>
<script>
function filtrarFormaciones() {
  const deporte = document.getElementById('deporte_id_partido').value;
  const sel = document.getElementById('formacion_id');
  [...sel.options].forEach(opt => {
    if (!opt.dataset.deporte) return;
    opt.hidden = opt.dataset.deporte !== deporte;
  });
}
filtrarFormaciones();
</script>
</body>
</html>
