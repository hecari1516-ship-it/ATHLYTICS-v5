<?php
require 'config.php';
require 'auth_check.php';

$entrenadorId = $_SESSION['entrenador_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_formacion'])) {
    $nombre = trim($_POST['nombre'] ?? 'Mi formación');
    $deporteId = (int)$_POST['deporte_id'];
    $esquema = $_POST['esquema'];
    $layout = $_POST['layout_json'] ?? '[]';
    json_decode($layout);
    if (json_last_error() === JSON_ERROR_NONE) {
        $stmt = $pdo->prepare("INSERT INTO formaciones (entrenador_id, deporte_id, nombre, esquema, layout_json) VALUES (?,?,?,?,?)");
        $stmt->execute([$entrenadorId, $deporteId, $nombre, $esquema, $layout]);
        header('Location: formacion.php?guardada=1&deporte=' . $deporteId . '&esquema=' . urlencode($esquema));
        exit;
    }
}

if (isset($_GET['eliminar'])) {
    $stmt = $pdo->prepare("DELETE FROM formaciones WHERE id=? AND entrenador_id=?");
    $stmt->execute([(int)$_GET['eliminar'], $entrenadorId]);
    header('Location: formacion.php');
    exit;
}

$deporteId = (int)($_GET['deporte'] ?? 1);
if (!isset($DEPORTES[$deporteId])) $deporteId = 1;
$dep = deporte_info($DEPORTES, $deporteId);
$esquemaSel = $_GET['esquema'] ?? array_key_first($dep['slots']);
if (!isset($dep['slots'][$esquemaSel])) $esquemaSel = array_key_first($dep['slots']);
$slots = $dep['slots'][$esquemaSel];

// Jugadores del plantel para ese deporte, con OVR (para autoasignar por rendimiento)
$jugStmt = $pdo->prepare("SELECT id, nombre, numero, posicion, foto, ovr FROM ficha_jugador WHERE entrenador_id=? AND deporte_id=? ORDER BY ovr DESC");
$jugStmt->execute([$entrenadorId, $deporteId]);
$jugadores = $jugStmt->fetchAll();

$guardadasStmt = $pdo->prepare("SELECT * FROM formaciones WHERE entrenador_id=? ORDER BY creado_en DESC");
$guardadasStmt->execute([$entrenadorId]);
$guardadas = $guardadasStmt->fetchAll();

