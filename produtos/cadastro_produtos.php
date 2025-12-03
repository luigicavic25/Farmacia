<?php
// Farmacia/produtos/cadastro_produtos.php
// Formulário de cadastro/edição de produtos (tabela cadastro_produto)

require_once __DIR__ . '/../functions.php';
require_login();
require_once __DIR__ . '/../header.php';

// Tipos de produto padrão
$tipos_produto = [
    'Medicamento',
    'Higiene Pessoal',
    'Cosmético',
    'Suplementos e Alimentos',
    'Cuidados infantis'
];

$errors = [];
$success = '';

// Edit mode: ?id=123
$id = intval($_GET['id'] ?? 0);
$produto = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM cadastro_produto WHERE id_produto = ? LIMIT 1");
    $stmt->execute([$id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Campos com fallback (mantêm valores após post quando há erro)
$nome_produto = $_POST['nome_produto'] ?? ($produto['nome_produto'] ?? '');
$descricao_produto = $_POST['descricao_produto'] ?? ($produto['descricao_produto'] ?? '');
$tipo_produto = $_POST['tipo_produto'] ?? ($produto['tipo_produto'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // recebe e valida
    $nome_produto = trim($_POST['nome_produto'] ?? '');
    $descricao_produto = trim($_POST['descricao_produto'] ?? '');
    $tipo_produto = trim($_POST['tipo_produto'] ?? '');

    if ($nome_produto === '') $errors[] = 'O nome do produto é obrigatório.';
    if ($tipo_produto !== '' && !in_array($tipo_produto, $tipos_produto)) $errors[] = 'Tipo de produto inválido.';

    if (empty($errors)) {
        try {
            if ($id > 0) {
                // update
                $up = $pdo->prepare("UPDATE cadastro_produto SET nome_produto = ?, descricao_produto = ?, tipo_produto = ? WHERE id_produto = ?");
                $up->execute([$nome_produto, $descricao_produto ?: null, $tipo_produto ?: null, $id]);
                $success = 'Produto atualizado com sucesso.';
            } else {
                // insert
                $ins = $pdo->prepare("INSERT INTO cadastro_produto (nome_produto, descricao_produto, tipo_produto) VALUES (?,?,?)");
                $ins->execute([$nome_produto, $descricao_produto ?: null, $tipo_produto ?: null]);
                $id = $pdo->lastInsertId();
                $success = 'Produto cadastrado com sucesso.';
            }

            // redireciona para lista de produtos (pasta produtos/index.php)
            header('Location: index.php');
            exit;

        } catch (PDOException $e) {
            $errors[] = 'Erro ao salvar no banco: ' . $e->getMessage();
        }
    }
}
?>

<h2><?= $id > 0 ? 'Editar Produto' : 'Cadastrar Produto' ?></h2>

<?php if ($errors): ?>
  <div style="color:red; margin-bottom:12px">
    <?php foreach($errors as $er) echo '<div>'.e($er).'</div>'; ?>
  </div>
<?php endif; ?>

<form method="post" style="max-width:720px">
  <label>Nome do Produto</label><br>
  <input type="text" name="nome_produto" required value="<?= e($nome_produto) ?>" style="width:100%;padding:8px"><br><br>

  <label>Descrição</label><br>
  <textarea name="descricao_produto" style="width:100%;padding:8px;height:90px"><?= e($descricao_produto) ?></textarea><br><br>

  <label>Tipo de Produto</label><br>
  <select name="tipo_produto" style="padding:8px">
    <option value="">-- selecione --</option>
    <?php foreach($tipos_produto as $t): ?>
      <option value="<?= e($t) ?>" <?= $tipo_produto === $t ? 'selected' : '' ?>><?= e($t) ?></option>
    <?php endforeach; ?>
  </select><br><br>

  <button type="submit" class="btn"><?= $id > 0 ? 'Salvar alterações' : 'Cadastrar produto' ?></button>
  <a href="index.php" style="margin-left:12px">Voltar</a>
</form>

<?php require_once __DIR__ . '/../footer.php'; ?>
