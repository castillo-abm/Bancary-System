<?php
require 'config.php'; requiere_login();
cabecera('Inicio');
?>
<h1>Menú principal</h1>
<div class="menu">
<?php foreach ($E as $k => $e):
    $total = $conexion->query("SELECT COUNT(*) FROM $k")->fetch_row()[0]; ?>
  <div class="tarjeta">
    <h2><?= h($e['titulo']) ?></h2>
    <p class="num"><?= $total ?></p>
    <a class="btn" href="lista.php?t=<?= $k ?>">Ver listado</a>
    <a class="btn claro" href="<?= h($e['url_nuevo'] ?? "form.php?t=$k") ?>">+ Registrar</a>
  </div>
<?php endforeach; ?>
</div>
<?php pie(); ?>
