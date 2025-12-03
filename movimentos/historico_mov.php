<?php
// Farmacia/movimentos/historico_mov.php
// Exibe histórico de movimentos com informações do produto e do usuário

require_once __DIR__ . '/../functions.php';
require_login();
require_once __DIR__ . '/../header.php';

$errors = [];

// Detecta colunas em movimentos
try {
    $colsM = $pdo->query("SHOW COLUMNS FROM movimentos")->fetchAll(PDO::FETCH_ASSOC);
    $m_fields = array_column($colsM, 'Field');

    // Identifica colunas importantes na tabela movimentos
    $m_id_col = in_array('id', $m_fields) ? 'id' : $m_fields[0];
    $m_tipo_col = in_array('tipo', $m_fields) ? 'tipo' : (in_array('movement_type', $m_fields) ? 'movement_type' : null);

    // fk produto
    if (in_array('id_produto', $m_fields)) $m_prod_fk = 'id_produto';
    elseif (in_array('produto_id', $m_fields)) $m_prod_fk = 'produto_id';
    elseif (in_array('idproduto', $m_fields)) $m_prod_fk = 'idproduto';
    else $m_prod_fk = null; // pode ser nulo se a coluna tiver nome diferente

    // fk lote
    if (in_array('id_lote', $m_fields)) $m_lote_col = 'id_lote';
    elseif (in_array('lote_id', $m_fields)) $m_lote_col = 'lote_id';
    elseif (in_array('lote', $m_fields)) $m_lote_col = 'lote';
    else $m_lote_col = null;

    // quantidade
    if (in_array('qtd', $m_fields)) $m_qtd_col = 'qtd';
    elseif (in_array('quantidade', $m_fields)) $m_qtd_col = 'quantidade';
    else $m_qtd_col = null;

    // usuario fk
    if (in_array('id_usuario', $m_fields)) $m_user_fk = 'id_usuario';
    elseif (in_array('usuario_id', $m_fields)) $m_user_fk = 'usuario_id';
    elseif (in_array('user_id', $m_fields)) $m_user_fk = 'user_id';
    else $m_user_fk = null;

    // descricao and date
    $m_desc_col = in_array('descricao', $m_fields) ? 'descricao' : (in_array('description', $m_fields) ? 'description' : null);
    $m_date_col = in_array('data_mov', $m_fields) ? 'data_mov' : (in_array('created_at', $m_fields) ? 'created_at' : (in_array('data', $m_fields) ? 'data' : null));

} catch (Exception $e) {
    $errors[] = 'Erro lendo esquema de movimentos: ' . $e->getMessage();
    // definições padrão
    $m_id_col = 'id';
    $m_tipo_col = 'tipo';
    $m_prod_fk = 'id_produto';
    $m_lote_col = 'id_lote';
    $m_qtd_col = 'qtd';
    $m_user_fk = 'id_usuario';
    $m_desc_col = 'descricao';
    $m_date_col = 'data_mov';
}

// Detecta colunas em cadastro_produto
try {
    $colsCp = $pdo->query("SHOW COLUMNS FROM cadastro_produto")->fetchAll(PDO::FETCH_ASSOC);
    $cp_fields = array_column($colsCp, 'Field');
    $cp_id_col = in_array('id_produto', $cp_fields) ? 'id_produto' : (in_array('id', $cp_fields) ? 'id' : $cp_fields[0]);
    $cp_nome_col = in_array('nome_produto', $cp_fields) ? 'nome_produto' : (in_array('nome', $cp_fields) ? 'nome' : $cp_fields[1] ?? $cp_fields[0]);
    $cp_tipo_col = in_array('tipo_produto', $cp_fields) ? 'tipo_produto' : (in_array('tipo', $cp_fields) ? 'tipo' : null);
    $cp_desc_col = in_array('descricao_produto', $cp_fields) ? 'descricao_produto' : (in_array('descricao', $cp_fields) ? 'descricao' : null);
} catch (Exception $e) {
    $errors[] = 'Erro lendo cadastro_produto: ' . $e->getMessage();
    $cp_id_col = 'id';
    $cp_nome_col = 'nome';
    $cp_tipo_col = null;
    $cp_desc_col = null;
}

// Detecta colunas em usuarios
try {
    $colsU = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
    $u_fields = array_column($colsU, 'Field');
    $u_id_col = in_array('id', $u_fields) ? 'id' : $u_fields[0];
    // nome do usuário
    if (in_array('nome', $u_fields)) $u_name_col = 'nome';
    elseif (in_array('nome_usuario', $u_fields)) $u_name_col = 'nome_usuario';
    elseif (in_array('username', $u_fields)) $u_name_col = 'username';
    elseif (in_array('user', $u_fields)) $u_name_col = 'user';
    else $u_name_col = $u_fields[1] ?? $u_fields[0];
} catch (Exception $e) {
    // se tabela usuarios não existir ou erro, apenas continue sem nome de usuário
    $u_id_col = 'id';
    $u_name_col = 'nome';
}

