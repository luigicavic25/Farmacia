<?php
// Farmacia/produtos/index.php
require_once __DIR__ . '/../functions.php';
require_login();
require_once __DIR__ . '/../header.php';

// detectar colunas em cadastro_produto e lotes para compatibilidade
try {
    $cols_cp = $pdo->query("SHOW COLUMNS FROM cadastro_produto")->fetchAll(PDO::FETCH_ASSOC);
    $cp_fields = array_column($cols_cp, 'Field');

    // id em cadastro_produto (id_produto ou id)
    if (in_array('id_produto', $cp_fields)) $cp_id_col = 'id_produto';
    elseif (in_array('id', $cp_fields)) $cp_id_col = 'id';
    else $cp_id_col = $cp_fields[0];

    // nome
    if (in_array('nome_produto', $cp_fields)) $cp_nome_col = 'nome_produto';
    elseif (in_array('nome', $cp_fields)) $cp_nome_col = 'nome';
    else $cp_nome_col = $cp_fields[1] ?? $cp_fields[0];

    // tipo e descricao (opcionais)
    $cp_tipo_col = in_array('tipo_produto', $cp_fields) ? 'tipo_produto' : (in_array('tipo', $cp_fields) ? 'tipo' : null);
    $cp_desc_col = in_array('descricao_produto', $cp_fields) ? 'descricao_produto' : (in_array('descricao', $cp_fields) ? 'descricao' : null);
} catch (Exception $e) {
    // fallback simples se SHOW COLUMNS falhar
    $cp_id_col = 'id';
    $cp_nome_col = 'nome';
    $cp_tipo_col = null;
    $cp_desc_col = null;
}

// detectar coluna de quantidade em lotes (qtd_entrega, quantidade, qtd_total, qtd)
try {
    $cols_l = $pdo->query("SHOW COLUMNS FROM lotes")->fetchAll(PDO::FETCH_ASSOC);
    $l_fields = array_column($cols_l, 'Field');

    if (in_array('qtd_entrega', $l_fields)) $l_qty_col = 'qtd_entrega';
    elseif (in_array('quantidade', $l_fields)) $l_qty_col = 'quantidade';
    elseif (in_array('qtd_total', $l_fields)) $l_qty_col = 'qtd_total';
    elseif (in_array('qtd', $l_fields)) $l_qty_col = 'qtd';
    else $l_qty_col = $l_fields[ array_search('qtd_entrega', $l_fields) !== false ? array_search('qtd_entrega', $l_fields) : 0 ];
} catch (Exception $e) {
    $l_qty_col = 'qtd_entrega'; // suposição
}

// montar SQL que soma lotes por produto (LEFT JOIN para incluir produtos sem lotes)
try {
    // nomes usados no SQL: cp (cadastro_produto) e l (lotes)
    // JOIN ON l.produto_id = cp.<cp_id_col>
    $joinCondition = "l.produto_id = cp.{$cp_id_col}";
    // colunas a selecionar
    $selectCols = "cp.{$cp_id_col} AS produto_id, cp.{$cp_nome_col} AS nome_produto";
    if ($cp_tipo_col) $selectCols .= ", cp.{$cp_tipo_col} AS tipo_produto";
    else $selectCols .= ", NULL AS tipo_produto";
    if ($cp_desc_col) $selectCols .= ", cp.{$cp_desc_col} AS descricao_produto";
    else $selectCols .= ", NULL AS descricao_produto";

    // soma total usando a coluna detectada em lotes
    $sql = "
        SELECT
            {$selectCols},
            COALESCE(SUM(l.{$l_qty_col}), 0) AS qtd_total
        FROM cadastro_produto cp
        LEFT JOIN lotes l ON {$joinCondition}
        GROUP BY cp.{$cp_id_col}
        ORDER BY cp.{$cp_nome_col} ASC
    ";

    $stmt = $pdo->query($sql);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $produtos = [];
    $errorMsg = "Erro ao buscar produtos: " . $e->getMessage();
}

?>

<h2>Produtos — Estoque</h2>

<p>
  <a href="/Farmacia/produtos/index.php">Atualizar Página</a> |
  <a href="/Farmacia/produtos/cadastro_produtos.php">Cadastrar Produto</a> |
  <a href="/Farmacia/produtos/lotes/lotes.php">Ver Lotes</a>
</p>

<?php if (!empty($errorMsg)): ?>
  <div style="color:red; margin-bottom:12px;"><?= e($errorMsg) ?></div>
<?php endif; ?>

<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse; width:100%; max-width:1000px">
  <thead style="background:#f5f5f5">
    <tr>
      <th style="text-align:left">Nome do Produto</th>
      <th style="text-align:left">Tipo</th>
      <th style="text-align:left">Descrição</th>
      <th style="text-align:right">Quantidade Total</th>
      <th style="text-align:center">Ações</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($produtos)): ?>
      <tr><td colspan="5" style="text-align:center;color:#666;padding:18px">Nenhum produto encontrado.</td></tr>
    <?php else: ?>
      <?php foreach ($produtos as $p): ?>
        <tr>
          <td><?= e($p['nome_produto']) ?></td>
          <td><?= e($p['tipo_produto'] ?? '') ?></td>
          <td><?= nl2br(e($p['descricao_produto'] ?? '')) ?></td>
          <td style="text-align:right"><?= number_format((int)($p['qtd_total'] ?? 0), 0, ',', '.') ?></td>
          <td style="text-align:center">
            <a href="/Farmacia/movimentos/saida.php?produto_id=<?= e($p['produto_id']) ?>">Saída</a> |
            <a href="/Farmacia/movimentos/entrada.php?id_produto=<?= e($p['produto_id']) ?>">Entrada</a>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

<?php require_once __DIR__ . '/../footer.php'; ?>
