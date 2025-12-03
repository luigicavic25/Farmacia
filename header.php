<?php
// header.php - header genérico e seguro para o projeto Farmacia
// garante que a functions.php do root seja incluída apenas uma vez
if (session_status() === PHP_SESSION_NONE) session_start();

$functions_path = __DIR__ . '/functions.php';
if (file_exists($functions_path) && !defined('FUNCTIONS_INCLUDED')) {
    require_once $functions_path;
    // marca que já incluímos para evitar includes repetidos
    if (!defined('FUNCTIONS_INCLUDED')) define('FUNCTIONS_INCLUDED', true);
}
// se por algum motivo a session não estiver iniciada, inicia
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper para escapar saída (caso functions.php não tenha)
if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Farmácia - Sistema</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body { font-family: Arial, Helvetica, sans-serif; margin: 16px; background:#f7f7f7; color:#222; }
    header { background:#fff; padding:12px 16px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
    nav a { margin-right:12px; text-decoration:none; color:#0073e6; font-weight:500; }
    .container { max-width:1100px; margin:18px auto; background:#fff; padding:18px; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,0.05); }
    .right { float:right; }
    footer.small { font-size:13px; color:#666; text-align:center; padding:12px; margin-top:16px; }
    .btn { display:inline-block; padding:6px 10px; border-radius:6px; background:#0073e6; color:#fff; text-decoration:none; }
    .btn.gray { background:#888; }
    table { width:100%; border-collapse:collapse; }
    table th, table td { padding:8px 10px; border-bottom:1px solid #eee; text-align:left; }
    @media (max-width:720px){ .container{margin:8px} nav a{display:inline-block;margin:6px 4px} }
  </style>
</head>
<body>
<header>
  <div style="max-width:1100px;margin:0 auto;">
    <a href="/Farmacia/index.php" style="font-weight:700;color:#000;text-decoration:none">Farmácia</a>
    <nav class="right" style="display:inline-block;margin-left:18px">
      <a href="/Farmacia/produtos/index.php">Produtos</a>
      <a href="/Farmacia/movimentos/historico_mov.php">Movimentos</a>
      <?php if (!empty($_SESSION['usuario_papel']) && $_SESSION['usuario_papel'] === 'admin'): ?>
        <a href="/Farmacia/usuarios/usuarios.php">Usuários</a>
      <?php endif; ?>

      <?php if (!empty($_SESSION['usuario_nome'])): ?>
        <span style="margin-left:10px;color:#333">Olá, <?= e($_SESSION['usuario_nome']) ?></span>
        <a href="/Farmacia/logout.php" class="btn" style="margin-left:8px">Sair</a>
      <?php else: ?>
        <a href="/Farmacia/login.php" class="btn">Login</a>
      <?php endif; ?>
    </nav>
    <div style="clear:both"></div>
  </div>
</header>

<div class="container">
<!-- conteúdo da página começa aqui -->
