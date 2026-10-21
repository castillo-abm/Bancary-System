<?php
require 'config.php'; requiere_login();
$t = $_GET['t'] ?? ''; $e = entidad($t);

// ---- Buscador, filtro de vencidos y paginación ----
$q = trim($_GET['q'] ?? '');
$filtro = $_GET['f'] ?? '';
$porPagina = 15;
$pagina = max(1, (int)($_GET['p'] ?? 1));

$where = []; $params = []; $types = '';
if ($q !== '') {
    // Busca el texto en todas las columnas visibles; si es un número, también por ID exacto
    $campos = array_values($e['cols']);
    $cond = "CONCAT_WS(' ', " . implode(', ', $campos) . ") LIKE ?";
    $params[] = "%$q%"; $types .= 's';
    if (ctype_digit($q)) { $cond .= " OR {$e['pk']} = ?"; $params[] = (int)$q; $types .= 'i'; }
    $where[] = "($cond)";
}
if ($t === 'prestamo' && $filtro === 'vencidos') $where[] = "estado = 'VENCIDO'";
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$st = $conexion->prepare("SELECT COUNT(*) FROM ({$e['sql']}) x $w");
if ($params) $st->bind_param($types, ...$params);
$st->execute();
$total = (int)$st->get_result()->fetch_row()[0];
$paginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($pagina, $paginas);
$offset = ($pagina - 1) * $porPagina;

$st = $conexion->prepare("SELECT * FROM ({$e['sql']}) x $w ORDER BY {$e['pk']} DESC LIMIT $porPagina OFFSET $offset");
if ($params) $st->bind_param($types, ...$params);
$st->execute();
$filas = $st->get_result();

$urlNuevo = $e['url_nuevo'] ?? "form.php?t=$t";
function enlace($t, $q, $f, $p) { return "lista.php?t=$t&q=" . urlencode($q) . "&f=" . urlencode($f) . "&p=$p"; }

cabecera($e['titulo']);
?>
<div class="barra">
  <h1><?= h($e['titulo']) ?></h1>
  <a class="btn" href="<?= h($urlNuevo) ?>">+ Registrar <?= h($e['singular']) ?></a>
</div>
<?php mensajes(); ?>

<form class="buscador" method="GET" action="lista.php">
  <input type="hidden" name="t" value="<?= h($t) ?>">
  <input type="hidden" name="f" value="<?= h($filtro) ?>">
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Buscar por nombre, ID, teléfono, correo...">
  <button type="submit">Buscar</button>
  <?php if ($q !== ''): ?><a class="btn claro" href="<?= h(enlace($t, '', $filtro, 1)) ?>">Limpiar</a><?php endif; ?>
</form>

<?php if ($t === 'prestamo'): ?>
  <p class="filtros">
    <a class="btn chico <?= $filtro === '' ? '' : 'claro' ?>" href="<?= h(enlace($t, $q, '', 1)) ?>">Todos</a>
    <a class="btn chico rojo <?= $filtro === 'vencidos' ? '' : 'claro' ?>" href="<?= h(enlace($t, $q, 'vencidos', 1)) ?>">Solo vencidos</a>
  </p>
<?php endif; ?>

<p class="total"><?= $total ?> resultado(s)</p>
<div class="scroll">
<table>
  <tr><?php foreach ($e['cols'] as $titulo => $campo) echo "<th>" . h($titulo) . "</th>"; ?><th>Acciones</th></tr>
  <?php if ($filas->num_rows === 0): ?>
    <tr><td colspan="<?= count($e['cols']) + 1 ?>" class="vacio"><?= $q !== '' || $filtro ? 'No se encontró nada con ese criterio.' : 'Todavía no hay registros.' ?></td></tr>
  <?php endif; ?>
  <?php while ($f = $filas->fetch_assoc()): $id = $f[$e['pk']]; ?>
  <tr>
    <?php foreach ($e['cols'] as $campo): $v = $f[$campo];
        if (in_array($campo, $e['dinero'] ?? [])) $v = '$' . number_format((float)$v, 2);
        if ($campo === 'estatus' || $campo === 'estado') echo "<td><span class='badge b-" . strtolower(h($v)) . "'>" . h($v) . "</span></td>";
        else echo "<td>" . h($v) . "</td>";
    endforeach; ?>
    <td class="acciones">
      <?php if (isset($e['url_ver'])): ?>
        <a class="btn chico" href="<?= h($e['url_ver'] . $id) ?>">Ver</a>
      <?php else: ?>
        <a class="btn chico" href="form.php?t=<?= $t ?>&id=<?= $id ?>">Editar</a>
      <?php endif; ?>
      <form method="POST" action="eliminar.php" onsubmit="return confirm('¿Seguro que quieres eliminar este <?= h($e['singular']) ?>?');">
        <input type="hidden" name="t" value="<?= $t ?>"><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn chico rojo" type="submit">Eliminar</button>
      </form>
    </td>
  </tr>
  <?php endwhile; ?>
</table>
</div>

<?php if ($paginas > 1): ?>
<div class="paginas">
  <?php if ($pagina > 1): ?><a class="btn chico claro" href="<?= h(enlace($t, $q, $filtro, $pagina - 1)) ?>">« Anterior</a><?php endif; ?>
  <span>Página <?= $pagina ?> de <?= $paginas ?></span>
  <?php if ($pagina < $paginas): ?><a class="btn chico claro" href="<?= h(enlace($t, $q, $filtro, $pagina + 1)) ?>">Siguiente »</a><?php endif; ?>
</div>
<?php endif; ?>
<?php pie(); ?>
