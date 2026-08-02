<?php
$activo = $activo ?? '';
?>
<div class="topbar">
  <div class="brand">ATHLY<span>TICS</span></div>
  <nav class="nav-center">
    <a href="plantel.php" class="<?= $activo==='plantel'?'active':'' ?>">Plantel</a>
    <a href="formacion.php" class="<?= $activo==='formacion'?'active':'' ?>">Formaciones</a>
    <a href="partidos.php" class="<?= $activo==='partidos'?'active':'' ?>">Partidos</a>
    <a href="registrar_sesion.php" class="<?= $activo==='sesion'?'active':'' ?>">Entrenamiento</a>
    <a href="reportes.php" class="<?= $activo==='reportes'?'active':'' ?>">Reportes</a>
    <a href="equipo_riesgo.php" class="<?= $activo==='riesgo'?'active':'' ?>">Riesgo</a>
  </nav>
  <a href="logout.php" class="nav-exit">Salir</a>
</div>
