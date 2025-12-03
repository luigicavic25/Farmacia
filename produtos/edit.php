<?php
require_once __DIR__ . '/../functions.php';
require_login();
require_once __DIR__ . '/../header.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM lotes WHERE id = ?");
$stmt->execute([$id]);
$lote = $stmt->fetch();
if (!$lote) { header('Location: index.php'); exit; }

$produtos = $pdo->query("SELECT id, nome FROM produtos ORDER BY nome")->fetchAll();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produto_id = intval($_POST['produto_id'] ?? 0);
    $num_lote = trim($_POST['num_lote'] ?? '');
    $data_validade = $_POST['data_validade'] ?? null;
    $quantidade = intval($_POST['quantidade'] ?? 0);
    $fornecedor = trim($_POST['fornecedor'] ?? '');

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE lotes SET produto_id=?, num_lote=?, data_validade=?, quantidade=?, fornecedor=? WHERE id=?");
        $stmt->execute([$produto_id, $num_lote, $data_validade, $quantidade, $fornecedor, $id]);
        header('Location: index.php');
        exit;
    }
}
?>
<h2>Editar Lote</h2>
<form method="post">
  Produto:<br>
  <select name="produto_id">
    <?php foreach($produtos as $p): ?>
      <option value="<?= $p['id'] ?>" <?= $p['id']==$lote['produto_id'] ? 'selected' : '' ?>><?= e($p['nome']) ?></option>
    <?php endforeach; ?>
  </select><br><br>

  Número do lote:<br><input name="num_lote" value="<?= e($lote['num_lote']) ?>"><br><br>
  Validade:<br><input type="date" name="data_validade" value="<?= e($lote['data_validade']) ?>"><br><br>
  Quantidade:<br><input type="number" name="quantidade" value="<?= e($lote['quantidade']) ?>" min="0"><br><br>
  Fornecedor:<br><input name="fornecedor" value="<?= e($lote['fornecedor']) ?>"><br><br>

  <button type="submit">Atualizar</button>
</form>
<?php require_once __DIR__ . '/../footer.php'; ?>
