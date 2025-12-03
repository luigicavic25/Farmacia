<?php
// Farmacia/usuarios/edit.php (debuggable)
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../functions.php';
require_login();
require_once __DIR__ . '/../header.php';

$errors = [];
$success = '';

$edit_id = intval($_GET['id'] ?? 0);
if ($edit_id <= 0) {
    header('Location: usuarios.php');
    exit;
}

$current_user_id = $_SESSION['usuario_id'] ?? null;
$current_user_papel = strtolower($_SESSION['usuario_papel'] ?? '');
$is_admin = ($current_user_papel === 'admin' || $current_user_papel === 'administrador');

// detectar nome_completo
$has_nome_completo = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
    $fields = array_column($cols, 'Field');
    $has_nome_completo = in_array('nome_completo', $fields);
} catch (Exception $e) {
    $has_nome_completo = false;
}

if (!$is_admin && $current_user_id != $edit_id) {
    $errors[] = 'Você não tem permissão para editar esse usuário.';
}

// SELECT para buscar dados
try {
    $select_sql = "SELECT id, nome, email, papel" . ($has_nome_completo ? ", nome_completo" : "") . " FROM usuarios WHERE id = ? LIMIT 1";
    $st = $pdo->prepare($select_sql);
    $st->execute([$edit_id]);
    $user = $st->fetch(PDO::FETCH_ASSOC);
    if (!$user) $errors[] = 'Usuário não encontrado';
} catch (Exception $e) {
    $errors[] = 'Erro ao buscar usuário: ' . $e->getMessage();
    $user = null;
}

// DEBUG helper: mostra POST enviado (apague depois)
function dbg($v){ echo '<pre style="background:#f7f7f7;border:1px solid #ddd;padding:8px;">'.htmlspecialchars(print_r($v, true)).'</pre>'; }

// processar POST (salvar alterações)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nome_completo = $has_nome_completo ? trim($_POST['nome_completo'] ?? '') : null;
    $nova_senha = $_POST['nova_senha'] ?? '';

    // Normalizar papel
    if ($is_admin) {
        $papel = trim($_POST['papel'] ?? '');

        // impedir valores vazios/invalidos
        $validos = ['farmaceutico','atendente','admin','administrador'];

        if (!in_array($papel, $validos, true)) {
            // Se vier vazio ou inválido, mantém o valor atual
            $papel = $user['papel'];
        }

        // Normalizar "administrador" para "admin"
        if ($papel === 'administrador') {
            $papel = 'admin';
        }

    } else {
        $papel = $user['papel']; // segurança
    }


    $nome_completo = $has_nome_completo ? trim($_POST['nome_completo'] ?? ($user['nome_completo'] ?? '')) : null;

    // validações
    if ($nome === '' || $email === '') {
        $errors[] = 'Nome e email são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email inválido.';
    } else {
        try {
            // checar duplicidade de email
            $chk = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ? LIMIT 1");
            $chk->execute([$email, $edit_id]);
            if ($chk->fetch()) {
                $errors[] = 'Email já em uso.';
            } else {
                // montar UPDATE explicitamente para debugging (sem dinamismo complexo)
                $sql = "UPDATE usuarios SET nome = ?, email = ?, papel = ?";
                $params = [$nome, $email, $papel];

                if ($has_nome_completo) {
                    $sql .= ", nome_completo = ?";
                    $params[] = ($nome_completo !== '' ? $nome_completo : $nome);
                }

                if ($nova_senha !== '') {
                    $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                    $sql .= ", senha = ?";
                    $params[] = $hash;
                }

                $sql .= " WHERE id = ?";
                $params[] = $edit_id;

                // executar e debugar resultado
                $upd = $pdo->prepare($sql);
                $ok = $upd->execute($params);


                if ($ok) {
                    // se rowCount === 0 pode ser que nada tenha sido alterado (valores iguais) — mas update foi executado
                    $success = 'Usuário atualizado com sucesso.';
                    // recarregar dados
                    $st2 = $pdo->prepare($select_sql);
                    $st2->execute([$edit_id]);
                    $user = $st2->fetch(PDO::FETCH_ASSOC);
                    if ($current_user_id == $edit_id) {
                        $_SESSION['usuario_nome'] = $user['nome'] ?? $_SESSION['usuario_nome'];
                        $_SESSION['usuario_papel'] = $user['papel'] ?? $_SESSION['usuario_papel'];
                    }
                } else {
                    $errors[] = 'Falha ao executar update. errorInfo: ' . implode(' | ', $info);
                }
            }

        } catch (Exception $e) {
            $errors[] = 'Exceção ao atualizar: ' . $e->getMessage();
        }
    }
}
?>

<h2>Editar Usuário (debug)</h2>

<?php if (!empty($errors)): ?>
  <div style="color:red;margin-bottom:12px">
    <?php foreach($errors as $er) echo '<div>'.htmlspecialchars($er).'</div>'; ?>
  </div>
<?php endif; ?>

<?php if ($success): ?>
  <div style="color:green;margin-bottom:12px"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($user): ?>
<form method="post" style="max-width:720px">
  <?php if ($has_nome_completo): ?>
    <label>Nome completo:</label><br>
    <input type="text" name="nome_completo" value="<?= htmlspecialchars($user['nome_completo'] ?? '') ?>" style="width:100%;padding:8px;margin:6px 0"><br>
  <?php endif; ?>

  <label>Nome (exibido):</label><br>
  <input type="text" name="nome" value="<?= htmlspecialchars($user['nome']) ?>" style="width:100%;padding:8px;margin:6px 0"><br>

  <label>Email:</label><br>
  <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" style="width:100%;padding:8px;margin:6px 0"><br>

  <?php if ($is_admin): ?>
    <label>Papel:</label><br>
    <select name="papel" style="padding:8px;margin:6px 0">
      <option value="farmaceutico"     <?= ($user['papel']==='farmaceutico') ? 'selected' : '' ?>>Farmaceutico</option>
      <option value="atendente" <?= ($user['papel']==='atendente') ? 'selected' : '' ?>>Atendente</option>
      <option value="admin"       <?= ($user['papel']==='admin') ? 'selected' : '' ?>>Administrador</option>
    </select><br>
  <?php else: ?>
    <div style="margin:6px 0;color:#666">Papel: <?= htmlspecialchars($user['papel']) ?> (não pode alterar)</div>
  <?php endif; ?>

  <label>Nova senha (opcional):</label><br>
  <input type="password" name="nova_senha" style="width:100%;padding:8px;margin:6px 0"><br>

  <button type="submit" class="btn" style="padding:10px 16px">Salvar Alterações</button>
  <a href="usuarios.php" style="margin-left:12px">Voltar</a>
</form>
<?php endif; ?>

<?php require_once __DIR__ . '/../footer.php'; ?>
