<?php
require 'config.php';
if (!empty($_SESSION['ok'])) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (hash_equals(USUARIO, $_POST['usuario'] ?? '') && hash_equals(CLAVE, $_POST['clave'] ?? '')) {
        $_SESSION['ok'] = true;
        header('Location: index.php'); exit;
    }
    $error = 'Usuario o contraseña incorrectos.';
}
cabecera('Entrar', false);
?>
<div class="tarjeta chica">
  <h1>🏦 Sistema del Banco</h1>
  <?php if ($error) echo "<div class='alerta error'>" . h($error) . "</div>"; ?>
  <form method="POST">
    <label>Usuario</label><input type="text" name="usuario" required autofocus>
    <label>Contraseña</label><input type="password" name="clave" required>
    <button type="submit">Entrar</button>
  </form>
</div>
<?php pie(); ?>
