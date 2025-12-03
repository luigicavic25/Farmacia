<?php
// Farmacia/produtos/lotes/lotes.php
// Lista os lotes e mostra informações do produto (id, nome, tipo, descricao)

require_once __DIR__ . '/../../functions.php';
require_login();
require_once __DIR__ . '/../../header.php';

$errors = [];

// Detecta colunas em cadastro_produto para compatibilidade
try {
    $cols = $pdo->query("SHOW COLUMNS FROM cadastro_produto")->fetchAll(PDO::FETCH_ASSOC);
    $cp_fields = array_column($cols, 'Field');

    if (in_array('id_produto', $cp_fields)) $cp_id_col = 'id_produto';
    elseif (in_array('id', $cp_fields)) $cp_id_col = 'id';
    else $cp_id_col = $cp_fields[0];

    if (in_array('nome_produto', $cp_fields)) $cp_nome_col = 'nome_produto';
    elseif (in_array('nome', $cp_fields)) $cp_nome_col = 'nome';
    else $cp_nome_col = $cp_fields[1] ?? $cp_fields[0];

    $cp_tipo_col = in_array('tipo_produto', $cp_fields) ? 'tipo_produto' : (in_array('tipo', $cp_fields) ? 'tipo' : null);
    $cp_desc_col = in_array('descricao_produto', $cp_fields) ? 'descricao_produto' : (in_array('descricao', $cp_fields) ? 'descricao' : null);
} catch (Exception $e) {
    $errors[] = 'Erro lendo cadastro_produto: ' . $e->getMessage();
    // fallback
    $cp_id_col = 'id';
    $cp_nome_col = 'nome';
    $cp_tipo_col = null;
    $cp_desc_col = null;
}

// Detecta colunas em lotes (nome das colunas que vamos usar)
try {
    $colsL = $pdo->query("SHOW COLUMNS FROM lotes")->fetchAll(PDO::FETCH_ASSOC);
    $l_fields = array_column($colsL, 'Field');

    $l_id_col = in_array('id', $l_fields) ? 'id' : $l_fields[0];
    // produto_id or produto or id_produto
    if (in_array('produto_id', $l_fields)) $l_prod_fk = 'produto_id';
    elseif (in_array('id_produto', $l_fields)) $l_prod_fk = 'id_produto';
    elseif (in_array('produto', $l_fields)) $l_prod_fk = 'produto';
    else $l_prod_fk = 'produto_id';

    $l_num_lote = in_array('num_lote', $l_fields) ? 'num_lote' : (in_array('lote', $l_fields) ? 'lote' : null);
    $l_validade = in_array('data_validade', $l_fields) ? 'data_validade' : (in_array('validade', $l_fields) ? 'validade' : null);
    $l_qtd = in_array('qtd_entrega', $l_fields) ? 'qtd_entrega' : (in_array('quantidade', $l_fields) ? 'quantidade' : (in_array('qtd', $l_fields) ? 'qtd' : null));
    $l_fornecedor = in_array('fornecedor', $l_fields) ? 'fornecedor' : null;
} catch (Exception $e) {
    $errors[] = 'Erro lendo lotes: ' . $e->getMessage();
    // defaults
    $l_id_col = 'id';
    $l_prod_fk = 'produto_id';
    $l_num_lote = 'num_lote';
    $l_validade = 'data_validade';
    $l_qtd = 'qtd_entrega';
    $l_fornecedor = 'fornecedor';
}

// Monta a query que traz lotes + dados do produto via JOIN
try {
    $select = [
        "l.{$l_id_col} AS lote_id",
        "l.{$l_prod_fk} AS produto_id",
    ];

    if ($l_num_lote) $select[] = "l.{$l_num_lote} AS num_lote";
    if ($l_validade) $select[] = "l.{$l_validade} AS data_validade";
    if ($l_qtd) $select[] = "l.{$l_qtd} AS quantidade";
    if ($l_fornecedor) $select[] = "l.{$l_fornecedor} AS fornecedor";

    // campos do cadastro_produto
    $select[] = "cp.{$cp_nome_col} AS nome_produto";
    $select[] = $cp_tipo_col ? "cp.{$cp_tipo_col} AS tipo_produto" : "NULL AS tipo_produto";
    $select[] = $cp_desc_col ? "cp.{$cp_desc_col} AS descricao_produto" : "NULL AS descricao_produto";

    $sql = "SELECT " . implode(', ', $select) . "\nFROM lotes l\nLEFT JOIN cadastro_produto cp ON l.{$l_prod_fk} = cp.{$cp_id_col}\nORDER BY l.{$l_validade} IS NULL, l.{$l_validade} ASC, l.{$l_id_col} DESC";

    $stmt = $pdo->query($sql);
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lotes = [];
    $errors[] = 'Erro ao buscar lotes: ' . $e->getMessage();
}
?>

<h2>Lotes — Detalhes por Produto</h2>

<p>
  <a href="/Farmacia/produtos/index.php">Voltar Produtos</a> |
  <a href="/Farmacia/movimentos/entrada.php">Registrar Entrada</a> |
  <a href="/Farmacia/movimentos/saida.php">Registrar Saída</a>
</p>

<?php if (!empty($errors)): ?>
  <div style="color:red;margin-bottom:12px">
    <?php foreach($errors as $er) echo '<div>'.e($er).'</div>'; ?>
  </div>
<?php endif; ?>

<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse; width:100%; max-width:1200px">
  <thead style="background:#f5f5f5">
    <tr>
      <th>ID Lote</th>
      <th>ID Produto</th>
      <th>Nome do Produto</th>
      <th>Tipo</th>
      <th>Nº do Lote</th>
      <th>Validade</th>
      <th style="text-align:right">Quantidade</th>
      <th>Fornecedor</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($lotes)): ?>
      <tr><td colspan="10" style="text-align:center;color:#666;padding:18px">Nenhum lote encontrado.</td></tr>
    <?php else: ?>
      <?php foreach($lotes as $lt): ?>
        <tr>
          <td><?= e($lt['lote_id']) ?></td>
          <td><?= e($lt['produto_id']) ?></td>
          <td><?= e($lt['nome_produto']) ?></td>
          <td><?= e($lt['tipo_produto'] ?? '') ?></td>
          <td><?= e($lt['num_lote'] ?? '') ?></td>
          <td><?= e($lt['data_validade'] ?? '') ?></td>
          <td style="text-align:right"><?= number_format((int)($lt['quantidade'] ?? 0),0,',','.') ?></td>
          <td><?= e($lt['fornecedor'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

<?php require_once __DIR__ . '/../../footer.php'; ?>
