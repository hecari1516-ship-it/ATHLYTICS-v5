<?php
require 'config.php';
require 'auth_check.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM ficha_jugador WHERE id = ? AND entrenador_id = ?");
$stmt->execute([$id, $_SESSION['entrenador_id']]);
$j = $stmt->fetch();

if (!$j) { header('Location: plantel.php'); exit; }

$dep = deporte_info($DEPORTES, (int)$j['deporte_id']);
$ltad = ltad_stage((int)$j['edad']);
$categoria = categoria_edad((int)$j['edad']);

function riesgoClase($nivel) {
    return match ($nivel) {
        'Optimo' => 'optimo', 'Precaucion' => 'precaucion',
        'Alto riesgo' => 'alto', 'Baja carga' => 'baja', default => 'sin',
    };
}
$rClase = riesgoClase($j['nivel_riesgo']);

// Radar: promedio por habilidad (dinámico según deporte)
$hab = $pdo->prepare("SELECT habilidad, ROUND(AVG(calificacion),1) AS promedio FROM evaluaciones WHERE jugador_id=? GROUP BY habilidad");
$hab->execute([$id]);
$habilidades = [];
foreach ($dep['habilidades'] as $clave => $etiqueta) { $habilidades[$clave] = 5; }
foreach ($hab->fetchAll() as $row) { if (isset($habilidades[$row['habilidad']])) $habilidades[$row['habilidad']] = (float)$row['promedio']; }

