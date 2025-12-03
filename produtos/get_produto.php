<?php
// produtos/get_produto.php
require_once __DIR__ . '/../functions.php'; // ajusta caminho se necessário
header('Content-Type: application/json; charset=utf-8');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['error' => 'id inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nome_produto, tipo_produto, descricao_produto FROM cadastro_produto WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['error' => 'produto não encontrado']);
        exit;
    }
    echo json_encode(['ok' => true, 'produto' => $row]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}
