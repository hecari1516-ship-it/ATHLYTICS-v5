<?php
require 'config.php';
require 'auth_check.php';
$activo = 'riesgo';

$stmt = $pdo->prepare("
  SELECT * FROM ficha_jugador WHERE entrenador_id=?
  ORDER BY FIELD(nivel_riesgo, 'Alto riesgo','Precaucion','Baja carga','Optimo','Acumulando datos','Sin datos'), acwr DESC");
$stmt->execute([$_SESSION['entrenador_id']]);
$jugadores = $stmt->fetchAll();

function colorRiesgo($nivel) {
    return match ($nivel) {
        'Optimo' => ['bg'=>'rgba(183,240,60,0.12)','border'=>'var(--lime)','txt'=>'var(--lime)'],
        'Precaucion' => ['bg'=>'rgba(255,184,76,0.12)','border'=>'var(--amber)','txt'=>'var(--amber)'],
        'Alto riesgo' => ['bg'=>'rgba(255,93,93,0.12)','border'=>'var(--red)','txt'=>'var(--red)'],
        'Baja carga' => ['bg'=>'rgba(140,170,255,0.12)','border'=>'var(--blue)','txt'=>'var(--blue)'],
        'Acumulando datos' => ['bg'=>'rgba(140,170,255,0.08)','border'=>'var(--blue)','txt'=>'var(--blue)'],
        default => ['bg'=>'rgba(255,255,255,0.04)','border'=>'var(--line)','txt'=>'var(--chalk-dim)'],
    };
}
$leyenda = ['Optimo'=>'Optimo','Precaucion'=>'Precaucion','Alto riesgo'=>'Alto riesgo','Baja carga'=>'Baja carga','Acumulando datos'=>'Acumulando datos'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATHLYTICS — Mapa de riesgo</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require 'nav.php'; ?>
<div class="wrap">
  <div class="page-head">
    <div>
      <div class="eyebrow">Vista de equipo</div>
      <h1>Mapa de calor de riesgo</h1>
    </div>
    <a href="formacion.php" class="btn btn-sm btn-ghost">Ir a formaciones</a>
  </div>

  <div class="legend-row">
    <?php foreach ($leyenda as $nivel => $etiqueta): $c = colorRiesgo($nivel); ?>
      <span class="legend-chip"><span class="legend-dot" style="background:<?= $c['txt'] ?>"></span><?= $etiqueta ?></span>
    <?php endforeach; ?>
  </div>

  <?php if (empty($jugadores)): ?>
    <div class="empty-state panel">Aún no hay jugadores registrados.</div>
  <?php else: ?>
  <div class="heatgrid">
    <?php foreach ($jugadores as $i => $j): $c = colorRiesgo($j['nivel_riesgo']); ?>
      <a href="ficha.php?id=<?= $j['id'] ?>" style="text-decoration:none;">
        <div class="heatcell" style="background:<?= $c['bg'] ?>; border-color:<?= $c['border'] ?>; animation-delay:<?= min($i,12)*0.03 ?>s">
          <div class="pos-tag"><?= $j['posicion'] ?></div>
          <div class="hname" style="color:var(--chalk)"><?= htmlspecialchars($j['nombre']) ?></div>
          <div class="hacwr" style="color:<?= $c['txt'] ?>"><?= $j['acwr'] ?? '—' ?></div>
          <div class="muted" style="font-size:11px;"><?= htmlspecialchars($j['nivel_riesgo']) ?></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
