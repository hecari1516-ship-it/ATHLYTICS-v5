<?php
require 'config.php';
require 'auth_check.php';

$busqueda = trim($_GET['q'] ?? '');
$deporteFiltro = (int)($_GET['deporte'] ?? 0);

$sql = "SELECT * FROM ficha_jugador WHERE entrenador_id = ?";
$params = [$_SESSION['entrenador_id']];
if ($busqueda !== '') { $sql .= " AND nombre LIKE ?"; $params[] = "%$busqueda%"; }
if ($deporteFiltro > 0) { $sql .= " AND deporte_id = ?"; $params[] = $deporteFiltro; }
$sql .= " ORDER BY ovr DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jugadores = $stmt->fetchAll();

function riesgoClase($nivel) {
    return match ($nivel) {
        'Optimo' => 'optimo', 'Precaucion' => 'precaucion',
        'Alto riesgo' => 'alto', 'Baja carga' => 'baja', default => 'sin',
    };
}
function iniciales($nombre) {
    $partes = explode(' ', trim($nombre));
    return strtoupper(substr($partes[0],0,1) . (isset($partes[1]) ? substr($partes[1],0,1) : ''));
}

$activo = 'plantel';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATHLYTICS — Plantel</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require 'nav.php'; ?>

<div class="wrap">
  <?php if (isset($_GET['bienvenida'])): ?>
    <div class="toast-ok">Cuenta creada. Agrega tu primer jugador para comenzar.</div>
  <?php endif; ?>

  <div class="page-head">
    <div>
      <div class="eyebrow"><?= htmlspecialchars($_SESSION['entrenador_nombre']) ?></div>
      <h1>Tu plantel</h1>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
    <form method="get" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
      <select name="deporte" class="select-filter" onchange="this.form.submit()">
        <option value="0">Todos los deportes</option>
        <?php foreach ($DEPORTES as $id => $d): ?>
          <option value="<?= $id ?>" <?= $deporteFiltro===$id?'selected':'' ?>><?= $d['nombre'] ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="q" placeholder="Buscar jugador..." value="<?= htmlspecialchars($busqueda) ?>"
             style="padding:10px 14px; background:var(--surface-2); border:1px solid var(--line); border-radius:6px; color:var(--chalk);">
      <button class="btn btn-sm" type="submit">Buscar</button>
    </form>
    <a href="agregar_jugador.php" class="btn btn-sm" style="border-radius:20px;">+ Jugador</a>
    </div>
  </div>

  <?php if (empty($jugadores)): ?>
    <div class="empty-state panel">
      Aún no tienes jugadores registrados. <a href="agregar_jugador.php">Agrega el primero →</a>
    </div>
  <?php else: ?>
    <div class="grid-players">
      <?php foreach ($jugadores as $i => $j): $r = riesgoClase($j['nivel_riesgo']); $dep = deporte_info($DEPORTES, $j['deporte_id']); $cat = categoria_edad((int)$j['edad']); ?>
        <a href="ficha.php?id=<?= $j['id'] ?>" style="text-decoration:none;">
        <div class="pcard" style="animation-delay:<?= min($i,10)*0.04 ?>s">
          <span class="sport-chip"><?= $dep['icono'] ?></span>

          <div class="pcard-avatar-wrap">
            <?php if (!empty($j['foto'])): ?>
              <img src="<?= htmlspecialchars($j['foto']) ?>" class="pcard-photo" alt="">
            <?php else: ?>
              <div class="pcard-initials"><?= iniciales($j['nombre']) ?></div>
            <?php endif; ?>
            <span class="ovr-pill"><?= $j['ovr'] ?></span>
          </div>

          <div class="pname"><?= htmlspecialchars($j['apodo'] ?: $j['nombre']) ?></div>
          <?php if (!empty($j['apodo'])): ?><div class="pmeta" style="font-style:italic;"><?= htmlspecialchars($j['nombre']) ?></div><?php endif; ?>
          <div class="pmeta"><?= $j['edad'] ?> años</div>

          <div class="pcard-tags">
            <span class="pos-tag"><?= htmlspecialchars($j['posicion']) ?></span>
            <?php if ($j['numero'] !== null): ?><span class="pos-tag">#<?= (int)$j['numero'] ?></span><?php endif; ?>
            <span class="pos-tag"><?= $cat ?></span>
          </div>

          <div class="risk-row risk-pill-<?= $r ?>">
            <span class="risk-dot risk-<?= $r ?>"></span> <?= htmlspecialchars($j['nivel_riesgo']) ?>
          </div>
        </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
