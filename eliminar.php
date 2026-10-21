<?php
require 'config.php'; requiere_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
$t = $_POST['t'] ?? ''; $e = entidad($t); $id = (int)($_POST['id'] ?? 0);
try {
    $st = $conexion->prepare("DELETE FROM $t WHERE {$e['pk']} = ?");
    $st->bind_param('i', $id); $st->execute();
    $_SESSION['msg'] = ucfirst($e['singular']) . ' eliminado correctamente.';
} catch (mysqli_sql_exception $ex) {
    $_SESSION['error'] = ($ex->getCode() == 1451)
        ? "No se puede eliminar este {$e['singular']} porque tiene registros relacionados (por ejemplo cuentas o préstamos). Elimina primero esos registros, o cambia su estatus a INACTIVO."
        : 'No se pudo eliminar: ' . $ex->getMessage();
}
header("Location: lista.php?t=$t");
