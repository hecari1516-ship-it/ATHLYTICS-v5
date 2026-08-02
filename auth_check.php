<?php
if (!isset($_SESSION['entrenador_id'])) {
    header('Location: index.php');
    exit;
}
