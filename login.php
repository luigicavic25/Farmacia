<?php
// login.php - página de login
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// inclui funções e inicia sessão
require_once __DIR__ . '/functions.php';

// se já está logado, envia para index
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$erro = '';
$email_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    // mantém o email digitado
    $email_value = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    if ($email === '' || $senha === '') {
        $erro = 'Preencha email e senha.';
    } else {
        // procura usuário
        $stmt = $pdo->prepare("SELECT id, nome, senha, papel FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha'])) {

            session_regenerate_id(true);

            $_SESSION['usuario_id']   = $user['id'];
            $_SESSION['usuario_nome'] = $user['nome'];
            $_SESSION['usuario_papel'] = $user['papel'];

            header('Location: index.php');
            exit;
        } else {
            $erro = 'Email ou senha incorretos.';
        }
    }
}

// inclui header
require_once __DIR__ . '/header.php';
?>

<h2>Login</h2>

<?php if (!empty($erro)): ?>
  <div class="flash" style="background:#ffdede;border:1px solid #ffb3b3;color:#900;">
    <?= htmlspecialchars($erro, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
  </div>
<?php endif; ?>

<form method="post" action="login.php" style="max-width:420px">
  <label for="email">Email</label><br>
  <input id="email" type="email" name="email" required 
         value="<?= $email_value ?>" 
         style="width:100%;padding:8px;margin:6px 0"><br>

  <label for="senha">Senha</label><br>
  <input id="senha" type="password" name="senha" required 
         style="width:100%;padding:8px;margin:6px 0"><br>

  <button type="submit" class="btn">Entrar</button>
</form>

<!-- Botão para cadastro -->
<form action="register.php" method="get" style="margin-top:15px;">
  <button type="submit" class="btn" 
          style="background:#2e6da4;color:white;padding:8px 14px;">
    Cadastre-se
  </button>
</form>

<p style="margin-top:12px;color:#666">
Caso ainda não tenha usuário admin, gere um hash com <code>gera_hash.php</code>
e insira manualmente via phpMyAdmin.
</p>

<?php
require_once __DIR__ . '/footer.php';
?>
