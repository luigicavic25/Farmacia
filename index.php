<?php
// home.php - tela inicial do sistema Farmácia
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/functions.php';
require_login(); // garante que só usuário autenticado acesse

// buscar dados do usuário logado
$user_id = $_SESSION['usuario_id'] ?? null;
$user = null;
if ($user_id) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// dados para exibir
$nome_completo = $user['nome_completo'] ?? $user['nome'] ?? 'Usuário';
$papel = $user['papel'] ?? ($user['role'] ?? 'Usuário');
$profile_path = __DIR__ . '/img/profiles/' . ($user_id ?: '0') . '.jpg';
$profile_url  = '/Farmacia/img/profiles/' . ($user_id ?: '0') . '.jpg';
$has_profile = file_exists($profile_path);

// tratar upload de foto (mesma página)
$upload_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_profile'])) {
    if (!isset($_FILES['profile']) || $_FILES['profile']['error'] !== UPLOAD_ERR_OK) {
        $upload_msg = "Erro no upload. Tente novamente.";
    } else {
        // aceitar apenas jpg/png
        $allowed = ['image/jpeg' => '.jpg', 'image/png' => '.png'];
        $type = $_FILES['profile']['type'];
        if (!array_key_exists($type, $allowed)) {
            $upload_msg = "Formato inválido. Use JPG ou PNG.";
        } else {
            // garantir pasta
            $target_dir = __DIR__ . '/img/profiles';
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

            // salvar como {id}.jpg (convertendo PNG para JPG se quiser manter jpg)
            $ext = $allowed[$type];
            $target_file = $target_dir . '/' . $user_id . $ext;

            // mover
            if (move_uploaded_file($_FILES['profile']['tmp_name'], $target_file)) {
                // opcional: se for png e quiser forçar jpg, podemos converter — por enquanto mantemos extensão original
                $upload_msg = "Foto enviada com sucesso.";
                // atualiza variáveis para mostrar a imagem nova (usar URL com timestamp para cache bust)
                $profile_url = '/Farmacia/img/profiles/' . $user_id . $ext . '?t=' . time();
                $has_profile = true;
            } else {
                $upload_msg = "Falha ao salvar arquivo.";
            }
        }
    }
}

// caminhos (logo)
$logo_path = __DIR__ . '/img/logo.png';
$logo_url  = '/Farmacia/img/logo.png';
$has_logo = file_exists($logo_path);

?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Painel - Farmácia</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{ --accent:#1976d2; --blue:#0aa1e8; --muted:#666; --bg:#f7f7f8; }
    body{font-family:Inter, "Segoe UI", Roboto, Arial, sans-serif; margin:0; background:var(--bg); color:#222;}
    .top{background:var(--accent); color:#fff; padding:18px 28px; display:flex; align-items:center; gap:20px;}
    .logo{height:64px; width:64px; object-fit:contain;}
    .welcome{flex:1}
    .welcome h1{margin:0;font-size:28px;font-weight:600}
    .welcome p{margin:4px 0 0;color:rgba(255,255,255,0.9)}
    .container{max-width:1100px;margin:24px auto;padding:18px;}
    .card{background:#fff;border-radius:10px;padding:18px;box-shadow:0 6px 18px rgba(0,0,0,0.06);display:flex;gap:24px;align-items:center;}
    .profile{width:220px;text-align:center;padding:10px;}
    .avatar{width:160px;height:160px;border-radius:6px;background:#eee;display:inline-block;overflow:hidden;border:6px solid #e9e9e9}
    .avatar img{width:100%;height:100%;object-fit:cover;display:block}
    .info{flex:1}
    .role{font-weight:700;color:var(--muted); margin-bottom:14px}
    .actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-top:12px}
    .btn{display:block;padding:22px;border-radius:16px;background:var(--blue);color:#fff;text-decoration:none;text-align:center;font-size:20px;box-shadow:0 6px 14px rgba(10,161,232,0.18)}
    .small{font-size:14px;color:var(--muted);margin-top:6px}
    form.upload{margin-top:12px}
    .msg{margin-top:8px;color:green}
    @media (max-width:760px){ .card{flex-direction:column;align-items:stretch} .profile{width:100%} }
  </style>
</head>
<body>

<div class="top">
  <?php if ($has_logo): ?>
    <img src="<?= htmlspecialchars($logo_url) ?>" class="logo" alt="Logo">
  <?php else: ?>
    <div style="width:64px;height:64px;background:#fff;border-radius:6px;display:flex;align-items:center;justify-content:center;color:var(--accent);font-weight:700">LOGO</div>
  <?php endif; ?>

  <div class="welcome">
    <h1>Olá, <?= htmlspecialchars($nome_completo) ?></h1>
    <p><?= htmlspecialchars($papel) ?></p>
  </div>

  <div style="text-align:right">
    <a href="/Farmacia/logout.php" style="background:#fff;padding:8px 14px;border-radius:10px;color:var(--accent);text-decoration:none;font-weight:700">SAIR</a>
  </div>
</div>

<div class="container">
  <div class="card">
    <div class="profile">
      <div class="avatar">
        <?php if ($has_profile): ?>
          <img src="<?= htmlspecialchars($profile_url) ?>" alt="Foto perfil">
        <?php else: ?>
          <img src="/Farmacia/img/default-avatar.png" alt="sem foto">
        <?php endif; ?>
      </div>
      <div class="small"><?= htmlspecialchars($user['email'] ?? '') ?></div>

      <!-- upload form -->
      <form class="upload" method="post" enctype="multipart/form-data">
        <label style="display:block;margin-top:10px;font-size:14px">Atualizar foto</label>
        <input type="file" name="profile" accept="image/jpeg,image/png" style="margin-top:6px">
        <div style="margin-top:8px">
          <button type="submit" name="upload_profile" style="padding:8px 12px;border-radius:8px;border:0;background:var(--accent);color:#fff">Enviar</button>
        </div>
        <?php if ($upload_msg): ?><div class="msg"><?= htmlspecialchars($upload_msg) ?></div><?php endif; ?>
      </form>
    </div>

    <div class="info">
      <div class="role"><?= htmlspecialchars($papel) ?></div>
      <div style="font-size:16px;color:#444">
        <strong>Nome completo:</strong> <?= nl2br(htmlspecialchars($nome_completo)) ?><br>
        <strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? '-') ?>
      </div>

      <div class="actions">
        <a class="btn" href="/Farmacia/movimentos/entrada.php">Movimentação (Entradas)</a>
        <a class="btn" href="/Farmacia/produtos/cadastro_produtos.php">Cadastrar Produtos</a>
        <a class="btn" href="/Farmacia/produtos/lotes/lotes.php">Lotes</a>
        <a class="btn" href="/Farmacia/usuarios/usuarios.php">Usuários</a>
        <a class="btn" href="/Farmacia/movimentos/saida.php">Registrar Saída</a>
        <a class="btn" href="/Farmacia/movimentos/historico_mov.php">Histórico</a>
      </div>

      <div style="margin-top:14px;color:var(--muted)">Versão 1.0 • Sistema Farmácia</div>
    </div>
  </div>
</div>

</body>
</html>
