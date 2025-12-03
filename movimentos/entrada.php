<?php
// movimentos/entrada.php  (Opção B: totals by SUM(lotes), não altera produtos.qtd_total)
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../functions.php';
require_login();
require_once __DIR__ . '/../header.php';

$errors = [];

// ---------- detecta colunas em cadastro_produto (id / nome / tipo / descricao) ----------
try {
    $cols = $pdo->query("SHOW COLUMNS FROM cadastro_produto")->fetchAll(PDO::FETCH_ASSOC);
    $fields = array_column($cols, 'Field');

    // id da tabela cadastro_produto (aceita id_produto ou id)
    if (in_array('id_produto', $fields)) $cp_id_col = 'id_produto';
    elseif (in_array('id', $fields)) $cp_id_col = 'id';
    else $cp_id_col = $fields[0];

    // nome do produto
    if (in_array('nome_produto', $fields)) $cp_nome_col = 'nome_produto';
    elseif (in_array('nome', $fields)) $cp_nome_col = 'nome';
    else $cp_nome_col = $fields[1] ?? $fields[0];

    // tipo e descricao (se existirem)
    $cp_tipo_col = in_array('tipo_produto', $fields) ? 'tipo_produto' : (in_array('tipo', $fields) ? 'tipo' : null);
    $cp_desc_col = in_array('descricao_produto', $fields) ? 'descricao_produto' : (in_array('descricao', $fields) ? 'descricao' : null);

} catch (Exception $e) {
    $errors[] = "Erro lendo cadastro_produto: " . $e->getMessage();
    // define defaults para continuar (poderá haver outros erros)
    $cp_id_col = 'id';
    $cp_nome_col = 'nome';
    $cp_tipo_col = null;
    $cp_desc_col = null;
}

// monta lista de produtos para select (alias padronizado cp_id,nome_produto,...)
try {
    $selectCols = "{$cp_id_col} AS cp_id, {$cp_nome_col} AS nome_produto";
    if ($cp_tipo_col) $selectCols .= ", {$cp_tipo_col} AS tipo_produto";
    if ($cp_desc_col) $selectCols .= ", {$cp_desc_col} AS descricao_produto";
    $produtos = $pdo->query("SELECT {$selectCols} FROM cadastro_produto ORDER BY {$cp_nome_col}")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $produtos = [];
    $errors[] = "Erro buscando produtos: " . $e->getMessage();
}

// Tipos padrão (UI)
$tipos_produto = [
    'Medicamento',
    'Higiene Pessoal',
    'Cosmético',
    'Suplementos e Alimentos',
    'Cuidados infantis'
];

