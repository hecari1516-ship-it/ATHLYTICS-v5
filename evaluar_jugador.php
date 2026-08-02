<?php
require 'config.php';
require 'auth_check.php';

$id = (int)($_GET['id'] ?? $_POST['jugador_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM jugadores WHERE id=? AND entrenador_id=?");
$stmt->execute([$id, $_SESSION['entrenador_id']]);
$jugador = $stmt->fetch();
if (!$jugador) { header('Location: plantel.php'); exit; }

$dep = deporte_info($DEPORTES, (int)$jugador['deporte_id']);
$cuestionario = $CUESTIONARIOS[(int)$jugador['deporte_id']] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ins = $pdo->prepare("INSERT INTO evaluaciones (jugador_id, fecha, habilidad, calificacion) VALUES (?, CURDATE(), ?, ?)");
    foreach ($cuestionario as $habilidad => $preguntas) {
        $suma = 0; $total = 0;
        foreach ($preguntas as $qi => $texto) {
            $val = (int)($_POST["resp_{$habilidad}_{$qi}"] ?? 0);
            if ($val >= 1 && $val <= 5) { $suma += $val; $total++; }
        }
        if ($total > 0) {
            // Escala 1-5 -> 2-10 (mismo rango que la calificación técnica del sistema)
            $calificacion = round(($suma / $total) * 2, 1);
            $ins->execute([$id, $habilidad, $calificacion]);
        }
    }
    header('Location: ficha.php?id=' . $id . '&evaluado=1');
    exit;
}
$activo = '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATHLYTICS — Evaluación</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require 'nav.php'; ?>
<div class="wrap" style="max-width:640px;">
  <p><a href="ficha.php?id=<?= $id ?>">&larr; Volver a la ficha</a></p>
  <div class="page-head">
    <div>
      <div class="eyebrow"><span class="sport-chip-mini"><?= $dep['icono'] ?></span> <?= $dep['nombre'] ?></div>
      <h1>Evaluar a <?= htmlspecialchars($jugador['nombre']) ?></h1>
    </div>
  </div>

  <form method="post" id="formEval">
    <?php foreach ($cuestionario as $habilidad => $preguntas): ?>
    <div class="panel quiz-block">
      <h3><?= htmlspecialchars($dep['habilidades'][$habilidad] ?? $habilidad) ?></h3>
      <?php foreach ($preguntas as $qi => $texto): ?>
        <div class="quiz-row">
          <span class="quiz-q"><?= htmlspecialchars($texto) ?></span>
          <div class="quiz-scale-wrap">
            <div class="quiz-scale" data-hab="<?= $habilidad ?>" data-q="<?= $qi ?>">
              <?php for ($n=1;$n<=5;$n++): ?>
                <button type="button" data-val="<?= $n ?>" onclick="marcar(this)"><?= $n ?></button>
              <?php endfor; ?>
            </div>
            <div class="quiz-scale-labels"><span>Muy bajo</span><span>Muy alto</span></div>
          </div>
          <input type="hidden" name="resp_<?= $habilidad ?>_<?= $qi ?>" id="resp_<?= $habilidad ?>_<?= $qi ?>" value="3">
        </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <button class="btn" type="submit">Calcular y guardar evaluación</button>
  </form>
</div>
<script>
function marcar(btn) {
  const scale = btn.parentElement;
  const hab = scale.dataset.hab, q = scale.dataset.q;
  document.getElementById('resp_' + hab + '_' + q).value = btn.dataset.val;
  [...scale.children].forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}
document.querySelectorAll('.quiz-scale').forEach(scale => {
  const btn = scale.querySelector('[data-val="3"]');
  if (btn) btn.classList.add('active');
});
</script>
</body>
</html>
