<?php
// movimentos/saida.php
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../functions.php';
require_login();
require_once __DIR__ . '/../header.php';

$errors = [];

// ---------- detectar colunas em cadastro_produto e lotes (compatibilidade) ----------
try {
    $cols = $pdo->query("SHOW COLUMNS FROM cadastro_produto")->fetchAll(PDO::FETCH_ASSOC);
    $cp_fields = array_column($cols, 'Field');
    $cp_id_col = in_array('id_produto', $cp_fields) ? 'id_produto' : (in_array('id', $cp_fields) ? 'id' : $cp_fields[0]);
    $cp_nome_col = in_array('nome_produto', $cp_fields) ? 'nome_produto' : (in_array('nome', $cp_fields) ? 'nome' : ($cp_fields[1] ?? $cp_fields[0]));
    $cp_tipo_col = in_array('tipo_produto', $cp_fields) ? 'tipo_produto' : (in_array('tipo', $cp_fields) ? 'tipo' : null);
    $cp_desc_col = in_array('descricao_produto', $cp_fields) ? 'descricao_produto' : (in_array('descricao', $cp_fields) ? 'descricao' : null);
} catch (Exception $e) {
    $errors[] = 'Erro lendo cadastro_produto: ' . $e->getMessage();
    $cp_id_col = 'id';
    $cp_nome_col = 'nome';
    $cp_tipo_col = null;
    $cp_desc_col = null;
}

try {
    $colsL = $pdo->query("SHOW COLUMNS FROM lotes")->fetchAll(PDO::FETCH_ASSOC);
    $l_fields = array_column($colsL, 'Field');
    $l_id_col = in_array('id', $l_fields) ? 'id' : $l_fields[0];
    $l_prod_fk = in_array('produto_id', $l_fields) ? 'produto_id' : (in_array('id_produto', $l_fields) ? 'id_produto' : (in_array('produto', $l_fields) ? 'produto' : 'produto_id'));
    $l_num_lote = in_array('num_lote', $l_fields) ? 'num_lote' : (in_array('lote', $l_fields) ? 'lote' : null);
    $l_validade = in_array('data_validade', $l_fields) ? 'data_validade' : (in_array('validade', $l_fields) ? 'validade' : null);
    $l_qtd = in_array('qtd_entrega', $l_fields) ? 'qtd_entrega' : (in_array('quantidade', $l_fields) ? 'quantidade' : (in_array('qtd', $l_fields) ? 'qtd' : null));
    $l_fornecedor = in_array('fornecedor', $l_fields) ? 'fornecedor' : null;
} catch (Exception $e) {
    $errors[] = 'Erro lendo lotes: ' . $e->getMessage();
    $l_id_col = 'id';
    $l_prod_fk = 'produto_id';
    $l_num_lote = 'num_lote';
    $l_validade = 'data_validade';
    $l_qtd = 'qtd_entrega';
    $l_fornecedor = 'fornecedor';
}

// montar lista de produtos para select (alias padronizado)
try {
    $selectCols = "{$cp_id_col} AS cp_id, {$cp_nome_col} AS nome_produto";
    if ($cp_tipo_col) $selectCols .= ", {$cp_tipo_col} AS tipo_produto";
    if ($cp_desc_col) $selectCols .= ", {$cp_desc_col} AS descricao_produto";
    $produtos = $pdo->query("SELECT {$selectCols} FROM cadastro_produto ORDER BY {$cp_nome_col}")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $produtos = [];
    $errors[] = 'Erro buscando produtos: ' . $e->getMessage();
}

// tipos (UI)
$tipos_produto = [
    'Medicamento',
    'Higiene Pessoal',
    'Cosmético',
    'Suplementos e Alimentos',
    'Cuidados infantis'
];

