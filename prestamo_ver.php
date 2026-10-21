<?php
require 'config.php'; requiere_login();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

// Marcar como liquidado (cuando el cliente ya pagó todo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'liquidar') {
    $st = $conexion->prepare("UPDATE prestamo SET estado = 'LIQUIDADO' WHERE id_prestamo = ?");
    $st->bind_param('i', $id); $st->execute();
    $_SESSION['msg'] = 'El préstamo quedó como liquidado.';
    header("Location: prestamo_ver.php?id=$id"); exit;
}

$st = $conexion->prepare("SELECT p.*, cl.nombre, cl.telefono, cl.correo, cl.domicilio,
    CASE WHEN p.estado = 'VIGENTE' AND p.fecha_vencimiento < CURDATE() THEN 'VENCIDO' ELSE UPPER(p.estado) END AS estado_real
    FROM prestamo p JOIN cliente cl ON p.id_cliente = cl.id_cliente WHERE p.id_prestamo = ?");
$st->bind_param('i', $id); $st->execute();
$p = $st->get_result()->fetch_assoc();
if (!$p) { header('Location: lista.php?t=prestamo'); exit; }

$st = $conexion->prepare("SELECT * FROM garantia WHERE id_prestamo = ?");
$st->bind_param('i', $id); $st->execute();
$g = $st->get_result()->fetch_assoc();

$pagos = tabla_pagos((float)$p['cantidad'], (int)$p['plazo_meses'], (float)$p['tasa_anual'], (float)$p['mensualidad'], $p['fecha_inicio']);
$dinero = fn($n) => '$' . number_format((float)$n, 2);
$etiquetaGarantia = ['BIEN' => 'Bien', 'VEHICULO' => 'Vehículo', 'CASA' => 'Casa o propiedad'];

cabecera('Préstamo #' . $id);
?>
<div class="barra">
  <h1>Préstamo #<?= $id ?> <span class="badge b-<?= strtolower(h($p['estado_real'])) ?>"><?= h($p['estado_real']) ?></span></h1>
  <div>
    <a class="btn" href="convenio.php?id=<?= $id ?>">📄 Descargar convenio (Word)</a>
    <a class="btn claro" href="lista.php?t=prestamo">« Volver</a>
  </div>
</div>
<?php mensajes(); ?>
<?php if ($p['estado_real'] === 'VENCIDO'): ?>
  <div class="alerta error">Este préstamo ya venció el <?= h($p['fecha_vencimiento']) ?>. Si no se paga, la garantía pasa a ser del banco según el convenio.</div>
<?php endif; ?>

<div class="menu">
  <div class="tarjeta">
    <h2>Cliente</h2>
    <p><strong><?= h($p['nombre']) ?></strong> (ID <?= (int)$p['id_cliente'] ?>)<br>
       Tel: <?= h($p['telefono']) ?><br><?= h($p['correo']) ?><br><?= h($p['domicilio']) ?></p>
  </div>
  <div class="tarjeta">
    <h2>Condiciones</h2>
    <table class="mini">
      <tr><td>Monto prestado</td><td><?= $dinero($p['cantidad']) ?></td></tr>
      <tr><td>Plazo</td><td><?= (int)$p['plazo_meses'] ?> meses</td></tr>
      <tr><td>Interés anual</td><td><?= h($p['tasa_anual']) ?>%</td></tr>
      <tr><td>Comisión (<?= h($p['comision_pct']) ?>%)</td><td><?= $dinero($p['comision']) ?></td></tr>
      <tr><td>Mensualidad</td><td><?= $dinero($p['mensualidad']) ?></td></tr>
      <tr><td>Total a pagar</td><td><?= $dinero($p['total_pagar']) ?></td></tr>
      <tr><td>Inicio</td><td><?= h($p['fecha_inicio']) ?></td></tr>
      <tr><td>Vencimiento</td><td><?= h($p['fecha_vencimiento']) ?></td></tr>
    </table>
  </div>
  <div class="tarjeta">
    <h2>Garantía</h2>
    <?php if ($g): ?>
      <p><strong><?= h($etiquetaGarantia[$g['tipo']] ?? $g['tipo']) ?></strong><br>
         <?= h($g['descripcion']) ?><br>
         Documento: <?= h($g['documento']) ?><br>
         Valor estimado: <?= $dinero($g['valor_estimado']) ?></p>
    <?php else: ?><p>Sin garantía registrada.</p><?php endif; ?>
  </div>
</div>

<h2>Calendario de pagos</h2>
<div class="scroll">
<table>
  <tr><th>#</th><th>Fecha de pago</th><th>Pago</th><th>Capital</th><th>Interés</th><th>Saldo restante</th></tr>
  <?php foreach ($pagos as $f): ?>
  <tr><td><?= $f['n'] ?></td><td><?= h($f['fecha']) ?></td><td><?= $dinero($f['pago']) ?></td><td><?= $dinero($f['capital']) ?></td><td><?= $dinero($f['interes']) ?></td><td><?= $dinero($f['saldo']) ?></td></tr>
  <?php endforeach; ?>
</table>
</div>

<?php if ($p['estado_real'] !== 'LIQUIDADO'): ?>
<form method="POST" style="margin-top:20px" onsubmit="return confirm('¿El cliente ya pagó todo? Esto marca el préstamo como liquidado.');">
  <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="accion" value="liquidar">
  <button class="btn claro" type="submit" style="width:auto">Marcar como liquidado</button>
</form>
<?php endif; ?>
<?php pie(); ?>