// Forma reciente: últimos 5 RPE registrados
$forma = $pdo->prepare("
  SELECT a.rpe, s.fecha FROM asistencia a JOIN sesiones s ON s.id=a.sesion_id
  WHERE a.jugador_id=? AND a.asistio=1 AND a.rpe IS NOT NULL
  ORDER BY s.fecha DESC LIMIT 5");
$forma->execute([$id]);
$formaReciente = array_reverse($forma->fetchAll());

// Historial de evaluaciones (para tabla y para curva de evolución)
$hist = $pdo->prepare("SELECT * FROM evaluaciones WHERE jugador_id=? ORDER BY fecha ASC");
$hist->execute([$id]);
$historialTodo = $hist->fetchAll();
$historial = array_reverse(array_slice($historialTodo, -10));

// ------- CURVA DE MADURACIÓN: promedio de la categoría (mismo entrenador + deporte) -------
$compStmt = $pdo->prepare("SELECT edad, ovr FROM ficha_jugador WHERE entrenador_id=? AND deporte_id=? AND id != ?");
$compStmt->execute([$_SESSION['entrenador_id'], $j['deporte_id'], $id]);
$rango = categoria_rango($categoria);
$ovrCategoria = [];
foreach ($compStmt->fetchAll() as $row) {
    if ($row['edad'] >= $rango[0] && $row['edad'] <= $rango[1]) { $ovrCategoria[] = (float)$row['ovr']; }
}
$promedioCategoriaOvr = count($ovrCategoria) ? round(array_sum($ovrCategoria) / count($ovrCategoria), 1) : null;

// Promedio de habilidades de la categoría
$habCategoria = [];
if (count($ovrCategoria)) {
    $ids = $pdo->prepare("SELECT id, edad FROM ficha_jugador WHERE entrenador_id=? AND deporte_id=? AND id != ?");
    $ids->execute([$_SESSION['entrenador_id'], $j['deporte_id'], $id]);
    $idsCategoria = [];
    foreach ($ids->fetchAll() as $row) { if ($row['edad'] >= $rango[0] && $row['edad'] <= $rango[1]) $idsCategoria[] = $row['id']; }
    if ($idsCategoria) {
        $in = implode(',', array_fill(0, count($idsCategoria), '?'));
        $q = $pdo->prepare("SELECT habilidad, AVG(calificacion) AS promedio FROM evaluaciones WHERE jugador_id IN ($in) GROUP BY habilidad");
        $q->execute($idsCategoria);
        foreach ($q->fetchAll() as $row) { $habCategoria[$row['habilidad']] = round((float)$row['promedio'], 1); }
    }
}

// ------- Historial de medidas físicas (curva de crecimiento) -------
$med = $pdo->prepare("SELECT * FROM medidas_fisicas WHERE jugador_id=? ORDER BY fecha ASC");
$med->execute([$id]);
$medidas = $med->fetchAll();

$acwrPct = $j['acwr'] !== null ? min(100, max(0, ($j['acwr']/2)*100)) : 0;
$acwrColor = match($rClase){ 'optimo'=>'var(--lime)', 'precaucion'=>'var(--amber)', 'alto'=>'var(--red)', default=>'var(--chalk-dim)'};

$activo = '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATHLYTICS — <?= htmlspecialchars($j['nombre']) ?></title>
<link rel="stylesheet" href="css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
<?php require 'nav.php'; ?>

<div class="wrap">
  <p><a href="plantel.php">&larr; Volver al plantel</a></p>
  <?php if (isset($_GET['evaluado'])): ?><div class="toast-ok">Evaluación guardada</div><?php endif; ?>
  <?php if (isset($_GET['actualizado'])): ?><div class="toast-ok">Jugador actualizado</div><?php endif; ?>

  <div class="ficha-grid">
    <!-- Tarjeta estilo FIFA -->
    <div>
    <div class="fifa-card">
      <span class="sport-chip" style="position:absolute; top:16px; left:16px;"><?= $dep['icono'] ?></span>
      <a href="editar_jugador.php?id=<?= $id ?>" class="edit-fab" title="Editar jugador">✎</a>
      <?php if ($j['numero'] !== null): ?>
        <div class="dorsal-big">#<?= (int)$j['numero'] ?><small>NÚMERO</small></div>
      <?php endif; ?>
      <div class="ovr-big"><?= $j['ovr'] ?></div>
      <div class="pos-big"><?= htmlspecialchars($j['posicion']) ?></div>
      <?php if (!empty($j['foto'])): ?>
        <div class="avatar avatar-photo"><img src="<?= htmlspecialchars($j['foto']) ?>" alt=""></div>
      <?php else: ?>
      <div class="avatar"><?php
        $partes = explode(' ', trim($j['nombre']));
        echo strtoupper(substr($partes[0],0,1) . (isset($partes[1]) ? substr($partes[1],0,1) : ''));
      ?></div>
      <?php endif; ?>
      <div class="fname"><?= htmlspecialchars($j['apodo'] ?: $j['nombre']) ?></div>
      <?php if (!empty($j['apodo'])): ?><div class="fmeta" style="font-style:italic; margin-top:-2px;"><?= htmlspecialchars($j['nombre']) ?></div><?php endif; ?>
      <div class="fmeta"><?= $j['edad'] ?> años · <?= $categoria ?><?php if ($j['talla_cm']): ?> · <?= $j['talla_cm'] ?> cm<?php endif; ?><?php if ($j['peso_kg']): ?> · <?= $j['peso_kg'] ?> kg<?php endif; ?></div>

      <div class="form-strip">
        <?php foreach ($formaReciente as $f):
          $color = $f['rpe'] >= 8 ? 'var(--red)' : ($f['rpe'] >= 6 ? 'var(--amber)' : 'var(--lime)');
        ?>
          <div class="form-dot" style="background:<?= $color ?>" title="RPE <?= $f['rpe'] ?> — <?= $f['fecha'] ?>"></div>
        <?php endforeach; ?>
        <?php if (empty($formaReciente)): ?><span class="muted">Sin sesiones registradas aún</span><?php endif; ?>
      </div>

      <div class="risk-banner <?= $rClase ?>">
        <?= htmlspecialchars($j['nivel_riesgo']) ?>
        <?php if ($j['acwr'] !== null): ?> · ACWR <?= $j['acwr'] ?><?php endif; ?>
      </div>
    </div>

    <div class="panel ltad-panel">
      <h3>ETAPA DE FORMACIÓN (LTAD — Balyi)</h3>
      <div class="ltad-badge"><?= htmlspecialchars($ltad['etapa']) ?></div>
      <p class="muted" style="font-size:12px; margin:6px 0 0;">Rango típico: <?= $ltad['rango'] ?></p>
      <p style="font-size:13px; margin-top:10px;"><?= htmlspecialchars($ltad['enfoque']) ?></p>
    </div>

    <?php if ($j['condiciones_medicas']): ?>
    <div class="panel">
      <h3>CONDICIONES / LESIONES REGISTRADAS</h3>
      <p style="font-size:13px; white-space:pre-wrap;"><?= htmlspecialchars($j['condiciones_medicas']) ?></p>
    </div>
    <?php endif; ?>
    </div>

    <!-- Paneles de análisis -->
    <div>
      <div class="panel">
        <h3>RADAR DE ATRIBUTOS (<?= $dep['nombre'] ?>)</h3>
        <canvas id="radarChart" height="220"></canvas>
      </div>

      <div class="panel">
        <div class="flex-between">
          <h3 style="margin:0;">COMPARATIVA vs. PROMEDIO DE SU CATEGORÍA (<?= $categoria ?>)</h3>
        </div>
        <p class="muted" style="font-size:12px; margin:6px 0 14px;">
          Compara solo contra jugadores de edad similar — evita medir a un jugador de 13 años contra uno de 17 (Balyi, LTAD).
        </p>
        <?php if ($promedioCategoriaOvr === null): ?>
          <p class="muted">Aún no hay otros jugadores en esta categoría para comparar.</p>
        <?php else: ?>
          <div class="compare-row">
            <span class="compare-label">OVR</span>
            <div class="compare-track">
              <div class="compare-fill self" style="width:<?= min(100,$j['ovr']) ?>%"></div>
              <div class="compare-marker" style="left:<?= min(100,$promedioCategoriaOvr) ?>%" title="Promedio categoría: <?= $promedioCategoriaOvr ?>"></div>
            </div>
            <span class="compare-value mono"><?= $j['ovr'] ?> <span class="muted">/ prom. <?= $promedioCategoriaOvr ?></span></span>
          </div>
          <?php foreach ($dep['habilidades'] as $clave => $etiqueta):
            $valorJ = $habilidades[$clave] ?? 5;
            $valorCat = $habCategoria[$clave] ?? null;
          ?>
          <div class="compare-row">
            <span class="compare-label"><?= $etiqueta ?></span>
            <div class="compare-track">
              <div class="compare-fill" style="width:<?= min(100,$valorJ*10) ?>%"></div>
              <?php if ($valorCat !== null): ?>
                <div class="compare-marker" style="left:<?= min(100,$valorCat*10) ?>%" title="Promedio categoría: <?= $valorCat ?>"></div>
              <?php endif; ?>
            </div>
            <span class="compare-value mono"><?= $valorJ ?><?= $valorCat!==null ? ' <span class="muted">/ prom. '.$valorCat.'</span>' : '' ?></span>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h3>CARGA DE ENTRENAMIENTO (ACWR)</h3>
        <div class="flex-between" style="margin-bottom:10px;">
          <span class="muted">Aguda (7d): <b class="mono"><?= $j['carga_aguda'] !== null ? round($j['carga_aguda'],1) : '—' ?></b></span>
          <span class="muted">Crónica (28d): <b class="mono"><?= $j['carga_cronica'] !== null ? round($j['carga_cronica'],1) : '—' ?></b></span>
          <span class="muted">ACWR: <b class="mono"><?= $j['acwr'] ?? '—' ?></b></span>
        </div>
        <div class="acwr-bar-track">
          <div class="acwr-bar-fill" style="width:<?= $acwrPct ?>%; background:<?= $acwrColor ?>;"></div>
        </div>
        <p class="muted" style="margin-top:10px; font-size:12px;">
          ACWR ideal entre 0.8 y 1.3. Por encima de 1.5 se considera zona de alto riesgo de lesión (Gabbett, 2016).
        </p>
      </div>

      <?php if (count($medidas) >= 1): ?>
      <div class="panel">
        <h3>RATIO DE CRECIMIENTO (TALLA / PESO)</h3>
        <canvas id="growthChart" height="180"></canvas>
        <p class="muted" style="margin-top:10px; font-size:12px;">
          Seguimiento periódico de talla y peso — útil para detectar maduración temprana o tardía frente a la curva de la categoría.
        </p>
      </div>
      <?php endif; ?>

      <div class="panel">
        <div class="flex-between">
          <h3 style="margin:0;">HISTORIAL DE EVALUACIONES</h3>
          <a class="btn btn-sm" href="evaluar_jugador.php?id=<?= $id ?>">+ Evaluar</a>
        </div>
        <?php if (empty($historial)): ?>
          <p class="muted">Aún no hay evaluaciones técnicas registradas.</p>
        <?php else: ?>
        <table class="data">
          <tr><th>Fecha</th><th>Habilidad</th><th>Calificación</th></tr>
          <?php foreach ($historial as $h): ?>
          <tr>
            <td><?= $h['fecha'] ?></td>
            <td><?= htmlspecialchars($dep['habilidades'][$h['habilidad']] ?? $h['habilidad']) ?></td>
            <td class="mono"><?= $h['calificacion'] ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php endif; ?>
        <p style="margin-top:12px;"><a href="reportes.php?jugador_id=<?= $id ?>">Ver reporte completo de rendimiento →</a></p>
      </div>
    </div>
  </div>
</div>

<script>
const ctx = document.getElementById('radarChart');
new Chart(ctx, {
  type: 'radar',
  data: {
    labels: <?= json_encode(array_values($dep['habilidades']), JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
      label: '<?= htmlspecialchars($j['nombre']) ?>',
      data: <?= json_encode(array_values($habilidades)) ?>,
      backgroundColor: 'rgba(183,240,60,0.20)',
      borderColor: '#B7F03C',
      pointBackgroundColor: '#B7F03C',
      borderWidth: 2
    }]
  },
  options: {
    scales: {
      r: {
        min: 0, max: 10,
        angleLines: { color: 'rgba(255,255,255,0.08)' },
        grid: { color: 'rgba(255,255,255,0.08)' },
        pointLabels: { color: '#F2F1E8', font: { size: 12 } },
        ticks: { display:false, backdropColor: 'transparent' }
      }
    },
    plugins: { legend: { display: false } }
  }
});

