<?php
// Farmacia/usuarios/delete.php
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../functions.php';
require_login(); // garante que só usuário logado pode acessar

// Apenas POST é aceito
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    // redireciona de volta mostrando erro se quiser
    header('Location: usuarios.php?msg=ID+n%C3%A3o+recebido');
    exit;
}

$id = intval($_POST['id']);
if ($id <= 0) {
    header('Location: usuarios.php?msg=ID+inv%C3%A1lido');
    exit;
}

// evita excluir o próprio usuário logado
$current_user_id = $_SESSION['usuario_id'] ?? null;
if ($current_user_id && $current_user_id == $id) {
    header('Location: usuarios.php?msg=Voc%C3%AA+n%C3%A3o+pode+excluir+seu+pr%C3%B3prio+usu%C3%A1rio');
    exit;
}

try {
    // Verifica existência
    $st = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: usuarios.php?msg=Usu%C3%A1rio+n%C3%A3o+encontrado');
        exit;
    }

    // Excluir usuário
    $del = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
    $del->execute([$id]);

    header('Location: usuarios.php?msg=Usu%C3%A1rio+exclu%C3%ADdo+com+sucesso');
    exit;

} catch (Exception $e) {
    // opcional: gravar em log aqui
    header('Location: usuarios.php?msg=Erro+ao+excluir+usu%C3%A1rio');
    exit;
}
?>