// ---------- processamento do POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_post = intval($_POST['id_produto'] ?? 0);
    $nome_produto = trim($_POST['nome_produto'] ?? '');
    $tipo_produto = trim($_POST['tipo_produto'] ?? '');
    $descricao_produto = trim($_POST['descricao_produto'] ?? '');

    $quantidade = intval($_POST['quantidade'] ?? 0);
    $data_mov = trim($_POST['data_mov'] ?? ''); // data da saída (opcional)
    $num_lote = trim($_POST['num_lote'] ?? ''); // se informado, retirar desse lote
    $motivo = trim($_POST['descricao_mov'] ?? 'Saída de estoque');

    if ($id_post <= 0 && $nome_produto === '') $errors[] = 'Informe o ID do produto ou selecione um produto existente.';
    if ($quantidade <= 0) $errors[] = 'Quantidade deve ser maior que zero.';
    if ($tipo_produto && !in_array($tipo_produto, $tipos_produto)) $errors[] = 'Tipo de produto inválido.';

    $data_mov_dt = $data_mov ? date('Y-m-d H:i:s', strtotime($data_mov)) : date('Y-m-d H:i:s');

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1) confirmar produto cadastrado (não criamos novo produto na saída)
            $st = $pdo->prepare("SELECT {$cp_id_col} AS id_cad, {$cp_nome_col} AS nome_prod FROM cadastro_produto WHERE {$cp_id_col} = ? LIMIT 1");
            $st->execute([$id_post]);
            $prodRow = $st->fetch(PDO::FETCH_ASSOC);
            if (!$prodRow) throw new Exception("Produto (ID {$id_post}) não encontrado no cadastro.");

            $id_cadastro = (int)$prodRow['id_cad'];

            // 2) se num_lote informado -> debitar desse lote específico
            $consumed = []; // array de arrays: ['lote_id'=>..,'qtd'=>..]
            $remaining = $quantidade;

            if ($num_lote !== '') {
                // localizar lote
                $stl = $pdo->prepare("SELECT id, {$l_qtd} AS qtd FROM lotes WHERE {$l_prod_fk} = ? AND {$l_num_lote} = ? FOR UPDATE");
                $stl->execute([$id_cadastro, $num_lote]);
                $lrow = $stl->fetch(PDO::FETCH_ASSOC);
                if (!$lrow) throw new Exception("Lote '{$num_lote}' não encontrado para este produto.");
                $available = (int)$lrow['qtd'];
                if ($available < $remaining) throw new Exception("Quantidade insuficiente no lote '{$num_lote}' (disponível: {$available}).");
                // decrementar
                $upd = $pdo->prepare("UPDATE lotes SET {$l_qtd} = {$l_qtd} - ? WHERE id = ?");
                $upd->execute([$remaining, $lrow['id']]);
                $consumed[] = ['lote_id' => (int)$lrow['id'], 'qtd' => $remaining];
                $remaining = 0;
            } else {
                // FIFO: consumir de lotes com qtd>0, ORDER BY data_validade ASC NULLS LAST, id ASC
                // seleciona lotes disponíveis
                $orderExpr = $l_validade ? ("(l.{$l_validade} IS NULL), l.{$l_validade} ASC, l.{$l_id_col} ASC") : ("l.{$l_id_col} ASC");
                $stl = $pdo->prepare("SELECT id, {$l_qtd} AS qtd FROM lotes WHERE {$l_prod_fk} = ? AND {$l_qtd} > 0 ORDER BY " . $orderExpr . " FOR UPDATE");
                $stl->execute([$id_cadastro]);
                $lots = $stl->fetchAll(PDO::FETCH_ASSOC);

                foreach ($lots as $lot) {
                    if ($remaining <= 0) break;
                    $avail = (int)$lot['qtd'];
                    if ($avail <= 0) continue;
                    if ($avail >= $remaining) {
                        // decrementa parcialmente ou totalmente
                        $upd = $pdo->prepare("UPDATE lotes SET {$l_qtd} = {$l_qtd} - ? WHERE id = ?");
                        $upd->execute([$remaining, $lot['id']]);
                        $consumed[] = ['lote_id' => (int)$lot['id'], 'qtd' => $remaining];
                        $remaining = 0;
                        break;
                    } else {
                        // consome todo o lote e continua
                        $upd = $pdo->prepare("UPDATE lotes SET {$l_qtd} = 0 WHERE id = ?");
                        $upd->execute([$lot['id']]);
                        $consumed[] = ['lote_id' => (int)$lot['id'], 'qtd' => $avail];
                        $remaining -= $avail;
                    }
                }

                if ($remaining > 0) {
                    throw new Exception("Quantidade insuficiente em estoque para atender a saída (faltam {$remaining} unidades).");
                }
            }

            // 3) registrar movimentos (um por lote consumido)
            $insM = $pdo->prepare("INSERT INTO movimentos (tipo, id_produto, id_lote, qtd, id_usuario, descricao, data_mov) VALUES ('saida', ?, ?, ?, ?, ?, ?)");
            foreach ($consumed as $c) {
                $insM->execute([
                    $id_cadastro,
                    $c['lote_id'],
                    $c['qtd'],
                    $_SESSION['usuario_id'] ?? null,
                    $motivo ?: 'Saída de estoque',
                    $data_mov_dt
                ]);
            }

            $pdo->commit();

            header('Location: ../produtos/index.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Erro ao registrar saída: ' . $e->getMessage();
        }
    }
}
?>

<h2>Registrar Saída</h2>

<?php if (!empty($errors)): ?>
  <div style="color:red;margin-bottom:12px">
    <?php foreach($errors as $er) echo '<div>'.e($er).'</div>'; ?>
  </div>
<?php endif; ?>

<form method="post" style="max-width:760px;">

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

  <label>Quantidade a retirar:</label><br>
  <input type="number" name="quantidade" min="1" required style="padding:8px;width:140px" value="<?= e($_POST['quantidade'] ?? 1) ?>"><br><br>

  <label>Data da saída (opcional):</label><br>
  <input type="datetime-local" name="data_mov" style="padding:8px" value="<?= e($_POST['data_mov'] ?? '') ?>"><br><br>

  <label>Número do lote (opcional - se preenchido, retiramos deste lote; se não, usamos FIFO):</label><br>
  <input type="text" name="num_lote" style="padding:8px;width:250px" value="<?= e($_POST['num_lote'] ?? '') ?>"><br><br>

  <label>Motivo/descrição da saída:</label><br>
  <input type="text" name="descricao_mov" style="padding:8px;width:100%" value="<?= e($_POST['descricao_mov'] ?? 'Saída de estoque') ?>"><br><br>

  <button type="submit" class="btn">Registrar Saída</button>
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
