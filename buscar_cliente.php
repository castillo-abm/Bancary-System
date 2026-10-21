<?php
// Buscador de clientes (lo usa el formulario de préstamo; responde en JSON)
require 'config.php';
if (empty($_SESSION['ok'])) { http_response_code(401); echo '[]'; exit; }
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if ($q === '') { echo '[]'; exit; }

if (ctype_digit($q)) {
    // 1) Por ID: usa la llave primaria (búsqueda directa, no recorre la tabla).
    //    También permite encontrar por teléfono.
    $st = $conexion->prepare("SELECT id_cliente, nombre, telefono, correo FROM cliente WHERE estatus = 'ACTIVO' AND (id_cliente = ? OR telefono LIKE ?) ORDER BY (id_cliente = ?) DESC LIMIT 10");
    $id = (int)$q; $tel = "$q%";
    $st->bind_param('isi', $id, $tel, $id);
} else {
    // 2) Por nombre o correo: el índice idx_cliente_nombre acelera las búsquedas que empiezan igual;
    //    los que empiezan con el texto salen primero.
    $st = $conexion->prepare("SELECT id_cliente, nombre, telefono, correo FROM cliente WHERE estatus = 'ACTIVO' AND (nombre LIKE ? OR correo LIKE ?) ORDER BY (nombre LIKE ?) DESC, nombre LIMIT 10");
    $cont = "%$q%"; $ini = "$q%";
    $st->bind_param('sss', $cont, $cont, $ini);
}
$st->execute();
echo json_encode($st->get_result()->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);
