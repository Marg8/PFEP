<?php
/**
 * PFEP – Catálogo para Plataforma – Componentes
 * delete.php  –  Delete a component by ID
 *
 * Accepts GET request with ?id=N.
 * Deletes the record and its associated photo files, then redirects.
 */

require_once __DIR__ . '/config/db.php';

session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$pdo  = get_db();
$stmt = $pdo->prepare('SELECT * FROM componentes WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Componente no encontrado.'];
    header('Location: index.php');
    exit;
}

// Delete associated photo files
foreach (['foto_producto', 'foto_empaque'] as $field) {
    if ($row[$field]) {
        $path = __DIR__ . '/uploads/' . $row[$field];
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

// Delete DB record
$del = $pdo->prepare('DELETE FROM componentes WHERE id = ?');
$del->execute([$id]);

$_SESSION['flash'] = [
    'type' => 'success',
    'msg'  => "Componente '{$row['numero_parte']}' eliminado correctamente.",
];

header('Location: index.php');
exit;
