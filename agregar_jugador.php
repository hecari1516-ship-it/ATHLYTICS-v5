<?php
require 'config.php';
require 'auth_check.php';

function guardarFoto(): ?string {
    if (empty($_FILES['foto']['name']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) return null;
    $permitidas = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $tipo = mime_content_type($_FILES['foto']['tmp_name']);
    if (!isset($permitidas[$tipo])) return null;
    if ($_FILES['foto']['size'] > 4 * 1024 * 1024) return null; // máx 4MB
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
    $deporteId = (int)($_POST['deporte_id'] ?? 0);
    $posicion = $_POST['posicion'] ?? '';
    $fecha = $_POST['fecha_nacimiento'] ?? '';
    $talla = $_POST['talla_cm'] !== '' ? (float)$_POST['talla_cm'] : null;
    $peso = $_POST['peso_kg'] !== '' ? (float)$_POST['peso_kg'] : null;
    $condiciones = trim($_POST['condiciones_medicas'] ?? '');

    $posicionesValidas = array_keys(deporte_info($DEPORTES, $deporteId)['posiciones'] ?? []);

    if ($nombre === '' || !isset($DEPORTES[$deporteId]) || !in_array($posicion, $posicionesValidas) || $fecha === '') {
        $error = 'Completa nombre, deporte, posición y fecha de nacimiento correctamente.';
    } else {
        $foto = guardarFoto();
        $stmt = $pdo->prepare("INSERT INTO jugadores (nombre, apodo, numero, deporte_id, posicion, fecha_nacimiento, talla_cm, peso_kg, condiciones_medicas, foto, entrenador_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$nombre, $apodo ?: null, $numero, $deporteId, $posicion, $fecha, $talla, $peso, $condiciones ?: null, $foto, $_SESSION['entrenador_id']]);
        $jugadorId = $pdo->lastInsertId();
        if ($talla !== null || $peso !== null) {
            $m = $pdo->prepare("INSERT INTO medidas_fisicas (jugador_id, fecha, talla_cm, peso_kg) VALUES (?, CURDATE(), ?, ?)");
            $m->execute([$jugadorId, $talla, $peso]);
        }
        header('Location: plantel.php');
        exit;
    }
}

$activo = 'jugador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATHLYTICS — Nuevo jugador</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require 'nav.php'; ?>
<div class="wrap" style="max-width:560px;">
  <h1>Registrar jugador</h1>

  <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="post" class="panel" enctype="multipart/form-data">
    <div class="field">
      <label>Deporte</label>
      <select name="deporte_id" id="deporte_id" required onchange="actualizarPosiciones()">
        <option value="">Selecciona...</option>
        <?php foreach ($DEPORTES as $id => $d): ?>
          <option value="<?= $id ?>"><?= $d['nombre'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex-between" style="gap:16px;">
      <div class="field" style="flex:2;">
        <label>Nombre completo</label>
        <input type="text" name="nombre" required placeholder="Ej. Kevin Torres">
      </div>
      <div class="field" style="flex:1;">
        <label>Número</label>
        <input type="number" name="numero" min="0" max="99" placeholder="9">
      </div>
    </div>
    <div class="field">
      <label id="apodoLabel">Apodo / nombre para mostrar — opcional</label>
      <input type="text" name="apodo" id="apodoInput" placeholder="Primero elige un deporte...">
    </div>
    <div class="field">
      <label>Posición</label>
      <select name="posicion" id="posicion" required>
        <option value="">Primero elige un deporte...</option>
      </select>
    </div>
    <div class="field">
      <label>Fecha de nacimiento</label>
      <input type="date" name="fecha_nacimiento" required>
    </div>
    <div class="flex-between" style="gap:16px;">
      <div class="field" style="flex:1;">
        <label>Estatura (cm)</label>
        <input type="number" step="0.1" name="talla_cm" placeholder="168.0">
      </div>
      <div class="field" style="flex:1;">
        <label>Peso (kg)</label>
        <input type="number" step="0.1" name="peso_kg" placeholder="58.0">
      </div>
    </div>
    <div class="field">
      <label>Foto</label>
      <div class="foto-upload-row">
        <div class="foto-circle-preview" id="fotoPreviewWrap">
          <img id="fotoPreviewImg" class="hidden" alt="">
          <span id="fotoPreviewPlaceholder" class="foto-circle-placeholder">Sin foto</span>
        </div>
        <label class="foto-upload-btn" for="fotoInput">
          Elegir foto
          <input type="file" name="foto" id="fotoInput" accept="image/png, image/jpeg, image/webp" class="hidden">
        </label>
      </div>
    </div>
    <div class="field">
      <label>Lesiones / condiciones médicas — opcional</label>
      <textarea name="condiciones_medicas" rows="3" placeholder="Ej. Esguince de tobillo (recuperado), asma controlada..."
        style="width:100%; padding:11px 12px; background:var(--surface-2); border:1px solid var(--line); border-radius:6px; color:var(--chalk); font-family:inherit; font-size:14px;"></textarea>
    </div>
    <button class="btn" type="submit">Guardar jugador</button>
  </form>
</div>

<script>
const POSICIONES = <?= json_encode(array_map(fn($d) => $d['posiciones'], $DEPORTES), JSON_UNESCAPED_UNICODE) ?>;
const APODOS = <?= json_encode(array_map(fn($d) => ['label' => $d['apodo_label'] ?? 'Apodo', 'placeholder' => $d['apodo_placeholder'] ?? 'Ej. Apodo del jugador'], $DEPORTES), JSON_UNESCAPED_UNICODE) ?>;

function actualizarPosiciones() {
  const deporteId = document.getElementById('deporte_id').value;
  const sel = document.getElementById('posicion');
  sel.innerHTML = '';
  if (!deporteId || !POSICIONES[deporteId]) {
    sel.innerHTML = '<option value="">Primero elige un deporte...</option>';
  } else {
    const opciones = POSICIONES[deporteId];
    sel.innerHTML = '<option value="">Selecciona...</option>';
    for (const clave in opciones) {
      const opt = document.createElement('option');
      opt.value = clave;
      opt.textContent = opciones[clave] + ' (' + clave + ')';
      sel.appendChild(opt);
    }
  }

  // Personalización del campo de apodo según el deporte elegido
  const apodoLabel = document.getElementById('apodoLabel');
  const apodoInput = document.getElementById('apodoInput');
  if (deporteId && APODOS[deporteId]) {
    apodoLabel.textContent = APODOS[deporteId].label + ' — opcional';
    apodoInput.placeholder = APODOS[deporteId].placeholder;
  } else {
    apodoLabel.textContent = 'Apodo / nombre para mostrar — opcional';
    apodoInput.placeholder = 'Primero elige un deporte...';
  }
}

// Vista previa circular animada de la foto — no tapa ni estorba el resto del formulario
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
    void fotoPreviewImg.offsetWidth; // reinicia la animación si se cambia la foto
    fotoPreviewImg.classList.add('pop');
    fotoPreviewPlaceholder.classList.add('hidden');
    fotoPreviewWrap.classList.add('has-photo');
  };
  lector.readAsDataURL(archivo);
});
</script>
</body>
</html>
