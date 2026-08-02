<?php
require 'config.php';
require 'auth_check.php';

$entrenadorId = $_SESSION['entrenador_id'];
$jugadorId = (int)($_GET['jugador_id'] ?? 0);
$deporteFiltro = (int)($_GET['deporte'] ?? 0);
$activo = 'reportes';

// ============================================================
// VISTA INDIVIDUAL
// ============================================================
if ($jugadorId) {
    $jStmt = $pdo->prepare("SELECT * FROM ficha_jugador WHERE id=? AND entrenador_id=?");
    $jStmt->execute([$jugadorId, $entrenadorId]);
    $j = $jStmt->fetch();
    if (!$j) { header('Location: reportes.php'); exit; }
    $dep = deporte_info($DEPORTES, (int)$j['deporte_id']);

    $pStmt = $pdo->prepare("
      SELECT pj.*, p.fecha, p.rival, p.resultado_favor, p.resultado_contra
      FROM partido_jugadores pj JOIN partidos p ON p.id = pj.partido_id
      WHERE pj.jugador_id=? ORDER BY p.fecha DESC");
    $pStmt->execute([$jugadorId]);
    $partidosJ = $pStmt->fetchAll();

    $n = count($partidosJ);
    $prom = ['minutos'=>0,'goles_puntos'=>0,'asistencias'=>0,'pases_completados'=>0,'tiros'=>0,'robos_rebotes'=>0,'faltas'=>0,'calificacion_partido'=>0];
    foreach ($partidosJ as $pj) { foreach ($prom as $k=>$v) { $prom[$k] += (float)($pj[$k] ?? 0); } }
    if ($n > 0) { foreach ($prom as $k=>$v) { $prom[$k] = round($v / $n, 1); } }

    // Evolución de habilidades a lo largo del tiempo (una línea por habilidad)
    $evoStmt = $pdo->prepare("SELECT fecha, habilidad, calificacion FROM evaluaciones WHERE jugador_id=? ORDER BY fecha ASC");
    $evoStmt->execute([$jugadorId]);
    $evoRows = $evoStmt->fetchAll();
    $fechas = array_values(array_unique(array_map(fn($r)=>$r['fecha'], $evoRows)));
    $series = [];
    foreach ($dep['habilidades'] as $clave => $etiqueta) { $series[$clave] = array_fill(0, count($fechas), null); }
    foreach ($evoRows as $r) {
        if (!isset($series[$r['habilidad']])) continue;
        $idx = array_search($r['fecha'], $fechas);
        $series[$r['habilidad']][$idx] = (float)$r['calificacion'];
    }
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATHLYTICS — Reporte de <?= htmlspecialchars($j['nombre']) ?></title>
<link rel="stylesheet" href="css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
<?php require 'nav.php'; ?>
<div class="wrap">
  <p><a href="ficha.php?id=<?= $jugadorId ?>">&larr; Volver a la ficha</a> · <a href="reportes.php">Ver reporte de equipo</a></p>
  <div class="page-head">
    <div>
      <div class="eyebrow"><span class="sport-chip-mini"><?= $dep['icono'] ?></span> <?= $dep['nombre'] ?> · Reporte individual</div>
      <h1><?= htmlspecialchars($j['nombre']) ?></h1>
    </div>
  </div>

  <?php if ($n === 0): ?>
    <div class="empty-state panel">Aún no hay partidos registrados para este jugador. <a href="partidos.php">Asigna un partido →</a></div>
  <?php else: ?>

  <div class="report-cards">
    <div class="report-card"><span class="rc-value"><?= $n ?></span><span class="rc-label">Partidos</span></div>
    <div class="report-card"><span class="rc-value"><?= $prom['goles_puntos'] ?></span><span class="rc-label">Prom. <?= $dep['stat_labels']['goles_puntos'] ?></span></div>
    <div class="report-card"><span class="rc-value"><?= $prom['asistencias'] ?></span><span class="rc-label">Prom. <?= $dep['stat_labels']['asistencias'] ?></span></div>
    <div class="report-card"><span class="rc-value"><?= $prom['pases_completados'] ?></span><span class="rc-label">Prom. <?= $dep['stat_labels']['pases_completados'] ?></span></div>
    <div class="report-card"><span class="rc-value"><?= $prom['robos_rebotes'] ?></span><span class="rc-label">Prom. <?= $dep['stat_labels']['robos_rebotes'] ?></span></div>
    <div class="report-card"><span class="rc-value"><?= $prom['calificacion_partido'] ?: '—' ?></span><span class="rc-label">Nota promedio</span></div>
  </div>

  <?php if (!empty($fechas)): ?>
  <div class="panel">
    <h3>CRECIMIENTO DE HABILIDADES EN EL TIEMPO</h3>
    <canvas id="evoChart" height="200"></canvas>
  </div>
  <?php endif; ?>

  <div class="panel">
    <div class="flex-between">
      <h3 style="margin:0;">DETALLE POR PARTIDO</h3>
      <button class="btn btn-sm btn-ghost" type="button" onclick="document.getElementById('detalle-partidos').classList.toggle('hidden')">Ver más / menos</button>
    </div>
    <table class="data hidden" id="detalle-partidos">
      <tr><th>Fecha</th><th>Rival</th><th>Resultado</th><th>Min</th><th><?= $dep['stat_labels']['goles_puntos'] ?></th><th><?= $dep['stat_labels']['asistencias'] ?></th><th><?= $dep['stat_labels']['pases_completados'] ?></th><th><?= $dep['stat_labels']['tiros'] ?></th><th><?= $dep['stat_labels']['robos_rebotes'] ?></th><th>Nota</th></tr>
      <?php foreach ($partidosJ as $pj): ?>
      <tr>
        <td><?= $pj['fecha'] ?></td>
        <td><?= htmlspecialchars($pj['rival']) ?></td>
        <td class="mono"><?= $pj['resultado_favor'] !== null ? $pj['resultado_favor'].'-'.$pj['resultado_contra'] : '—' ?></td>
        <td class="mono"><?= $pj['minutos'] ?></td>
        <td class="mono"><?= $pj['goles_puntos'] ?></td>
        <td class="mono"><?= $pj['asistencias'] ?></td>
        <td class="mono"><?= $pj['pases_completados'] ?></td>
        <td class="mono"><?= $pj['tiros'] ?></td>
        <td class="mono"><?= $pj['robos_rebotes'] ?></td>
        <td class="mono"><?= $pj['calificacion_partido'] ?? '—' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>
<script>
<?php if (!empty($fechas)): ?>
new Chart(document.getElementById('evoChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($fechas) ?>,
    datasets: [
      <?php $colores = ['#B7F03C','#FFB84C','#FF5D5D','#8FA79B']; $i=0; foreach ($series as $clave => $valores): ?>
      { label: '<?= $dep['habilidades'][$clave] ?>', data: <?= json_encode($valores) ?>, borderColor: '<?= $colores[$i % count($colores)] ?>', spanGaps:true, tension:0.3 },
      <?php $i++; endforeach; ?>
    ]
  },
  options: {
    scales: {
      y: { min:0, max:10, ticks:{ color:'#8FA79B' }, grid:{ color:'rgba(255,255,255,0.06)' } },
      x: { ticks:{ color:'#8FA79B' }, grid:{ display:false } }
    },
    plugins: { legend: { labels:{ color:'#F2F1E8' } } }
  }
});
<?php endif; ?>
</script>
</body>
</html>
<?php
    exit;
}