<?php if (count($medidas) >= 1): ?>
const gctx = document.getElementById('growthChart');
new Chart(gctx, {
  type: 'line',
  data: {
    labels: <?= json_encode(array_map(fn($m) => $m['fecha'], $medidas)) ?>,
    datasets: [
      { label: 'Talla (cm)', data: <?= json_encode(array_map(fn($m) => $m['talla_cm'], $medidas)) ?>, borderColor: '#B7F03C', backgroundColor:'rgba(183,240,60,0.1)', yAxisID:'y', tension:0.3 },
      { label: 'Peso (kg)', data: <?= json_encode(array_map(fn($m) => $m['peso_kg'], $medidas)) ?>, borderColor: '#FFB84C', backgroundColor:'rgba(255,184,76,0.1)', yAxisID:'y1', tension:0.3 }
    ]
  },
  options: {
    scales: {
      y:  { position:'left', ticks:{ color:'#8FA79B' }, grid:{ color:'rgba(255,255,255,0.06)' } },
      y1: { position:'right', ticks:{ color:'#8FA79B' }, grid:{ display:false } },
      x:  { ticks:{ color:'#8FA79B' }, grid:{ display:false } }
    },
    plugins: { legend: { labels:{ color:'#F2F1E8' } } }
  }
});
<?php endif; ?>
</script>
</body>
</html>
