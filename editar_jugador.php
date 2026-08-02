<?php
require 'config.php';
require 'auth_check.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM jugadores WHERE id=? AND entrenador_id=?");
$stmt->execute([$id, $_SESSION['entrenador_id']]);
$jugador = $stmt->fetch();
if (!$jugador) { header('Location: plantel.php'); exit; }

$dep = deporte_info($DEPORTES, (int)$jugador['deporte_id']);

function guardarFotoEdicion(): ?string {
    if (empty($_FILES['foto']['name']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) return null;
    $permitidas = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $tipo = mime_content_type($_FILES['foto']['tmp_name']);
    if (!isset($permitidas[$tipo])) return null;
    if ($_FILES['foto']['size'] > 4 * 1024 * 1024) return null;
    if (!is_dir('uploads')) mkdir('uploads', 0755, true);
    $nombreArchivo = 'uploads/' . uniqid('jug_') . '.' . $permitidas[$tipo];
    if (move_uploaded_file($_FILES['foto']['tmp_name'], $nombreArchivo)) return $nombreArchivo;
    return null;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apodo = trim($_POST['apodo'] ?? '');
    $numero = $_POST['numero'] !== '' ? (int)$_POST['numero'] : null;
    $posicion = $_POST['posicion'] ?? '';
    $fecha = $_POST['fecha_nacimiento'] ?? '';
    $talla = $_POST['talla_cm'] !== '' ? (float)$_POST['talla_cm'] : null;
    $peso = $_POST['peso_kg'] !== '' ? (float)$_POST['peso_kg'] : null;
    $condiciones = trim($_POST['condiciones_medicas'] ?? '');
    $posicionesValidas = array_keys($dep['posiciones']);

    if ($nombre === '' || !in_array($posicion, $posicionesValidas) || $fecha === '') {
        $error = 'Completa nombre, posición y fecha de nacimiento correctamente.';
    } else {
        $nuevaFoto = guardarFotoEdicion();
        if ($nuevaFoto) {
            $upd = $pdo->prepare("UPDATE jugadores SET nombre=?, apodo=?, numero=?, posicion=?, fecha_nacimiento=?, talla_cm=?, peso_kg=?, condiciones_medicas=?, foto=? WHERE id=?");
            $upd->execute([$nombre, $apodo ?: null, $numero, $posicion, $fecha, $talla, $peso, $condiciones ?: null, $nuevaFoto, $id]);
        } else {
            $upd = $pdo->prepare("UPDATE jugadores SET nombre=?, apodo=?, numero=?, posicion=?, fecha_nacimiento=?, talla_cm=?, peso_kg=?, condiciones_medicas=? WHERE id=?");
            $upd->execute([$nombre, $apodo ?: null, $numero, $posicion, $fecha, $talla, $peso, $condiciones ?: null, $id]);
        }
        if (($talla !== null && (float)$talla !== (float)$jugador['talla_cm']) || ($peso !== null && (float)$peso !== (float)$jugador['peso_kg'])) {
            $m = $pdo->prepare("INSERT INTO medidas_fisicas (jugador_id, fecha, talla_cm, peso_kg) VALUES (?, CURDATE(), ?, ?)");
            $m->execute([$id, $talla, $peso]);
        }
        header('Location: ficha.php?id=' . $id . '&actualizado=1');
        exit;
    }
}

$activo = '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATHLYTICS — Editar jugador</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require 'nav.php'; ?>
<div class="wrap" style="max-width:560px;">
  <p><a href="ficha.php?id=<?= $id ?>">&larr; Volver a la ficha</a></p>
  <h1>Editar a <?= htmlspecialchars($jugador['nombre']) ?></h1>

  <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="post" class="panel" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="field">
      <label>Foto</label>
      <div class="foto-upload-row">
        <div class="foto-circle-preview <?= $jugador['foto'] ? 'has-photo' : '' ?>" id="fotoPreviewWrap">
          <img id="fotoPreviewImg" src="<?= htmlspecialchars($jugador['foto'] ?? '') ?>" class="<?= $jugador['foto'] ? '' : 'hidden' ?>" alt="">
          <span id="fotoPreviewPlaceholder" class="foto-circle-placeholder <?= $jugador['foto'] ? 'hidden' : '' ?>">Sin foto</span>
        </div>
        <label class="foto-upload-btn" for="fotoInput">
          Cambiar foto
          <input type="file" name="foto" id="fotoInput" accept="image/png, image/jpeg, image/webp" class="hidden">
        </label>
      </div>
    </div>
    <div class="flex-between" style="gap:16px;">
      <div class="field" style="flex:2;">
        <label>Nombre completo</label>
        <input type="text" name="nombre" required value="<?= htmlspecialchars($jugador['nombre']) ?>">
      </div>
      <div class="field" style="flex:1;">
        <label>Número</label>
        <input type="number" name="numero" min="0" max="99" value="<?= htmlspecialchars($jugador['numero'] ?? '') ?>">
      </div>
    </div>
    <div class="field">
      <label><?= htmlspecialchars($dep['apodo_label'] ?? 'Apodo') ?> — opcional</label>
      <input type="text" name="apodo" value="<?= htmlspecialchars($jugador['apodo'] ?? '') ?>" placeholder="<?= htmlspecialchars($dep['apodo_placeholder'] ?? 'Ej. Apodo del jugador') ?>">
    </div>
    <div class="field">
      <label>Posición (<?= $dep['nombre'] ?>)</label>
      <select name="posicion" required>
        <?php foreach ($dep['posiciones'] as $clave => $etiqueta): ?>
          <option value="<?= $clave ?>" <?= $clave===$jugador['posicion']?'selected':'' ?>><?= $etiqueta ?> (<?= $clave ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Fecha de nacimiento</label>
      <input type="date" name="fecha_nacimiento" required value="<?= $jugador['fecha_nacimiento'] ?>">
    </div>
    <div class="flex-between" style="gap:16px;">
      <div class="field" style="flex:1;">
        <label>Estatura (cm)</label>
        <input type="number" step="0.1" name="talla_cm" value="<?= $jugador['talla_cm'] ?>">
      </div>
      <div class="field" style="flex:1;">
        <label>Peso (kg)</label>
        <input type="number" step="0.1" name="peso_kg" value="<?= $jugador['peso_kg'] ?>">
      </div>
    </div>
    <div class="field">
      <label>Lesiones / condiciones médicas</label>
      <textarea name="condiciones_medicas" rows="3"
        style="width:100%; padding:11px 12px; background:var(--surface-2); border:1px solid var(--line); border-radius:6px; color:var(--chalk); font-family:inherit; font-size:14px;"><?= htmlspecialchars($jugador['condiciones_medicas'] ?? '') ?></textarea>
    </div>
    <button class="btn" type="submit">Guardar cambios</button>
  </form>
</div>
<script>
const fotoInput = document.getElementById('fotoInput');
const fotoPreviewImg = document.getElementById('fotoPreviewImg');
const fotoPreviewPlaceholder = document.getElementById('fotoPreviewPlaceholder');
const fotoPreviewWrap = document.getElementById('fotoPreviewWrap');
fotoInput.addEventListener('change', function () {
  const archivo = this.files[0];
  if (!archivo) return;
  const lector = new FileReader();
  lector.onload = function (e) {
    fotoPreviewImg.src = e.target.result;
    fotoPreviewImg.classList.remove('hidden');
    fotoPreviewImg.classList.remove('pop');
    void fotoPreviewImg.offsetWidth;
    fotoPreviewImg.classList.add('pop');
    fotoPreviewPlaceholder.classList.add('hidden');
    fotoPreviewWrap.classList.add('has-photo');
  };
  lector.readAsDataURL(archivo);
});
</script>
</body>
</html>
