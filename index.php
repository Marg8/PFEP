<?php
/**
 * PFEP – Catálogo para Plataforma – Componentes
 * index.php  –  Catalog view / main page
 */

require_once __DIR__ . '/config/db.php';

session_start();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pdo  = get_db();
$rows = $pdo->query(
    'SELECT * FROM componentes ORDER BY numero_parte ASC'
)->fetchAll();

/**
 * Return the full web-accessible path for an uploaded file.
 * Returns null when no file is stored.
 */
function foto_url(?string $filename): ?string {
    if (!$filename) return null;
    return 'uploads/' . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
}

/**
 * Return a CSS badge class based on size classification.
 */
function badge_class(?string $clase): string {
    return match ($clase) {
        'Chico'   => 'badge-chico',
        'Mediano' => 'badge-mediano',
        'Grande'  => 'badge-grande',
        default   => '',
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PFEP – Catálogo para Plataforma</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="site-header">
    <h1>📦 PFEP – Catálogo para Plataforma – Componentes</h1>
    <nav class="header-nav">
        <a href="form.php" class="btn-add">+ Agregar Componente</a>
        <a href="import.php" class="btn-add">📥 Importar Demanda</a>
        <a href="demanda.php" class="btn-add">👁️ Ver Demanda</a>
        <a href="dashboard.php" class="btn-add">📊 Dashboard</a>
    </nav>
</header>

<div class="container">

    <?php if ($flash): ?>
        <div class="flash <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="table-wrap">
        <table class="catalog">
            <thead>
                <tr>
                    <th>Número de<br>Parte</th>
                    <th>Foto del<br>Producto</th>
                    <th>Foto del<br>Empaque</th>
                    <th>Estándar Pack<br><small>(Caja o Bolsa Indi.)</small></th>
                    <th>Niveles por<br>Pallet</th>
                    <th>Cajas por<br>Nivel</th>
                    <th>Ancho<br><small>(Pulgadas)</small></th>
                    <th>Fondo<br><small>(Pulgadas)</small></th>
                    <th>Alto<br><small>(Pulgadas)</small></th>
                    <th>Peso<br><small>(Libras)</small></th>
                    <th>Clasificación<br>Tamaño</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr class="empty-row">
                    <td colspan="12">No hay componentes registrados. <a href="form.php">Agregar el primero →</a></td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="parte"><?= htmlspecialchars($r['numero_parte'], ENT_QUOTES, 'UTF-8') ?></td>

                    <td>
                        <?php $url = foto_url($r['foto_producto']); ?>
                        <?php if ($url): ?>
                            <img src="<?= $url ?>"
                                 alt="Foto producto"
                                 class="thumb"
                                 data-full="<?= $url ?>">
                        <?php else: ?>
                            <span class="no-foto">Sin foto</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php $url = foto_url($r['foto_empaque']); ?>
                        <?php if ($url): ?>
                            <img src="<?= $url ?>"
                                 alt="Foto empaque"
                                 class="thumb"
                                 data-full="<?= $url ?>">
                        <?php else: ?>
                            <span class="no-foto">Sin foto</span>
                        <?php endif; ?>
                    </td>

                    <td><?= $r['estandar_pack'] !== null ? htmlspecialchars($r['estandar_pack'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td><?= $r['niveles_pallet'] !== null ? htmlspecialchars($r['niveles_pallet'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td><?= $r['cajas_nivel']   !== null ? htmlspecialchars($r['cajas_nivel'],   ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td><?= $r['ancho'] !== null ? number_format((float)$r['ancho'], 2) : '—' ?></td>
                    <td><?= $r['fondo'] !== null ? number_format((float)$r['fondo'], 2) : '—' ?></td>
                    <td><?= $r['alto']  !== null ? number_format((float)$r['alto'],  2) : '—' ?></td>
                    <td><?= $r['peso']  !== null ? number_format((float)$r['peso'],  2) : '—' ?></td>

                    <td>
                        <?php if ($r['clasificacion']): ?>
                            <span class="badge <?= badge_class($r['clasificacion']) ?>">
                                <?= htmlspecialchars($r['clasificacion'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>

                    <td class="actions">
                        <a href="form.php?id=<?= (int)$r['id'] ?>" class="btn-edit">Editar</a>
                        <a href="delete.php?id=<?= (int)$r['id'] ?>" class="btn-delete">Eliminar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Lightbox -->
<div id="lightbox">
    <span class="close">&times;</span>
    <img src="" alt="Imagen ampliada">
</div>

<script src="js/app.js"></script>
</body>
</html>