// ============================================================
// VISTA DE EQUIPO
// ============================================================
$sqlPartidos = "SELECT p.* FROM partidos p WHERE p.entrenador_id=?";
$params = [$entrenadorId];
if ($deporteFiltro > 0) { $sqlPartidos .= " AND p.deporte_id=?"; $params[] = $deporteFiltro; }
$sqlPartidos .= " ORDER BY p.fecha DESC";
$partidosStmt = $pdo->prepare($sqlPartidos);
$partidosStmt->execute($params);
$partidosEquipo = $partidosStmt->fetchAll();

$n = count($partidosEquipo);
$gf = 0; $gc = 0; $victorias = 0; $empates = 0; $derrotas = 0;
foreach ($partidosEquipo as $p) {
    if ($p['resultado_favor'] === null) continue;
    $gf += (int)$p['resultado_favor']; $gc += (int)$p['resultado_contra'];
    if ($p['resultado_favor'] > $p['resultado_contra']) $victorias++;
    elseif ($p['resultado_favor'] == $p['resultado_contra']) $empates++;
    else $derrotas++;
}

// Promedios individuales agregados del equipo (para "promedio de pases, de gol...")
$sqlAgg = "
  SELECT AVG(pj.goles_puntos) prom_goles, AVG(pj.asistencias) prom_asist, AVG(pj.pases_completados) prom_pases,
         AVG(pj.tiros) prom_tiros, AVG(pj.robos_rebotes) prom_robos, AVG(pj.calificacion_partido) prom_nota
  FROM partido_jugadores pj JOIN partidos p ON p.id = pj.partido_id
  WHERE p.entrenador_id=?";