// Monta SQL com LEFT JOINs para trazer nome do produto, tipo e nome do usuário
try {
    $select = [];
    $select[] = "m.{$m_id_col} AS movimento_id";
    if ($m_tipo_col) $select[] = "m.{$m_tipo_col} AS tipo_mov";
    if ($m_lote_col) $select[] = "m.{$m_lote_col} AS lote_id";
    if ($m_qtd_col) $select[] = "m.{$m_qtd_col} AS quantidade";
    if ($m_desc_col) $select[] = "m.{$m_desc_col} AS descricao_mov";
    if ($m_date_col) $select[] = "m.{$m_date_col} AS data_mov";

    // campos do produto (alias)
    $select[] = "cp.{$cp_id_col} AS produto_id"; // internal but we will not display id_produto per user request
    $select[] = "cp.{$cp_nome_col} AS nome_produto";
    $select[] = $cp_tipo_col ? "cp.{$cp_tipo_col} AS tipo_produto" : "NULL AS tipo_produto";
    $select[] = $cp_desc_col ? "cp.{$cp_desc_col} AS descricao_produto" : "NULL AS descricao_produto";

    // usuário
    if ($m_user_fk) {
        $select[] = "u.{$u_name_col} AS nome_usuario";
    } else {
        $select[] = "NULL AS nome_usuario";
    }

    $sql = "SELECT " . implode(', ', $select) . "\nFROM movimentos m\n";

    // join produto if fk available
    if ($m_prod_fk) {
        $sql .= "LEFT JOIN cadastro_produto cp ON m.{$m_prod_fk} = cp.{$cp_id_col}\n";
    } else {
        // no fk detected, still LEFT JOIN trying common names
        $sql .= "LEFT JOIN cadastro_produto cp ON cp.{$cp_id_col} = m.id_produto OR cp.{$cp_id_col} = m.produto_id OR cp.{$cp_id_col} = m.produto\n";
    }

    // join usuario if fk available
    if ($m_user_fk) {
        $sql .= "LEFT JOIN usuarios u ON m.{$m_user_fk} = u.{$u_id_col}\n";
    } else {
        $sql .= "LEFT JOIN usuarios u ON u.{$u_id_col} = m.id_usuario OR u.{$u_id_col} = m.usuario_id OR u.{$u_id_col} = m.user_id\n";
    }

    $sql .= "ORDER BY m.{$m_date_col} DESC";

    $stmt = $pdo->query($sql);
    $movs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $movs = [];
    $errors[] = 'Erro ao buscar histórico: ' . $e->getMessage();
}

?>

<h2>Histórico de Movimentos</h2>

<p>
  <a href="/Farmacia/produtos/index.php">Produtos</a> |
  <a href="/Farmacia/produtos/lotes/lotes.php">Lotes</a> |
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
      <th>ID</th>
      <th>Tipo</th>
      <th>Nome do Produto</th>
      <th>Tipo do Produto</th>
      <th>Descrição do Produto</th>
      <th>Lote ID</th>
      <th style="text-align:right">Quantidade</th>
      <th>Usuário</th>
      <th>Descrição</th>
      <th>Data</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($movs)): ?>
      <tr><td colspan="10" style="text-align:center;color:#666;padding:18px">Nenhum movimento encontrado.</td></tr>
    <?php else: ?>
      <?php foreach($movs as $mv): ?>
        <tr>
          <td><?= e($mv['movimento_id']) ?></td>
          <td><?= e($mv['tipo_mov'] ?? $mv['tipo'] ?? '') ?></td>
          <td><?= e($mv['nome_produto'] ?? '') ?></td>
          <td><?= e($mv['tipo_produto'] ?? '') ?></td>
          <td><?= nl2br(e($mv['descricao_produto'] ?? '')) ?></td>
          <td><?= e($mv['lote_id'] ?? '') ?></td>
          <td style="text-align:right"><?= number_format((int)($mv['quantidade'] ?? 0),0,',','.') ?></td>
          <td><?= e($mv['nome_usuario'] ?? '') ?></td>
          <td><?= nl2br(e($mv['descricao_mov'] ?? '')) ?></td>
          <td><?= e($mv['data_mov'] ?? $mv['created_at'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

<?php require_once __DIR__ . '/../footer.php'; ?>