// ---------- PROCESSAMENTO DO FORM ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // campos do formulário
    // note: o campo de ID do produto envia para 'id_produto' no POST (valor numérico)
    $id_post = intval($_POST['id_produto'] ?? 0);
    $nome_produto = trim($_POST['nome_produto'] ?? '');
    $descricao_produto = trim($_POST['descricao_produto'] ?? '');
    $tipo_produto = trim($_POST['tipo_produto'] ?? '');

    $quantidade = intval($_POST['quantidade'] ?? 0);
    $data_entrada = trim($_POST['data_entrada'] ?? '');
    $data_validade = trim($_POST['data_validade'] ?? '');
    $num_lote = trim($_POST['num_lote'] ?? '');
    $tipo_estoque = trim($_POST['tipo_estoque'] ?? '');
    $fornecedor = trim($_POST['fornecedor'] ?? '');

    // validações
    if ($id_post <= 0 && $nome_produto === '') $errors[] = "Informe o ID do produto ou preencha o nome para cadastrar novo.";
    if ($quantidade <= 0) $errors[] = "Quantidade deve ser maior que zero.";
    if ($tipo_produto && !in_array($tipo_produto, $tipos_produto)) $errors[] = "Tipo de produto inválido.";

    // datas
    $data_entrada_dt = $data_entrada ? date('Y-m-d H:i:s', strtotime($data_entrada)) : date('Y-m-d H:i:s');
    $data_validade_dt = $data_validade ? date('Y-m-d', strtotime($data_validade)) : null;

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1) criar ou obter produto em cadastro_produto
            if ($id_post > 0) {
                // confirmar existencia
                $st = $pdo->prepare("SELECT {$cp_id_col} AS id_cad, {$cp_nome_col} AS nome_prod FROM cadastro_produto WHERE {$cp_id_col} = ? LIMIT 1");
                $st->execute([$id_post]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new Exception("Produto com ID {$id_post} não encontrado.");
                $id_cadastro = (int)$row['id_cad'];
            } else {
                // inserir novo cadastro_produto (monta colunas existentes)
                $colsIns = [];
                $ph = [];
                $vals = [];

                // nome
                $colsIns[] = $cp_nome_col; $ph[] = '?'; $vals[] = $nome_produto;
                // tipo
                if ($cp_tipo_col) { $colsIns[] = $cp_tipo_col; $ph[]='?'; $vals[] = $tipo_produto ?: null; }
                // descricao
                if ($cp_desc_col) { $colsIns[] = $cp_desc_col; $ph[]='?'; $vals[] = $descricao_produto ?: null; }

                $sqlIns = "INSERT INTO cadastro_produto (" . implode(',', $colsIns) . ") VALUES (" . implode(',', $ph) . ")";
                $insSt = $pdo->prepare($sqlIns);
                $insSt->execute($vals);
                $id_cadastro = (int)$pdo->lastInsertId();
            }

            // 2) Lotes: criar ou atualizar (somar qtd_entrega se mesmo num_lote)
            if ($num_lote !== '') {
                // tenta achar lote com produto_id = cadastro id e num_lote
                $stl = $pdo->prepare("SELECT id, qtd_entrega FROM lotes WHERE produto_id = ? AND num_lote = ? FOR UPDATE");
                $stl->execute([$id_cadastro, $num_lote]);
                $loteRow = $stl->fetch(PDO::FETCH_ASSOC);

                if ($loteRow) {
                    $upd = $pdo->prepare("UPDATE lotes SET qtd_entrega = qtd_entrega + ?, fornecedor = ?, data_entrada = ? WHERE id = ?");
                    $upd->execute([$quantidade, $fornecedor ?: null, $data_entrada_dt, $loteRow['id']]);
                    $id_lote = (int)$loteRow['id'];
                } else {
                    $insL = $pdo->prepare("INSERT INTO lotes (produto_id, num_lote, data_entrada, data_validade, qtd_entrega, fornecedor) VALUES (?,?,?,?,?,?)");
                    $insL->execute([$id_cadastro, $num_lote, $data_entrada_dt, $data_validade_dt, $quantidade, $fornecedor ?: null]);
                    $id_lote = (int)$pdo->lastInsertId();
                }
            } else {
                // sem num_lote informado: cria um lote automático
                $generated = 'L'.date('YmdHis').'_p'.$id_cadastro;
                $insL = $pdo->prepare("INSERT INTO lotes (produto_id, num_lote, data_entrada, data_validade, qtd_entrega, fornecedor) VALUES (?,?,?,?,?,?)");
                $insL->execute([$id_cadastro, $generated, $data_entrada_dt, $data_validade_dt, $quantidade, $fornecedor ?: null]);
                $id_lote = (int)$pdo->lastInsertId();
            }

            // 3) NÃO alteramos a tabela produtos.qtd_total (Opção B).
            // Se você quiser manter vínculo de tipo_estoque em produtos, podemos inserir/atualizar a linha
            // sem tocar quantidade; abaixo está um trecho opcional (comentado).
            /*
            $stProd = $pdo->prepare("SELECT id FROM produtos WHERE id_produto = ? LIMIT 1");
            $stProd->execute([$id_cadastro]);
            if (!$stProd->fetch()) {
                $insProd = $pdo->prepare("INSERT INTO produtos (id_produto, tipo_estoque) VALUES (?,?)");
                $insProd->execute([$id_cadastro, $tipo_estoque ?: null]);
            } else {
                $updProd = $pdo->prepare("UPDATE produtos SET tipo_estoque = ? WHERE id_produto = ?");
                $updProd->execute([$tipo_estoque ?: null, $id_cadastro]);
            }
            */

            // 4) registrar movimento em movimentos
            // assume campos: tipo, id_produto, id_lote, qtd, id_usuario, descricao, data_mov
            $insM = $pdo->prepare("INSERT INTO movimentos (tipo, id_produto, id_lote, qtd, id_usuario, descricao, data_mov) VALUES ('entrada', ?, ?, ?, ?, ?, ?)");
            $insM->execute([
                $id_cadastro,
                $id_lote,
                $quantidade,
                $_SESSION['usuario_id'] ?? null,
                "Entrada por formulário",
                $data_entrada_dt
            ]);

            $pdo->commit();

            // redireciona para a listagem de produtos (você pediu para não tocar index.php agora)
            header('Location: ../produtos/index.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Erro ao processar entrada: ' . $e->getMessage();
        }
    }
}
?>

<h2>Registrar Entrada</h2>

<?php if (!empty($errors)): ?>
  <div style="color:red;margin-bottom:12px">
    <?php foreach($errors as $er) echo '<div>'.e($er).'</div>'; ?>
  </div>
<?php endif; ?>

