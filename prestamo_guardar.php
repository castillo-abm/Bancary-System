<?php
require 'config.php'; requiere_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$idCliente = (int)($_POST['id_cliente'] ?? 0);
$monto     = trim($_POST['cantidad'] ?? '');
$plazo     = (int)($_POST['plazo_meses'] ?? 0);
$fecha     = trim($_POST['fecha_inicio'] ?? '');
$gDesc     = trim($_POST['g_descripcion'] ?? '');
$gDoc      = trim($_POST['g_documento'] ?? '');
$gValor    = trim($_POST['g_valor'] ?? '');

// ---- Validaciones ----
$err = [];
$st = $conexion->prepare("SELECT COUNT(*) FROM cliente WHERE id_cliente = ? AND estatus = 'ACTIVO'");
$st->bind_param('i', $idCliente); $st->execute();
if (!$st->get_result()->fetch_row()[0]) $err[] = 'Elige un cliente activo.';

if (!is_numeric($monto) || (float)$monto < 1000) $err[] = 'El monto mínimo del préstamo es $1,000.';
elseif ((float)$monto > 2000000) $err[] = 'El monto máximo del préstamo es $2,000,000.';
if (!isset($PLAZOS[$plazo])) $err[] = 'Elige un plazo válido.';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)) $err[] = 'La fecha del préstamo no es válida.';
if ($gDesc === '') $err[] = 'La descripción de la garantía es obligatoria.';
if ($gDoc === '')  $err[] = 'El número de documento de la garantía es obligatorio (escritura, factura o comprobante).';
if (!is_numeric($gValor) || (float)$gValor <= 0) $err[] = 'El valor de la garantía debe ser un número mayor a cero.';
elseif (is_numeric($monto) && (float)$gValor < (float)$monto) $err[] = 'El valor de la garantía debe ser igual o mayor al monto del préstamo.';

if ($err) {
    $_SESSION['errores'] = $err;
    $_SESSION['viejo_prestamo'] = ['id_cliente' => $idCliente, 'cantidad' => $monto, 'plazo_meses' => $plazo, 'fecha_inicio' => $fecha,
                                   'g_descripcion' => $gDesc, 'g_documento' => $gDoc, 'g_valor' => $gValor];
    header('Location: prestamo_form.php'); exit;
}

// ---- Cálculos hechos en el servidor (no se confía en lo que muestra el navegador) ----
$monto = (float)$monto;
$c = calcular_prestamo($monto, $plazo);
$vence = (new DateTime($fecha))->modify("+$plazo month")->format('Y-m-d');
$g = garantia_requerida($monto);

// ---- Guardar préstamo y garantía juntos (si algo falla, no se guarda nada) ----
try {
    $conexion->begin_transaction();
    $st = $conexion->prepare("INSERT INTO prestamo (id_cliente, fecha_inicio, fecha_vencimiento, cantidad, plazo_meses, tasa_anual, comision_pct, comision, mensualidad, total_pagar, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'VIGENTE')");
    $st->bind_param('issdiddddd', $idCliente, $fecha, $vence, $monto, $plazo, $c['tasa'], $c['comision_pct'], $c['comision'], $c['mensualidad'], $c['total']);
    $st->execute();
    $idPrestamo = $conexion->insert_id;

    $gValor = (float)$gValor;
    $st = $conexion->prepare("INSERT INTO garantia (id_prestamo, tipo, descripcion, documento, valor_estimado) VALUES (?, ?, ?, ?, ?)");
    $st->bind_param('isssd', $idPrestamo, $g['tipo'], $gDesc, $gDoc, $gValor);
    $st->execute();
    $conexion->commit();

    $_SESSION['msg'] = 'Préstamo registrado. Ya puedes imprimir el convenio para firmarlo.';
    header("Location: prestamo_ver.php?id=$idPrestamo");
} catch (Exception $ex) {
    $conexion->rollback();
    $_SESSION['error'] = 'No se pudo guardar el préstamo: ' . $ex->getMessage();
    header('Location: lista.php?t=prestamo');
}
