<?php
// Farmacia/register.php
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/functions.php';

// Se está logado e NÃO é admin, bloquear acesso (redirecionar)
if (function_exists('is_logged_in') && is_logged_in()) {
    // verifica papel do usuário
    $user_papel = $_SESSION['usuario_papel'] ?? $_SESSION['usuario_role'] ?? null;
    if (strtolower($user_papel) !== 'admin' && strtolower($user_papel) !== 'administrador') {
        header('Location: index.php');
        exit;
    }
}

// inclui header (padrão do sistema)
require_once __DIR__ . '/header.php';

$erro = '';
$sucesso = '';

// detectar colunas úteis na tabela usuarios
$has_nome_completo = false;
$has_criado_em = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
    $fields = array_column($cols, 'Field');
    $has_nome_completo = in_array('nome_completo', $fields);
    $has_criado_em = in_array('criado_em', $fields);
} catch (Exception $e) {
    // se falhar, não é bloqueante
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $papel = $_POST['papel'] ?? 'Atendente';
    $nome_completo_val = trim($_POST['nome_completo'] ?? '');

    // validações básicas
    if ($nome === '' || $email === '' || $senha === '') {
        $erro = 'Preencha Nome, Email e Senha.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Email inválido.';
    } else {
        try {
            // verifica duplicidade por email
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $erro = 'E-mail já cadastrado.';
            } else {
                $hash = password_hash($senha, PASSWORD_DEFAULT);

                // montar INSERT dinamicamente caso existam colunas extras
                $colsIns = ['nome','email','senha','papel'];
                $placeholders = ['?','?','?','?'];
                $vals = [$nome, $email, $hash, $papel];

                if ($has_nome_completo) {
                    $colsIns[] = 'nome_completo';
                    $placeholders[] = '?';
                    $vals[] = ($nome_completo_val !== '') ? $nome_completo_val : $nome;
                }

                if ($has_criado_em) {
                    $colsIns[] = 'criado_em';
                    $placeholders[] = 'NOW()'; // sem binding
                    // quando usamos NOW() diretamente, não adicionamos valor no $vals
                    // mas precisamos ajustar a query para não usar placeholder para esse item
                    // portanto construiremos a SQL abaixo com atenção
                    $use_now = true;
                } else {
                    $use_now = false;
                }

                // construir SQL corretamente suportando NOW()
                if ($use_now) {
                    // separar placeholders sem o NOW()
                    $colsForSQL = implode(',', $colsIns);
                    // montar parte de VALUES: usar ? para cada valor em $vals e adicionar NOW() no final
                    $valuesPart = implode(',', array_map(function($v){ return '?'; }, array_slice($placeholders, 0, count($vals))));
                    // se ultimo placeholder foi NOW() já removido, concatenar ', NOW()'
                    if (end($placeholders) === 'NOW()') {
                        $valuesPart .= ', NOW()';
                    }
                    $sql = "INSERT INTO usuarios ({$colsForSQL}) VALUES ({$valuesPart})";
                    $ins = $pdo->prepare($sql);
                    $ok = $ins->execute($vals);
                } else {
                    $colsForSQL = implode(',', $colsIns);
                    $valsPlace = implode(',', $placeholders);
                    $sql = "INSERT INTO usuarios ({$colsForSQL}) VALUES ({$valsPlace})";
                    $ins = $pdo->prepare($sql);
                    $ok = $ins->execute($vals);
                }

                if ($ok) {
                    $newId = $pdo->lastInsertId();
                    // Se o usuário logado é admin e estava criando, redireciona à lista de usuários
                    if (is_logged_in() && (strtolower($_SESSION['usuario_papel'] ?? '') === 'admin' || strtolower($_SESSION['usuario_papel'] ?? '') === 'administrador')) {
                        header('Location: usuarios/usuarios.php');
                        exit;
                    } else {
                        // caso auto-registro, redireciona ao login
                        header('Location: login.php');
                        exit;
                    }
                } else {
                    $err = $ins->errorInfo();
                    $erro = 'Falha ao inserir usuário. ' . ($err[2] ?? '');
                }
            }
        } catch (Exception $e) {
            $erro = 'Erro: ' . $e->getMessage();
        }
    }
}
?>

<h2>Cadastrar Usuário</h2>

<?php if ($erro): ?>
  <div style="color:red; margin-bottom:12px;"><?= e($erro) ?></div>
<?php endif; ?>

<form method="post" action="register.php" style="max-width:640px">
  <?php if ($has_nome_completo): ?>
    <label>Nome completo:</label><br>
    <input name="nome_completo" type="text" value="<?= e($_POST['nome_completo'] ?? '') ?>" style="width:100%;padding:8px;margin:6px 0"><br>
  <?php endif; ?>

  <label>Nome (usado para login/exibição):</label><br>
  <input name="nome" type="text" value="<?= e($_POST['nome'] ?? '') ?>" style="width:100%;padding:8px;margin:6px 0"><br>

  <label>Email:</label><br>
  <input name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" style="width:100%;padding:8px;margin:6px 0"><br>

  <label>Senha:</label><br>
  <input name="senha" type="password" style="width:100%;padding:8px;margin:6px 0"><br>

  <label>Papel:</label><br>
  <select name="papel" style="padding:8px;margin:6px 0">
    <option value="Atendente" <?= (($_POST['papel'] ?? '') === 'Atendente') ? 'selected' : '' ?>>Atendente</option>
    <option value="Farmaceutico" <?= (($_POST['papel'] ?? '') === 'Farmaceutico') ? 'selected' : '' ?>>Farmaceutico</option>
    <option value="admin" <?= (($_POST['papel'] ?? '') === 'admin') ? 'selected' : '' ?>>Administrador</option>
  </select><br><br>

  <button type="submit" class="btn" style="padding:10px 16px">Cadastrar</button>
</form>

<?php require_once __DIR__ . '/footer.php'; ?>
