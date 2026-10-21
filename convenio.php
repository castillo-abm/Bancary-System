<?php
// Genera el convenio de préstamo como documento de Word listo para imprimir y firmar.
// Word abre sin problema un archivo HTML con extensión .doc.
require 'config.php'; requiere_login();
$id = (int)($_GET['id'] ?? 0);

$st = $conexion->prepare("SELECT p.*, cl.nombre, cl.telefono, cl.correo, cl.domicilio FROM prestamo p JOIN cliente cl ON p.id_cliente = cl.id_cliente WHERE p.id_prestamo = ?");
$st->bind_param('i', $id); $st->execute();
$p = $st->get_result()->fetch_assoc();
if (!$p) { header('Location: lista.php?t=prestamo'); exit; }
$st = $conexion->prepare("SELECT * FROM garantia WHERE id_prestamo = ?");
$st->bind_param('i', $id); $st->execute();
$g = $st->get_result()->fetch_assoc();

$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
function fecha_larga($f) { global $meses; $d = new DateTime($f); return $d->format('j') . ' de ' . $meses[(int)$d->format('n') - 1] . ' de ' . $d->format('Y'); }
$dinero = fn($n) => '$' . number_format((float)$n, 2) . ' MXN';
$tipos = ['BIEN' => 'el bien', 'VEHICULO' => 'el vehículo', 'CASA' => 'la propiedad'];
$pagos = tabla_pagos((float)$p['cantidad'], (int)$p['plazo_meses'], (float)$p['tasa_anual'], (float)$p['mensualidad'], $p['fecha_inicio']);
$banco = NOMBRE_BANCO;
$nombreArchivo = "Convenio_Prestamo_{$id}.doc";

header('Content-Type: application/msword; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<title>Convenio de préstamo <?= $id ?></title>
<style>
  @page { size: 21.59cm 27.94cm; margin: 2.2cm; }
  body { font-family: Arial, sans-serif; font-size: 11pt; line-height: 1.4; }
  h1 { text-align: center; font-size: 16pt; margin-bottom: 4pt; }
  p.centro { text-align: center; margin-top: 0; }
  h3 { font-size: 11pt; margin-bottom: 2pt; }
  table.datos { border-collapse: collapse; width: 100%; }
  table.datos td, table.datos th { border: 1px solid #555; padding: 4pt 6pt; font-size: 10pt; }
  table.datos th { background: #e6e6e6; }
  table.firmas { width: 100%; margin-top: 50pt; }
  table.firmas td { width: 50%; text-align: center; padding-top: 4pt; vertical-align: top; }
  .linea { border-top: 1px solid #000; margin: 0 20pt; padding-top: 3pt; }
  .pie { font-size: 8pt; color: #666; text-align: center; margin-top: 30pt; }
</style>
</head>
<body>
<h1>CONVENIO DE PRÉSTAMO</h1>
<p class="centro">Folio <?= str_pad($id, 5, '0', STR_PAD_LEFT) ?> · <?= h(fecha_larga($p['fecha_inicio'])) ?></p>

<p>Entre <strong><?= h($banco) ?></strong> (en adelante “EL BANCO”) y <strong><?= h($p['nombre']) ?></strong>, con domicilio en <?= h($p['domicilio']) ?>, teléfono <?= h($p['telefono']) ?> y correo <?= h($p['correo']) ?> (en adelante “EL CLIENTE”), se acuerda lo siguiente:</p>

<h3>PRIMERA. El préstamo.</h3>
<p>EL BANCO presta a EL CLIENTE la cantidad de <strong><?= $dinero($p['cantidad']) ?></strong> el día <?= h(fecha_larga($p['fecha_inicio'])) ?>.</p>

<h3>SEGUNDA. Comisión, intereses y mensualidades.</h3>
<p>Por la apertura del préstamo EL CLIENTE paga una comisión de <?= h($p['comision_pct']) ?>% (<?= $dinero($p['comision']) ?>). El préstamo causa un interés de <?= h($p['tasa_anual']) ?>% anual y se paga en <strong><?= (int)$p['plazo_meses'] ?> mensualidades iguales de <?= $dinero($p['mensualidad']) ?></strong>, para un total a pagar de <?= $dinero($p['total_pagar']) ?>.</p>

<h3>TERCERA. Fecha límite de pago.</h3>
<p>EL CLIENTE se compromete a pagar cada mensualidad en las fechas del calendario de pagos que aparece abajo, y a terminar de pagar a más tardar el <strong><?= h(fecha_larga($p['fecha_vencimiento'])) ?></strong>.</p>

<h3>CUARTA. Garantía.</h3>
<p>Como garantía de pago, EL CLIENTE deja <?= $tipos[$g['tipo'] ?? 'BIEN'] ?? 'el bien' ?> siguiente: <strong><?= h($g['descripcion'] ?? '') ?></strong>, con documento <strong><?= h($g['documento'] ?? '') ?></strong>, con valor estimado de <?= $dinero($g['valor_estimado'] ?? 0) ?>. EL CLIENTE declara que es el legítimo dueño de la garantía.</p>

<h3>QUINTA. Si no se paga.</h3>
<p>Si EL CLIENTE no paga en la fecha límite, la garantía descrita en la cláusula anterior pasa a ser propiedad de EL BANCO.</p>

<h3>SEXTA. Aceptación.</h3>
<p>Ambas partes leyeron este convenio, están de acuerdo con todo lo que dice y lo firman de conformidad.</p>

<h3>Calendario de pagos</h3>
<table class="datos">
  <tr><th>#</th><th>Fecha de pago</th><th>Pago</th><th>Capital</th><th>Interés</th><th>Saldo</th></tr>
  <?php foreach ($pagos as $f): ?>
  <tr><td><?= $f['n'] ?></td><td><?= h(fecha_larga($f['fecha'])) ?></td><td><?= $dinero($f['pago']) ?></td><td><?= $dinero($f['capital']) ?></td><td><?= $dinero($f['interes']) ?></td><td><?= $dinero($f['saldo']) ?></td></tr>
  <?php endforeach; ?>
</table>

<table class="firmas">
  <tr>
    <td><div class="linea"><strong>EL CLIENTE</strong><br><?= h($p['nombre']) ?></div></td>
    <td><div class="linea"><strong>POR EL BANCO</strong><br><?= h($banco) ?></div></td>
  </tr>
</table>

<p class="pie">Documento generado por el sistema el <?= date('d/m/Y') ?> · Proyecto escolar con datos ficticios.</p>
</body>
</html>
