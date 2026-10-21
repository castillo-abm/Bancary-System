<?php
require 'config.php'; requiere_login();

$viejo = $_SESSION['viejo_prestamo'] ?? []; unset($_SESSION['viejo_prestamo']);
$errores = $_SESSION['errores'] ?? []; unset($_SESSION['errores']);

// Cliente ya elegido (viene de "Crear cliente" o de un intento con errores)
$idCliente = (int)($_GET['id_cliente'] ?? ($viejo['id_cliente'] ?? 0));
$clienteSel = null;
if ($idCliente) {
    $st = $conexion->prepare("SELECT id_cliente, nombre, telefono, correo FROM cliente WHERE id_cliente = ?");
    $st->bind_param('i', $idCliente); $st->execute();
    $clienteSel = $st->get_result()->fetch_assoc();
}
$v = fn($k, $def = '') => h($viejo[$k] ?? $def);

cabecera('Nuevo préstamo');
?>
<div class="tarjeta formu ancho">
  <h1>Registrar préstamo</h1>
  <?php foreach ($errores as $er) echo "<div class='alerta error'>" . h($er) . "</div>"; ?>

  <form method="POST" action="prestamo_guardar.php" id="formPrestamo">
    <h3>1. Cliente</h3>
    <input type="hidden" name="id_cliente" id="id_cliente" value="<?= $clienteSel ? (int)$clienteSel['id_cliente'] : '' ?>">

    <div id="clienteElegido" class="elegido" <?= $clienteSel ? '' : 'hidden' ?>>
      <span id="clienteTexto"><?= $clienteSel ? h("#{$clienteSel['id_cliente']} · {$clienteSel['nombre']} · {$clienteSel['telefono']}") : '' ?></span>
      <button type="button" class="chico claro" id="cambiarCliente">Cambiar</button>
    </div>

    <div id="zonaBusqueda" <?= $clienteSel ? 'hidden' : '' ?>>
      <label for="buscarCliente">Buscar cliente (por ID, nombre, teléfono o correo)</label>
      <input type="search" id="buscarCliente" autocomplete="off" placeholder="Escribe al menos 1 letra o número...">
      <ul id="resultados" class="resultados"></ul>
      <p class="ayuda">¿No existe? <a class="btn chico claro" href="form.php?t=cliente&volver=prestamo">+ Crear cliente nuevo</a></p>
    </div>

    <h3>2. Préstamo</h3>
    <label for="cantidad">Monto que se presta *</label>
    <input type="number" name="cantidad" id="cantidad" min="1000" max="2000000" step="0.01" required value="<?= $v('cantidad') ?>">

    <label for="plazo_meses">Plazo *</label>
    <select name="plazo_meses" id="plazo_meses" required>
      <?php foreach ($PLAZOS as $meses => $p): ?>
        <option value="<?= $meses ?>" <?= ((int)($viejo['plazo_meses'] ?? 12) === $meses) ? 'selected' : '' ?>><?= $meses ?> meses (interés <?= $p['tasa'] ?>% anual)</option>
      <?php endforeach; ?>
    </select>

    <label for="fecha_inicio">Fecha del préstamo *</label>
    <input type="date" name="fecha_inicio" id="fecha_inicio" required value="<?= $v('fecha_inicio', date('Y-m-d')) ?>">

    <div id="resumen" class="resumen" hidden>
      <h4>Así quedaría el préstamo</h4>
      <table class="mini">
        <tr><td>Comisión por apertura (<span id="rPct"></span>%)</td><td id="rCom"></td></tr>
        <tr><td>Mensualidad</td><td id="rMens"></td></tr>
        <tr><td>Total de intereses</td><td id="rInt"></td></tr>
        <tr><td><strong>Total a pagar en mensualidades</strong></td><td id="rTotal"></td></tr>
        <tr><td>Fecha de vencimiento (último pago)</td><td id="rVence"></td></tr>
      </table>
    </div>

    <h3>3. Garantía</h3>
    <p class="ayuda" id="textoGarantia">Escribe el monto para saber qué garantía se pide.</p>
    <label for="g_descripcion">Descripción de la garantía *</label>
    <input type="text" name="g_descripcion" id="g_descripcion" maxlength="255" required value="<?= $v('g_descripcion') ?>" placeholder="Ej. Nissan Versa 2020 gris">
    <label for="g_documento" id="labelDoc">Número de documento *</label>
    <input type="text" name="g_documento" id="g_documento" maxlength="100" required value="<?= $v('g_documento') ?>">
    <label for="g_valor">Valor estimado de la garantía *</label>
    <input type="number" name="g_valor" id="g_valor" min="1" step="0.01" required value="<?= $v('g_valor') ?>">
    <p class="ayuda">El valor de la garantía debe ser igual o mayor al monto del préstamo.</p>

    <button type="submit">Guardar préstamo</button>
    <a class="cancelar" href="lista.php?t=prestamo">Cancelar</a>
  </form>
