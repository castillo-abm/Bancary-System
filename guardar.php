<?php
require 'config.php'; requiere_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
$t = $_POST['t'] ?? ''; $e = entidad($t); $id = (int)($_POST['id'] ?? 0);

// ---- Validaciones ----
$vals = []; $err = [];
foreach ($e['campos'] as $n => $c) {
    $v = trim($_POST[$n] ?? ''); $vals[$n] = $v; $l = $c['label'];
    if ($v === '') { if (!empty($c['req'])) $err[] = "$l es obligatorio."; continue; }
    if (isset($c['max']) && strlen($v) > $c['max']) $err[] = "$l es demasiado largo (máximo {$c['max']} caracteres).";
    if ($c['tipo'] === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $err[] = "El correo no es válido.";
    if (isset($c['regex']) && !preg_match($c['regex'], $v)) $err[] = $c['errregex'];
    if ($c['tipo'] === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) $err[] = "$l no es una fecha válida.";
    if (in_array($c['tipo'], ['dec', 'int'])) {
        if (!is_numeric($v)) $err[] = "$l debe ser un número.";
        elseif (isset($c['min']) && (float)$v < $c['min']) $err[] = "$l debe ser mayor o igual a {$c['min']}.";
    }
    if ($c['tipo'] === 'select') {
        $opts = isset($c['fk']) ? opciones_fk($c['fk']) : $c['opts'];
        if (!isset($opts[$v])) $err[] = "Selecciona una opción válida en $l.";
    }
}
if ($t === 'prestamo' && !$err && $vals['fecha_vencimiento'] <= $vals['fecha_inicio'])
    $err[] = "La fecha de vencimiento debe ser posterior a la fecha de inicio.";

if ($err) {
    $_SESSION['errores'] = $err; $_SESSION['viejo'] = $vals;
    header("Location: form.php?t=$t" . ($id ? "&id=$id" : '')); exit;
}

// ---- Guardar ----
$cols = []; $params = []; $types = '';
foreach ($e['campos'] as $n => $c) {
    $cols[] = $n;
    $params[] = ($vals[$n] === '') ? null : $vals[$n];
    $types .= isset($c['fk']) ? 'i' : (['dec' => 'd', 'int' => 'i'][$c['tipo']] ?? 's');
}
try {
    if ($id) {
        $sql = "UPDATE $t SET " . implode(' = ?, ', $cols) . " = ? WHERE {$e['pk']} = ?";
        $params[] = $id; $types .= 'i';
    } else {
        if ($t === 'cuenta') {   // el número de cuenta se genera solo
            $cols[] = 'num_cuenta';
            $params[] = (int)$conexion->query("SELECT COALESCE(MAX(num_cuenta), 100000) + 1 FROM cuenta")->fetch_row()[0];
            $cols[] = 'fecha_creacion_cuenta'; $params[] = date('Y-m-d'); $types .= 'is';
        }
        $sql = "INSERT INTO $t (" . implode(', ', $cols) . ") VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ")";
    }
    $st = $conexion->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $_SESSION['msg'] = ucfirst($e['singular']) . ($id ? ' actualizado' : ' registrado') . ' correctamente.';
    // Cliente creado desde el formulario de préstamo: regresar al préstamo con el cliente ya elegido
    if ($t === 'cliente' && !$id && ($_POST['volver'] ?? '') === 'prestamo') {
        header('Location: prestamo_form.php?id_cliente=' . $conexion->insert_id); exit;
    }
} catch (Exception $ex) {
    $_SESSION['error'] = 'No se pudo guardar: ' . $ex->getMessage();
}
header("Location: lista.php?t=$t");
