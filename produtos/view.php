<?php
require_once __DIR__ . '/../functions.php';
require_login();
require_once __DIR__ . '/../header.php';

$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT l.*, p.nome as produto_nome FROM lotes l JOIN produtos p ON p.id=l.produto_id WHERE l.id = ?");
$stmt->execute([$id]);
$l = $stmt->fetch();
if (!$l) { echo "<p>Lote não encontrado</p>"; require_once __DIR__ . '/../footer.php'; exit; }
?>
<h2>Lote <?= e($l['num_lote']) ?> — <?= e($l['produto_nome']) ?></h2>
<p>Validade: <?= e($l['data_validade']) ?></p>
<p>Quantidade: <?= e($l['quantidade']) ?></p>
<p>Fornecedor: <?= e($l['fornecedor']) ?></p>
<p><a href="index.php">Voltar</a></p>
<?php require_once __DIR__ . '/../footer.php'; ?>