</div>

<script>
// ---------- Buscador de clientes ----------
const caja = document.getElementById('buscarCliente');
const lista = document.getElementById('resultados');
let temporizador;
caja.addEventListener('input', () => {
  clearTimeout(temporizador);
  const q = caja.value.trim();
  if (!q) { lista.innerHTML = ''; return; }
  temporizador = setTimeout(async () => {          // espera 250 ms para no buscar en cada tecla
    const r = await fetch('buscar_cliente.php?q=' + encodeURIComponent(q));
    const datos = await r.json();
    lista.innerHTML = '';
    if (!datos.length) { lista.innerHTML = '<li class="nada">Sin resultados. Puedes crear el cliente nuevo.</li>'; return; }
    datos.forEach(c => {
      const li = document.createElement('li');
      li.textContent = `#${c.id_cliente} · ${c.nombre} · ${c.telefono || 'sin teléfono'}`;
      li.onclick = () => elegir(c);
      lista.appendChild(li);
    });
  }, 250);
});
function elegir(c) {
  document.getElementById('id_cliente').value = c.id_cliente;
  document.getElementById('clienteTexto').textContent = `#${c.id_cliente} · ${c.nombre} · ${c.telefono || ''}`;
  document.getElementById('clienteElegido').hidden = false;
  document.getElementById('zonaBusqueda').hidden = true;
}
document.getElementById('cambiarCliente').onclick = () => {
  document.getElementById('id_cliente').value = '';
  document.getElementById('clienteElegido').hidden = true;
  document.getElementById('zonaBusqueda').hidden = false;
  caja.value = ''; lista.innerHTML = ''; caja.focus();
};
document.getElementById('formPrestamo').addEventListener('submit', ev => {
  if (!document.getElementById('id_cliente').value) { ev.preventDefault(); alert('Primero elige un cliente.'); }
});

// ---------- Cálculo en pantalla (el servidor lo vuelve a calcular al guardar) ----------
const PLAZOS = <?= json_encode($PLAZOS) ?>;
const dinero = n => '$' + n.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
function garantia(m) {
  if (m <= 20000)  return ['Bien (electrónico, herramienta, etc.)', 'Número de nota o comprobante de compra'];
  if (m <= 150000) return ['Vehículo', 'Número de factura del vehículo'];
  return ['Casa o propiedad', 'Número de escritura'];
}
function actualizar() {
  const monto = parseFloat(document.getElementById('cantidad').value);
  const plazo = parseInt(document.getElementById('plazo_meses').value);
  const caja = document.getElementById('resumen');
  if (!monto || monto <= 0) { caja.hidden = true; return; }
  const p = PLAZOS[plazo], i = p.tasa / 100 / 12;
  const mens = Math.round(monto * i / (1 - Math.pow(1 + i, -plazo)) * 100) / 100;
  document.getElementById('rPct').textContent = p.comision;
  document.getElementById('rCom').textContent = dinero(monto * p.comision / 100);
  document.getElementById('rMens').textContent = dinero(mens);
  document.getElementById('rInt').textContent = dinero(mens * plazo - monto);
  document.getElementById('rTotal').textContent = dinero(mens * plazo);
  const f = new Date(document.getElementById('fecha_inicio').value + 'T00:00:00');
  if (!isNaN(f)) { f.setMonth(f.getMonth() + plazo); document.getElementById('rVence').textContent = f.toLocaleDateString('es-MX', {day: '2-digit', month: 'long', year: 'numeric'}); }
  caja.hidden = false;
  const [tipo, doc] = garantia(monto);
  document.getElementById('textoGarantia').innerHTML = 'Para este monto se pide como garantía: <strong>' + tipo + '</strong>.';
  document.getElementById('labelDoc').textContent = doc + ' *';
}
['cantidad', 'plazo_meses', 'fecha_inicio'].forEach(id => document.getElementById(id).addEventListener('input', actualizar));
actualizar();
</script>
<?php pie(); ?>