$activo = 'formacion';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATHLYTICS — Formaciones</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require 'nav.php'; ?>
<div class="wrap">
  <div class="page-head">
    <div>
      <div class="eyebrow">Constructor táctico</div>
      <h1>Arma tu plantilla</h1>
    </div>
    <a href="equipo_riesgo.php" class="btn btn-sm btn-ghost">Ver mapa de riesgo</a>
  </div>

  <?php if (isset($_GET['guardada'])): ?>
    <div class="toast-ok">Formación guardada</div>
  <?php endif; ?>

  <div class="formation-controls">
    <form method="get" class="formation-controls-row">
      <div class="formation-controls-group">
        <select name="deporte" class="select-filter" onchange="this.form.submit()">
          <?php foreach ($DEPORTES as $id => $d): ?>
            <option value="<?= $id ?>" <?= $id===$deporteId?'selected':'' ?>><?= $d['nombre'] ?></option>
          <?php endforeach; ?>
        </select>
        <select name="esquema" class="select-filter" onchange="this.form.submit()">
          <?php foreach ($dep['slots'] as $esq => $s): ?>
            <option value="<?= $esq ?>" <?= $esq===$esquemaSel?'selected':'' ?>><?= $esq ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!empty($jugadores)): ?>
      <div class="formation-controls-group">
        <button class="btn btn-sm" type="button" id="btnAuto">Autoasignar por rendimiento</button>
        <button class="btn btn-sm btn-ghost" type="button" id="btnLimpiar">Limpiar cancha</button>
      </div>
      <?php endif; ?>
    </form>
  </div>

  <?php if (empty($jugadores)): ?>
    <div class="empty-state panel">Aún no tienes jugadores de <?= $dep['nombre'] ?> registrados. <a href="agregar_jugador.php">Agrega jugadores →</a></div>
  <?php else: ?>

  <div class="formation-layout">
    <div class="panel bench">
      <h3>PLANTEL</h3>
      <div id="banca" class="bench-list">
        <?php foreach ($jugadores as $pj): ?>
          <div class="chip" draggable="true" data-id="<?= $pj['id'] ?>" data-nombre="<?= htmlspecialchars($pj['nombre']) ?>" data-pos="<?= htmlspecialchars($pj['posicion']) ?>" data-ovr="<?= $pj['ovr'] ?>">
            <?php if (!empty($pj['foto'])): ?><img src="<?= htmlspecialchars($pj['foto']) ?>" class="chip-photo" alt="">
            <?php else: ?><span class="chip-pos"><?= htmlspecialchars($pj['posicion']) ?></span><?php endif; ?>
            <span class="chip-name"><?= htmlspecialchars($pj['nombre']) ?><?= $pj['numero']!==null ? ' #'.(int)$pj['numero'] : '' ?></span>
            <span class="chip-ovr"><?= $pj['ovr'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="panel">
      <div id="cancha" class="pitch <?= $dep['slug'] ?>">
        <?php if ($dep['slug'] === 'futbol'): ?>
          <div class="pitch-lines">
            <div class="center-circle"></div>
            <div class="center-line"></div>
            <div class="box box-top"></div>
            <div class="box box-bottom"></div>
          </div>
        <?php else: ?>
          <div class="pitch-lines">
            <div class="key key-top"></div>
            <div class="key key-bottom"></div>
            <div class="center-circle court"></div>
          </div>
        <?php endif; ?>

        <?php foreach ($slots as $i => $slot): ?>
          <div class="slot" data-index="<?= $i ?>" data-pos="<?= $slot['pos'] ?>" style="left:<?= $slot['x'] ?>%; top:<?= $slot['y'] ?>%;">
            <span class="slot-label"><?= $slot['pos'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <form method="post" id="formGuardar" style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
        <input type="hidden" name="guardar_formacion" value="1">
        <input type="hidden" name="deporte_id" value="<?= $deporteId ?>">
        <input type="hidden" name="esquema" value="<?= htmlspecialchars($esquemaSel) ?>">
        <input type="hidden" name="layout_json" id="layout_json" value="[]">
        <div class="field" style="margin:0; flex:1; min-width:200px;">
          <label>Nombre de la formación</label>
          <input type="text" name="nombre" required placeholder="Ej. Titular vs Norte">
        </div>
        <button class="btn" type="submit" style="width:auto;">Guardar formación</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($guardadas)): ?>
  <div class="panel" style="margin-top:24px;">
    <h3>FORMACIONES GUARDADAS</h3>
    <table class="data">
      <tr><th>Nombre</th><th>Deporte</th><th>Esquema</th><th>Creada</th><th></th></tr>
      <?php foreach ($guardadas as $g): $gd = deporte_info($DEPORTES, (int)$g['deporte_id']); ?>
      <tr>
        <td><?= htmlspecialchars($g['nombre']) ?></td>
        <td><span class="sport-chip-mini"><?= $gd['icono'] ?></span> <?= $gd['nombre'] ?></td>
        <td><?= htmlspecialchars($g['esquema']) ?></td>
        <td><?= $g['creado_en'] ?></td>
        <td><a href="formacion.php?eliminar=<?= $g['id'] ?>" onclick="return confirm('¿Eliminar esta formación?')" style="color:var(--red)">Eliminar</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
const chips = document.querySelectorAll('.chip');
const slots = document.querySelectorAll('.slot');

function ocuparSlot(slot, chip) {
  if (slot.dataset.jugadorId) {
    const prevChip = document.querySelector('.chip[data-id="' + slot.dataset.jugadorId + '"]');
    if (prevChip) prevChip.style.display = 'flex';
  }
  slot.dataset.jugadorId = chip.dataset.id;
  slot.innerHTML = '<span class="slot-player">' + chip.dataset.nombre.split(' ')[0] + '</span><span class="slot-label">' + slot.dataset.pos + '</span>';
  chip.style.display = 'none';
  slot.classList.remove('pop'); void slot.offsetWidth; slot.classList.add('pop');
}

function vaciarSlot(slot) {
  if (slot.dataset.jugadorId) {
    const chip = document.querySelector('.chip[data-id="' + slot.dataset.jugadorId + '"]');
    if (chip) chip.style.display = 'flex';
    delete slot.dataset.jugadorId;
    slot.innerHTML = '<span class="slot-label">' + slot.dataset.pos + '</span>';
  }
}

chips.forEach(chip => {
  chip.addEventListener('dragstart', e => e.dataTransfer.setData('text/plain', chip.dataset.id));
});

slots.forEach(slot => {
  slot.addEventListener('dragover', e => e.preventDefault());
  slot.addEventListener('drop', e => {
    e.preventDefault();
    const id = e.dataTransfer.getData('text/plain');
    const chip = document.querySelector('.chip[data-id="' + id + '"]');
    if (chip) { ocuparSlot(slot, chip); actualizarLayout(); }
  });
  slot.addEventListener('dblclick', () => { vaciarSlot(slot); actualizarLayout(); });
});

function actualizarLayout() {
  const layout = [];
  slots.forEach(slot => layout.push({ index: slot.dataset.index, pos: slot.dataset.pos, jugador_id: slot.dataset.jugadorId || null }));
  document.getElementById('layout_json').value = JSON.stringify(layout);
}
actualizarLayout();

// --- Autoasignación por rendimiento: mejor OVR primero, respetando la posición del slot ---
document.getElementById('btnAuto')?.addEventListener('click', () => {
  slots.forEach(vaciarSlot);
  chips.forEach(c => c.style.display = 'flex');

  const disponibles = [...chips].sort((a,b) => b.dataset.ovr - a.dataset.ovr);
  const slotsArr = [...slots];

  slotsArr.forEach(slot => {
    if (slot.dataset.jugadorId) return;
    const idx = disponibles.findIndex(c => c.dataset.pos === slot.dataset.pos && c.style.display !== 'none');
    if (idx !== -1) ocuparSlot(slot, disponibles[idx]);
  });
  slotsArr.forEach(slot => {
    if (slot.dataset.jugadorId) return;
    const idx = disponibles.findIndex(c => c.style.display !== 'none');
    if (idx !== -1) ocuparSlot(slot, disponibles[idx]);
  });
  actualizarLayout();
});

document.getElementById('btnLimpiar')?.addEventListener('click', () => {
  slots.forEach(vaciarSlot);
  actualizarLayout();
});
</script>
</body>
</html>