$paramsAgg = [$entrenadorId];
if ($deporteFiltro > 0) { $sqlAgg .= " AND p.deporte_id=?"; $paramsAgg[] = $deporteFiltro; }
$aggStmt = $pdo->prepare($sqlAgg);
$aggStmt->execute($paramsAgg);
$agg = $aggStmt->fetch();

// Top jugadores por nota promedio (para elegir a quién ver en detalle)
$sqlTop = "
  SELECT j.id, j.nombre, AVG(pj.calificacion_partido) AS nota_prom, COUNT(*) AS partidos_jugados
  FROM partido_jugadores pj JOIN jugadores j ON j.id = pj.jugador_id JOIN partidos p ON p.id = pj.partido_id
  WHERE p.entrenador_id=?";
$paramsTop = [$entrenadorId];
if ($deporteFiltro > 0) { $sqlTop .= " AND p.deporte_id=?"; $paramsTop[] = $deporteFiltro; }
$sqlTop .= " GROUP BY j.id, j.nombre ORDER BY nota_prom DESC LIMIT 8";
$topStmt = $pdo->prepare($sqlTop);
$topStmt->execute($paramsTop);
$topJugadores = $topStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATHLYTICS — Reporte de equipo</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require 'nav.php'; ?>
<div class="wrap">
  <div class="page-head">
    <div>
      <div class="eyebrow">Rendimiento del equipo</div>
      <h1>Reporte de equipo</h1>
    </div>
    <form method="get">
      <select name="deporte" class="select-filter" onchange="this.form.submit()">
        <option value="0">Todos los deportes</option>
        <?php foreach ($DEPORTES as $id => $d): ?>
          <option value="<?= $id ?>" <?= $deporteFiltro===$id?'selected':'' ?>><?= $d['nombre'] ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if ($n === 0): ?>
    <div class="empty-state panel">Aún no hay partidos registrados. <a href="partidos.php">Asigna el primero →</a></div>
  <?php else: ?>

  <div class="report-cards">
    <div class="report-card"><span class="rc-value"><?= $n ?></span><span class="rc-label">Partidos</span></div>
    <div class="report-card"><span class="rc-value"><?= $victorias ?>-<?= $empates ?>-<?= $derrotas ?></span><span class="rc-label">V-E-D</span></div>
    <div class="report-card"><span class="rc-value"><?= $gf ?>:<?= $gc ?></span><span class="rc-label">Goles/puntos F:C</span></div>
    <div class="report-card"><span class="rc-value"><?= $agg['prom_goles'] !== null ? round($agg['prom_goles'],1) : '—' ?></span><span class="rc-label">Prom. gol/pto por jugador</span></div>
    <div class="report-card"><span class="rc-value"><?= $agg['prom_pases'] !== null ? round($agg['prom_pases'],1) : '—' ?></span><span class="rc-label">Prom. pases por jugador</span></div>
    <div class="report-card"><span class="rc-value"><?= $agg['prom_nota'] !== null ? round($agg['prom_nota'],1) : '—' ?></span><span class="rc-label">Nota promedio equipo</span></div>
  </div>

  <div class="panel">
    <h3>MEJORES NOTAS PROMEDIO (top jugadores)</h3>
    <table class="data">
      <tr><th>Jugador</th><th>Partidos</th><th>Nota promedio</th><th></th></tr>
      <?php foreach ($topJugadores as $t): ?>
      <tr>
        <td><?= htmlspecialchars($t['nombre']) ?></td>
        <td class="mono"><?= $t['partidos_jugados'] ?></td>
        <td class="mono"><?= round($t['nota_prom'],1) ?></td>
        <td><a href="reportes.php?jugador_id=<?= $t['id'] ?>">Ver detalle →</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="panel">
    <div class="flex-between">
      <h3 style="margin:0;">HISTORIAL DE PARTIDOS</h3>
      <button class="btn btn-sm btn-ghost" type="button" onclick="document.getElementById('detalle-equipo').classList.toggle('hidden')">Ver más / menos</button>
    </div>
    <table class="data hidden" id="detalle-equipo">
      <tr><th>Fecha</th><th>Rival</th><th>Sede</th><th>Resultado</th></tr>
      <?php foreach ($partidosEquipo as $p): ?>
      <tr>
        <td><?= $p['fecha'] ?></td>
        <td><?= htmlspecialchars($p['rival']) ?></td>
        <td><?= $p['sede'] ?></td>
        <td class="mono"><?= $p['resultado_favor'] !== null ? $p['resultado_favor'].'-'.$p['resultado_contra'] : 'Sin capturar' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
