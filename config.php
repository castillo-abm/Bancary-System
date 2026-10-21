<?php
// ===== CONFIGURACIÓN GENERAL (conexión, usuario y definición de las tablas) =====
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conexion = new mysqli("localhost", "root", "", "banco");   // host, usuario, contraseña, base
    $conexion->set_charset("utf8mb4");
} catch (Exception $e) {
    die("No se pudo conectar a la base de datos. ¿Están encendidos Apache y MySQL en XAMPP? ¿Ya importaste banco.sql?");
}

// Usuario para entrar a la página (puedes cambiarlo)
define('USUARIO', 'admin');
define('CLAVE', 'banco123');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function requiere_login() { if (empty($_SESSION['ok'])) { header('Location: login.php'); exit; } }
function opciones_fk($sql) {
    global $conexion; $o = [];
    $r = $conexion->query($sql);
    while ($f = $r->fetch_row()) $o[$f[0]] = $f[1];
    return $o;
}

$ESTATUS = ['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo'];

// ===== REGLAS DEL PRÉSTAMO =====
define('NOMBRE_BANCO', 'Banco PIA');   // nombre ficticio que sale en el convenio

// Plazo en meses => [tasa de interés anual %, comisión por apertura %]
// A más plazo, más interés y más comisión.
$PLAZOS = [
    6  => ['tasa' => 12.0, 'comision' => 2.0],
    12 => ['tasa' => 18.0, 'comision' => 3.0],
    18 => ['tasa' => 21.0, 'comision' => 3.5],
    24 => ['tasa' => 24.0, 'comision' => 4.0],
    36 => ['tasa' => 30.0, 'comision' => 5.0],
];

// Qué garantía se pide según el monto del préstamo
function garantia_requerida($monto) {
    if ($monto <= 20000)  return ['tipo' => 'BIEN',     'nombre' => 'Bien (electrónico, herramienta, etc.)', 'doc' => 'Número de nota o comprobante de compra'];
    if ($monto <= 150000) return ['tipo' => 'VEHICULO', 'nombre' => 'Vehículo',                              'doc' => 'Número de factura del vehículo'];
    return                       ['tipo' => 'CASA',     'nombre' => 'Casa o propiedad',                      'doc' => 'Número de escritura'];
}

// Calcula comisión, mensualidad y total con la fórmula de pagos iguales
// (mensualidad = M * i / (1 - (1+i)^-n), con i = tasa anual / 12)
function calcular_prestamo($monto, $plazo) {
    global $PLAZOS;
    if (!isset($PLAZOS[$plazo]) || $monto <= 0) return null;
    $tasa = $PLAZOS[$plazo]['tasa']; $pctCom = $PLAZOS[$plazo]['comision'];
    $i = $tasa / 100 / 12;
    $mensualidad = round($monto * $i / (1 - pow(1 + $i, -$plazo)), 2);
    return [
        'tasa' => $tasa, 'comision_pct' => $pctCom,
        'comision' => round($monto * $pctCom / 100, 2),
        'mensualidad' => $mensualidad,
        'total' => round($mensualidad * $plazo, 2),
        'intereses' => round($mensualidad * $plazo - $monto, 2),
    ];
}

// Tabla de pagos mes por mes (capital, interés y saldo restante)
function tabla_pagos($monto, $plazo, $tasa, $mensualidad, $fechaInicio) {
    $i = $tasa / 100 / 12; $saldo = $monto; $filas = [];
    for ($n = 1; $n <= $plazo; $n++) {
        $interes = round($saldo * $i, 2);
        $capital = round($mensualidad - $interes, 2);
        if ($n == $plazo) { $capital = round($saldo, 2); }   // el último pago ajusta centavos
        $saldo = round($saldo - $capital, 2);
        $fecha = (new DateTime($fechaInicio))->modify("+$n month")->format('Y-m-d');
        $filas[] = ['n' => $n, 'fecha' => $fecha, 'pago' => $mensualidad, 'capital' => $capital, 'interes' => $interes, 'saldo' => max($saldo, 0)];
    }
    return $filas;
}

