<?php
// Farmacia/usuarios/usuarios.php
// Lista de usuários — exibe a tabela `usuarios` do banco

require_once __DIR__ . '/../functions.php';
require_login();
require_once __DIR__ . '/../header.php';

$errors = [];

// pegar esquema da tabela (colunas reais)
try {
    $cols = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
    $schema_fields = array_column($cols, 'Field');
} catch (Exception $e) {
    $schema_fields = [];
    $errors[] = 'Erro ao ler esquema de usuarios: ' . $e->getMessage();
}

// construir lista de colunas para exibir
$u_fields = $schema_fields;

// remover colunas sensíveis
$u_fields = array_values(array_filter($u_fields, function($c){
    $lc = strtolower($c);
    return $lc !== 'senha' && $lc !== 'password' && $lc !== 'senha_hash';
}));

// fallback
if (empty($u_fields)) {
    $u_fields = ['id','nome','email','papel'];
}

// garantir existência de coluna de papel
$possible_role_names = ['papel','role','usuario_papel','user_role','user_papel','funcao'];
$has_role_col = false;
foreach ($possible_role_names as $r) {
    if (in_array($r, $u_fields)) { 
        $has_role_col = true; 
        break; 
    }
}
if (!$has_role_col) {
    foreach ($possible_role_names as $r) {
        if (in_array($r, $schema_fields)) {
            $u_fields[] = $r;
            break;
        }
    }
}

// buscar registros
try {
    $stmt = $pdo->query("SELECT * FROM usuarios ORDER BY id DESC");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $usuarios = [];
    $errors[] = 'Erro ao buscar usuários: ' . $e->getMessage();
}

// cabeçalhos legíveis
function pretty_header($col) {
    $map = [
        'nome_completo' => 'Nome completo',
        'nome' => 'Nome',
        'email' => 'Email',
        'papel' => 'Papel',
        'role' => 'Papel',
        'id' => 'ID',
        'criado_em' => 'Criado em'
    ];
    $lc = strtolower($col);
    return $map[$lc] ?? ucwords(str_replace(['_','-'], [' ',' '], $col));
}
?>

<h2>Usuários</h2>

<p>
  <a href="/Farmacia/produtos/index.php">Produtos</a> |
  <a href="/Farmacia/movimentos/historico_mov.php">Histórico de Movimentos</a> |
  <a href="/Farmacia/register.php">Cadastrar Usuário</a>
</p>

<?php if (!empty($errors)): ?>
  <div style="color:red;margin-bottom:12px">
    <?php foreach($errors as $er) echo '<div>'.e($er).'</div>'; ?>
  </div>
<?php endif; ?>

<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse; width:100%; max-width:1100px">
  <thead style="background:#f5f5f5">
    <tr>
      <?php foreach($u_fields as $col): ?>
        <th><?= e(pretty_header($col)) ?></th>
      <?php endforeach; ?>
      <th>Ações</th>
    </tr>
  </thead>

  <tbody>
    <?php if (empty($usuarios)): ?>
      <tr><td colspan="<?= count($u_fields) + 1 ?>" style="text-align:center;color:#666;padding:18px">Nenhum usuário encontrado.</td></tr>
    <?php else: ?>

      <?php foreach($usuarios as $user): ?>
        <tr>
          <?php foreach($u_fields as $col): ?>
            <?php
              $val = $user[$col] ?? '';
              $lc = strtolower($col);

              // papel vazio → substitui por "—"
              if (in_array($lc, ['papel','role','usuario_papel','user_role','user_papel','funcao']) && trim((string)$val)==='') {
                  $val = '—';
              }
            ?>
            <td><?= e($val) ?></td>
          <?php endforeach; ?>

          <td style="text-align:center">
            <a href="/Farmacia/usuarios/edit.php?id=<?= e($user['id']) ?>">Editar</a>
            |
            <form action="/Farmacia/usuarios/delete.php" method="post" style="display:inline" onsubmit="return confirm('Excluir usuário?');">
              <input type="hidden" name="id" value="<?= e($user['id']) ?>">
              <button type="submit">Excluir</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>

    <?php endif; ?>
  </tbody>

</table>

<?php require_once __DIR__ . '/../footer.php'; ?>