<form method="post" style="max-width:750px;">

  <label>Informe o ID do produto (ou use o select):</label><br>
  <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
    <input id="produto_id_input" name="id_produto" type="number" min="0" placeholder="Ex: 4" value="<?= e($_POST['id_produto'] ?? '') ?>" style="padding:8px;width:120px;">
    <button type="button" id="btnFetchProduto" class="btn" style="padding:8px">Buscar</button>
    <span style="color:#666">ou</span>
    <select id="produto_select" style="flex:1;padding:8px">
      <option value="0">-- Selecionar produto --</option>
      <?php foreach($produtos as $p): ?>
        <option value="<?= e($p['cp_id']) ?>" <?= (isset($_POST['id_produto']) && $_POST['id_produto']==$p['cp_id'])?'selected':'' ?>>
          <?= e($p['cp_id']) ?> — <?= e($p['nome_produto']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <label>Nome do produto (preenchido automaticamente):</label><br>
  <input id="nome_produto" type="text" name="nome_produto" value="<?= e($_POST['nome_produto'] ?? '') ?>" style="width:100%;padding:8px"><br><br>

  <label>Tipo do produto (preenchido automaticamente):</label><br>
  <input id="tipo_produto" type="text" name="tipo_produto" value="<?= e($_POST['tipo_produto'] ?? '') ?>" style="width:50%;padding:8px"><br><br>

  <label>Descrição (preenchida automaticamente):</label><br>
  <textarea id="descricao_produto" name="descricao_produto" style="width:100%;padding:8px;height:80px"><?= e($_POST['descricao_produto'] ?? '') ?></textarea><br><br>

  <label>Quantidade:</label><br>
  <input type="number" name="quantidade" min="1" required style="padding:8px;width:140px" value="<?= e($_POST['quantidade'] ?? 1) ?>"><br><br>

  <label>Data de entrada:</label><br>
  <input type="date" name="data_entrada" style="padding:8px" value="<?= e($_POST['data_entrada'] ?? '') ?>"><br><br>

  <label>Data de validade (opcional):</label><br>
  <input type="date" name="data_validade" style="padding:8px" value="<?= e($_POST['data_validade'] ?? '') ?>"><br><br>

  <label>Número do lote (se já existir, será atualizado):</label><br>
  <input type="text" name="num_lote" style="padding:8px;width:250px" value="<?= e($_POST['num_lote'] ?? '') ?>"><br><br>

  <label>Tipo de estoque:</label><br>
  <input type="text" name="tipo_estoque" style="padding:8px;width:300px" placeholder="Ex: Almoxarifado" value="<?= e($_POST['tipo_estoque'] ?? '') ?>"><br><br>

  <label>Fornecedor:</label><br>
  <input type="text" name="fornecedor" style="padding:8px;width:300px" value="<?= e($_POST['fornecedor'] ?? '') ?>"><br><br>

  <button type="submit" class="btn">Registrar Entrada</button>
</form>

<!-- JS AJAX para buscar produto por ID (usa get_produto.php) -->
<script>
async function fetchProdutoAJAX(id) {
  if (!id || id <= 0) return null;
  try {
    const res = await fetch('/Farmacia/produtos/get_produto.php?id=' + encodeURIComponent(id));
    const data = await res.json();
    if (!data || data.error) return { error: data && data.error ? data.error : 'Erro' };
    return data.produto;
  } catch (err) {
    return { error: err.message || 'Erro de rede' };
  }
}

function fillFields(produto) {
  if (!produto) {
    document.getElementById('nome_produto').value = '';
    document.getElementById('tipo_produto').value = '';
    document.getElementById('descricao_produto').value = '';
    return;
  }
  document.getElementById('nome_produto').value = produto.nome_produto ?? produto.nome ?? '';
  document.getElementById('tipo_produto').value = produto.tipo_produto ?? produto.tipo ?? '';
  document.getElementById('descricao_produto').value = produto.descricao_produto ?? produto.descricao ?? '';
  document.getElementById('produto_id_input').value = produto.id_produto ?? produto.id ?? '';
  const sel = document.getElementById('produto_select'); if (sel) sel.value = produto.id_produto ?? produto.id ?? '0';
}

document.getElementById('btnFetchProduto').addEventListener('click', async () => {
  const id = document.getElementById('produto_id_input').value;
  if (!id) return alert('Digite um ID');
  const produto = await fetchProdutoAJAX(id);
  if (produto && produto.error) { alert('Erro: ' + produto.error); return; }
  fillFields(produto);
});

document.getElementById('produto_select').addEventListener('change', async (e) => {
  const id = e.target.value;
  if (!id || id == '0') { fillFields(null); return; }
  const produto = await fetchProdutoAJAX(id);
  if (produto && produto.error) { alert('Erro: ' + produto.error); return; }
  fillFields(produto);
});

document.getElementById('produto_id_input').addEventListener('keydown', function(e){
  if (e.key === 'Enter') { e.preventDefault(); document.getElementById('btnFetchProduto').click(); }
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