// Cada tabla se define aquí: sus campos (para el formulario) y su consulta (para el listado)
$E = [
 'cliente' => [
   'titulo' => 'Clientes', 'singular' => 'cliente', 'pk' => 'id_cliente',
   'campos' => [
     'nombre'    => ['label' => 'Nombre',    'tipo' => 'text',  'req' => 1, 'max' => 100],
     'telefono'  => ['label' => 'Teléfono',  'tipo' => 'text',  'max' => 20, 'regex' => '/^[0-9+\- ]{7,20}$/', 'errregex' => 'El teléfono solo puede llevar números (mínimo 7 dígitos).'],
     'correo'    => ['label' => 'Correo',    'tipo' => 'email', 'req' => 1, 'max' => 100],
     'domicilio' => ['label' => 'Domicilio', 'tipo' => 'text',  'req' => 1, 'max' => 100],
     'estatus'   => ['label' => 'Estatus',   'tipo' => 'select', 'opts' => $ESTATUS],
   ],
   'sql'  => "SELECT id_cliente, nombre, telefono, correo, domicilio, estatus FROM cliente ORDER BY id_cliente DESC",
   'cols' => ['ID' => 'id_cliente', 'Nombre' => 'nombre', 'Teléfono' => 'telefono', 'Correo' => 'correo', 'Domicilio' => 'domicilio', 'Estatus' => 'estatus'],
 ],
 'empleado' => [
   'titulo' => 'Empleados', 'singular' => 'empleado', 'pk' => 'id_empleado',
   'campos' => [
     'nombre'      => ['label' => 'Nombre',   'tipo' => 'text', 'req' => 1, 'max' => 100],
     'telefono'    => ['label' => 'Teléfono', 'tipo' => 'text', 'max' => 20, 'regex' => '/^[0-9+\- ]{7,20}$/', 'errregex' => 'El teléfono solo puede llevar números (mínimo 7 dígitos).'],
     'puesto'      => ['label' => 'Puesto',   'tipo' => 'text', 'req' => 1, 'max' => 100],
     'salario'     => ['label' => 'Salario',  'tipo' => 'dec',  'req' => 1, 'min' => 0],
     'id_sucursal' => ['label' => 'Sucursal', 'tipo' => 'select', 'req' => 1, 'fk' => "SELECT id_sucursal, nombre_sucursal FROM sucursal ORDER BY nombre_sucursal"],
     'estatus'     => ['label' => 'Estatus',  'tipo' => 'select', 'opts' => $ESTATUS],
   ],
   'sql'  => "SELECT e.id_empleado, e.nombre, e.telefono, e.puesto, e.salario, s.nombre_sucursal AS sucursal, e.estatus FROM empleado e LEFT JOIN sucursal s ON e.id_sucursal = s.id_sucursal ORDER BY e.id_empleado DESC",
   'cols' => ['ID' => 'id_empleado', 'Nombre' => 'nombre', 'Teléfono' => 'telefono', 'Puesto' => 'puesto', 'Salario' => 'salario', 'Sucursal' => 'sucursal', 'Estatus' => 'estatus'],
   'dinero' => ['salario'],
 ],
 'cuenta' => [
   'titulo' => 'Cuentas', 'singular' => 'cuenta', 'pk' => 'id_cuenta',
   'campos' => [
     'tipo_cuenta' => ['label' => 'Tipo de cuenta', 'tipo' => 'select', 'req' => 1, 'opts' => ['Ahorro' => 'Ahorro', 'Débito' => 'Débito', 'Nómina' => 'Nómina']],
     'saldo'       => ['label' => 'Saldo',          'tipo' => 'dec', 'req' => 1, 'min' => 0, 'def' => '0'],
     'id_cliente'  => ['label' => 'Cliente propietario', 'tipo' => 'select', 'req' => 1, 'fk' => "SELECT id_cliente, nombre FROM cliente ORDER BY nombre"],
     'id_empleado' => ['label' => 'Empleado que atiende', 'tipo' => 'select', 'req' => 1, 'fk' => "SELECT id_empleado, nombre FROM empleado ORDER BY nombre"],
     'estatus'     => ['label' => 'Estatus',        'tipo' => 'select', 'opts' => $ESTATUS],
   ],
   'sql'  => "SELECT c.id_cuenta, c.num_cuenta, c.tipo_cuenta, c.saldo, cl.nombre AS cliente, e.nombre AS empleado, c.estatus FROM cuenta c JOIN cliente cl ON c.id_cliente = cl.id_cliente LEFT JOIN empleado e ON c.id_empleado = e.id_empleado ORDER BY c.id_cuenta DESC",
   'cols' => ['ID' => 'id_cuenta', 'No. cuenta' => 'num_cuenta', 'Tipo' => 'tipo_cuenta', 'Saldo' => 'saldo', 'Cliente' => 'cliente', 'Empleado' => 'empleado', 'Estatus' => 'estatus'],
   'dinero' => ['saldo'],
 ],
 'prestamo' => [
   'titulo' => 'Préstamos', 'singular' => 'préstamo', 'pk' => 'id_prestamo',
   // El préstamo tiene su propio formulario (prestamo_form.php) porque calcula intereses y pide garantía
   'campos' => [],
   'url_nuevo' => 'prestamo_form.php',
   'url_ver'   => 'prestamo_ver.php?id=',
   // El estado "VENCIDO" se calcula solo: si sigue VIGENTE pero la fecha de vencimiento ya pasó
   'sql'  => "SELECT p.id_prestamo, cl.nombre AS cliente, p.cantidad, p.plazo_meses, p.tasa_anual, p.mensualidad, p.fecha_vencimiento, CASE WHEN p.estado = 'VIGENTE' AND p.fecha_vencimiento < CURDATE() THEN 'VENCIDO' ELSE UPPER(p.estado) END AS estado FROM prestamo p JOIN cliente cl ON p.id_cliente = cl.id_cliente ORDER BY p.id_prestamo DESC",
   'cols' => ['ID' => 'id_prestamo', 'Cliente' => 'cliente', 'Monto' => 'cantidad', 'Plazo (meses)' => 'plazo_meses', 'Tasa anual %' => 'tasa_anual', 'Mensualidad' => 'mensualidad', 'Vencimiento' => 'fecha_vencimiento', 'Estado' => 'estado'],
   'dinero' => ['cantidad', 'mensualidad'],
 ],
];

function entidad($t) {
    global $E;
    if (!isset($E[$t])) { header('Location: index.php'); exit; }
    return $E[$t];
}

// Parte de arriba y de abajo de todas las páginas
function cabecera($titulo, $nav = true) {
    global $E;
    echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1'><title>" . h($titulo) . " - Banco</title><link rel='stylesheet' href='estilos.css'></head><body>";
    if ($nav) {
        echo "<nav><span class='logo'>🏦 Banco</span><a href='index.php'>Inicio</a>";
        foreach ($E as $k => $e) echo "<a href='lista.php?t=$k'>" . h($e['titulo']) . "</a>";
        echo "<a class='salir' href='logout.php'>Salir</a></nav>";
    }
    echo "<main>";
}
function pie() { echo "</main></body></html>"; }
function mensajes() {
    foreach (['msg' => 'ok', 'error' => 'error'] as $k => $cls) {
        if (!empty($_SESSION[$k])) { echo "<div class='alerta $cls'>" . h($_SESSION[$k]) . "</div>"; unset($_SESSION[$k]); }
    }
}
