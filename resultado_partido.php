<?php
require 'config.php';
require 'auth_check.php';

$entrenadorId = $_SESSION['entrenador_id'];
$id = (int)($_GET['id'] ?? $_POST['partido_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM partidos WHERE id=? AND entrenador_id=?");
$stmt->execute([$id, $entrenadorId]);
$partido = $stmt->fetch();
if (!$partido) { header('Location: partidos.php'); exit; }

$dep = deporte_info($DEPORTES, (int)$partido['deporte_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_resultado'])) {
    $favor = (int)$_POST['resultado_favor'];
    $contra = (int)$_POST['resultado_contra'];
    $notas = trim($_POST['notas'] ?? '');
    $upd = $pdo->prepare("UPDATE partidos SET resultado_favor=?, resultado_contra=?, notas=? WHERE id=?");
    $upd->execute([$favor, $contra, $notas ?: null, $id]);

    $jugadoresIds = $_POST['jugador_id'] ?? [];
    $ins = $pdo->prepare("
      INSERT INTO partido_jugadores (partido_id, jugador_id, titular, minutos, goles_puntos, asistencias, pases_completados, tiros, robos_rebotes, faltas, calificacion_partido)
      VALUES (?,?,?,?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE titular=VALUES(titular), minutos=VALUES(minutos), goles_puntos=VALUES(goles_puntos),
        asistencias=VALUES(asistencias), pases_completados=VALUES(pases_completados), tiros=VALUES(tiros),
        robos_rebotes=VALUES(robos_rebotes), faltas=VALUES(faltas), calificacion_partido=VALUES(calificacion_partido)");
    foreach ($jugadoresIds as $jid) {
        $jid = (int)$jid;
        if (!isset($_POST["incluir_$jid"])) continue;
        $titular = isset($_POST["titular_$jid"]) ? 1 : 0;
        $minutos = (int)($_POST["minutos_$jid"] ?? 0);
        $goles = (int)($_POST["goles_$jid"] ?? 0);
        $asist = (int)($_POST["asistencias_$jid"] ?? 0);
        $pases = (int)($_POST["pases_$jid"] ?? 0);
        $tiros = (int)($_POST["tiros_$jid"] ?? 0);
        $robos = (int)($_POST["robos_$jid"] ?? 0);
        $faltas = (int)($_POST["faltas_$jid"] ?? 0);
        $calRaw = $_POST["calificacion_$jid"] ?? '';
        $cal = $calRaw !== '' ? (float)$calRaw : null;
        $ins->execute([$id, $jid, $titular, $minutos, $goles, $asist, $pases, $tiros, $robos, $faltas, $cal]);
    }
    header('Location: partidos.php?guardado=1');
    exit;
}

// Jugadores del plantel de ese deporte
$jugStmt = $pdo->prepare("SELECT id, nombre, posicion FROM jugadores WHERE entrenador_id=? AND deporte_id=? ORDER BY nombre");
$jugStmt->execute([$entrenadorId, $partido['deporte_id']]);
$jugadores = $jugStmt->fetchAll();

// Datos ya capturados de este partido (si existen)
$capStmt = $pdo->prepare("SELECT * FROM partido_jugadores WHERE partido_id=?");
$capStmt->execute([$id]);
$capturado = [];
foreach ($capStmt->fetchAll() as $row) { $capturado[$row['jugador_id']] = $row; }

// Titulares desde la formación asignada (si tiene)
$titularesDefault = [];
if ($partido['formacion_id']) {
    $f = $pdo->prepare("SELECT layout_json FROM formaciones WHERE id=?");
    $f->execute([$partido['formacion_id']]);
    $layout = json_decode($f->fetchColumn() ?: '[]', true);
    foreach ($layout as $slot) { if (!empty($slot['jugador_id'])) $titularesDefault[(int)$slot['jugador_id']] = true; }
}

$activo = 'partidos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATHLYTICS — Resultado de partido</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require 'nav.php'; ?>
<div class="wrap">
  <p><a href="partidos.php">&larr; Volver a partidos</a></p>
  <div class="page-head">
    <div>
      <div class="eyebrow"><span class="sport-chip-mini"><?= $dep['icono'] ?></span> <?= $dep['nombre'] ?> · <?= $partido['fecha'] ?> · <?= $partido['sede'] ?></div>
      <h1>vs. <?= htmlspecialchars($partido['rival']) ?></h1>
    </div>
  </div>

  <form method="post">
    <input type="hidden" name="guardar_resultado" value="1">
    <input type="hidden" name="partido_id" value="<?= $id ?>">

    <div class="panel" style="max-width:420px;">
      <h3>RESULTADO DEL EQUIPO</h3>
      <div class="flex-between" style="gap:16px;">
        <div class="field" style="flex:1;">
          <label>Nuestro marcador</label>
          <input type="number" name="resultado_favor" min="0" value="<?= $partido['resultado_favor'] ?? 0 ?>">
        </div>
        <div class="field" style="flex:1;">
          <label>Marcador rival</label>
          <input type="number" name="resultado_contra" min="0" value="<?= $partido['resultado_contra'] ?? 0 ?>">
        </div>
      </div>
      <div class="field">
        <label>Notas del partido — opcional</label>
        <textarea name="notas" rows="2" style="width:100%; padding:11px 12px; background:var(--surface-2); border:1px solid var(--line); border-radius:6px; color:var(--chalk); font-family:inherit;"><?= htmlspecialchars($partido['notas'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="panel">
      <h3>RENDIMIENTO INDIVIDUAL</h3>
      <p class="muted" style="font-size:12px; margin-bottom:14px;">Marca "Incluir" para los jugadores que participaron y captura sus estadísticas.</p>

      <div class="stat-table-wrap">
      <table class="data stat-table">
        <tr>
          <th>Incluir</th><th>Jugador</th><th>Titular</th><th>Min</th>
          <th><?= $dep['stat_labels']['goles_puntos'] ?></th>
          <th><?= $dep['stat_labels']['asistencias'] ?></th>
          <th><?= $dep['stat_labels']['pases_completados'] ?></th>
          <th><?= $dep['stat_labels']['tiros'] ?></th>
          <th><?= $dep['stat_labels']['robos_rebotes'] ?></th>
          <th><?= $dep['stat_labels']['faltas'] ?></th>
          <th>Nota (1-10)</th>
        </tr>
        <?php foreach ($jugadores as $pj):
          $c = $capturado[$pj['id']] ?? null;
          $incluido = $c !== null;
          $titular = $c ? $c['titular'] : (isset($titularesDefault[$pj['id']]) ? 1 : 0);
        ?>
        <tr>
          <td><input type="checkbox" name="incluir_<?= $pj['id'] ?>" <?= $incluido || $titular ? 'checked' : '' ?>>
              <input type="hidden" name="jugador_id[]" value="<?= $pj['id'] ?>"></td>
          <td><?= htmlspecialchars($pj['nombre']) ?> <span class="muted">(<?= htmlspecialchars($pj['posicion']) ?>)</span></td>
          <td><input type="checkbox" name="titular_<?= $pj['id'] ?>" <?= $titular ? 'checked' : '' ?>></td>
          <td><input type="number" class="stat-input" name="minutos_<?= $pj['id'] ?>" min="0" value="<?= $c['minutos'] ?? 0 ?>"></td>
          <td><input type="number" class="stat-input" name="goles_<?= $pj['id'] ?>" min="0" value="<?= $c['goles_puntos'] ?? 0 ?>"></td>
          <td><input type="number" class="stat-input" name="asistencias_<?= $pj['id'] ?>" min="0" value="<?= $c['asistencias'] ?? 0 ?>"></td>
          <td><input type="number" class="stat-input" name="pases_<?= $pj['id'] ?>" min="0" value="<?= $c['pases_completados'] ?? 0 ?>"></td>
          <td><input type="number" class="stat-input" name="tiros_<?= $pj['id'] ?>" min="0" value="<?= $c['tiros'] ?? 0 ?>"></td>
          <td><input type="number" class="stat-input" name="robos_<?= $pj['id'] ?>" min="0" value="<?= $c['robos_rebotes'] ?? 0 ?>"></td>
          <td><input type="number" class="stat-input" name="faltas_<?= $pj['id'] ?>" min="0" value="<?= $c['faltas'] ?? 0 ?>"></td>
          <td><input type="number" class="stat-input" step="0.5" min="1" max="10" name="calificacion_<?= $pj['id'] ?>" value="<?= $c['calificacion_partido'] ?? '' ?>"></td>
        </tr>
        <?php endforeach; ?>
      </table>
      </div>
    </div>

    <button class="btn" type="submit" style="max-width:280px;">Guardar resultado y estadísticas</button>
  </form>
</div>
</body>
</html>
