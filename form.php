<?php
require 'config.php'; requiere_login();
$t = $_GET['t'] ?? ''; $e = entidad($t);
$id = (int)($_GET['id'] ?? 0);
$datos = [];
if ($id) {
    $st = $conexion->prepare("SELECT * FROM $t WHERE {$e['pk']} = ?");
    $st->bind_param('i', $id); $st->execute();
    $datos = $st->get_result()->fetch_assoc();
    if (!$datos) { header("Location: lista.php?t=$t"); exit; }
}
if (!empty($_SESSION['viejo'])) { $datos = array_merge($datos, $_SESSION['viejo']); unset($_SESSION['viejo']); }
$errores = $_SESSION['errores'] ?? []; unset($_SESSION['errores']);
cabecera(($id ? 'Editar ' : 'Registrar ') . $e['singular']);
?>
<div class="tarjeta formu">
  <h1><?= $id ? 'Editar' : 'Registrar' ?> <?= h($e['singular']) ?></h1>
  <?php foreach ($errores as $er) echo "<div class='alerta error'>" . h($er) . "</div>"; ?>
  <form method="POST" action="guardar.php">
    <input type="hidden" name="t" value="<?= $t ?>"><input type="hidden" name="id" value="<?= $id ?>">
    <?php if (($_GET['volver'] ?? '') === 'prestamo' && $t === 'cliente'): ?><input type="hidden" name="volver" value="prestamo"><?php endif; ?>
    <?php if ($t === 'cuenta' && $id): ?>
      <label>Número de cuenta</label><input type="text" value="<?= h($datos['num_cuenta']) ?>" disabled>
    <?php endif; ?>
    <?php foreach ($e['campos'] as $n => $c):
        $valor = $datos[$n] ?? ($c['def'] ?? '');
        $req = !empty($c['req']) ? 'required' : ''; ?>
      <label><?= h($c['label']) ?><?= $req ? ' *' : '' ?></label>
      <?php if ($c['tipo'] === 'select'):
          $opts = isset($c['fk']) ? opciones_fk($c['fk']) : $c['opts']; ?>
        <select name="<?= $n ?>" <?= $req ?>>
          <?php if (isset($c['fk'])) echo "<option value=''>-- Seleccione --</option>"; ?>
          <?php foreach ($opts as $v => $txt): ?>
            <option value="<?= h($v) ?>" <?= ((string)$valor === (string)$v) ? 'selected' : '' ?>><?= h($txt) ?></option>
          <?php endforeach; ?>
        </select>
      <?php else:
          $type = ['dec' => 'number', 'int' => 'number'][$c['tipo']] ?? $c['tipo'];
          $step = $c['tipo'] === 'dec' ? "step='0.01'" : ($c['tipo'] === 'int' ? "step='1'" : '');
          $min  = isset($c['min']) ? "min='{$c['min']}'" : ''; ?>
        <input type="<?= $type ?>" name="<?= $n ?>" value="<?= h($valor) ?>" <?= "$step $min $req" ?>>
      <?php endif; ?>
    <?php endforeach; ?>
    <button type="submit">Guardar</button>
    <a class="cancelar" href="<?= (($_GET['volver'] ?? '') === 'prestamo') ? 'prestamo_form.php' : "lista.php?t=$t" ?>">Cancelar</a>
  </form>
</div>
<?php pie(); ?>
