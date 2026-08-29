<?php
/**
 * PFEP – Vista de Demanda Cargada
 * demanda.php  –  Consulta de solo lectura de los parámetros de demanda.
 */

require_once __DIR__ . '/config/db.php';

$pdo  = get_db();
$rows = $pdo->query(
    'SELECT numero_parte, daily_demand, safety_stock_days, lead_time_days, updated_at
       FROM componentes
      ORDER BY numero_parte ASC'
)->fetchAll();

$total          = count($rows);
$con_demanda    = 0;
$sin_demanda    = 0;
foreach ($rows as $r) {
    if ((int)$r['daily_demand'] > 0) $con_demanda++; else $sin_demanda++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demanda Cargada – PFEP</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php
$active_page   = 'demanda';
$page_subtitle = '👁️ Demanda Cargada';
require __DIR__ . '/partials/header.php';
?>

<div class="container">

    <div class="kpi-grid">
        <div class="kpi-card">
            <span class="kpi-label">Partes</span>
            <span class="kpi-value"><?= $total ?></span>
        </div>
        <div class="kpi-card kpi-chico">
            <span class="kpi-label">Con demanda</span>
            <span class="kpi-value"><?= $con_demanda ?></span>
        </div>
        <div class="kpi-card kpi-warn">
            <span class="kpi-label">Sin demanda</span>
            <span class="kpi-value"><?= $sin_demanda ?></span>
        </div>
    </div>

    <div class="search-bar">
        <input type="text"
               id="pfep-search"
               data-target="demanda-table"
               placeholder="🔎 Filtrar por Número de Parte…"
               autocomplete="off">
        <span id="search-count" class="muted"></span>
        <a href="import.php?template=1" class="btn-submit" style="margin-left:auto">⬇️ Exportar CSV</a>
    </div>

    <p class="muted" style="margin-bottom:10px">
        ✏️ Edita las cantidades directamente en la tabla para simular escenarios y
        presiona <strong>Guardar</strong>. Solo se actualiza la demanda; las
        dimensiones e imágenes no se modifican.
    </p>

    <div class="table-wrap">
        <table class="catalog" id="demanda-table">
            <thead>
                <tr>
                    <th>Número de<br>Parte</th>
                    <th>Demanda<br>Diaria</th>
                    <th>Días<br>Seguridad</th>
                    <th>Lead Time<br>(días)</th>
                    <th>Última<br>Actualización</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr class="empty-row">
                    <td colspan="6">No hay componentes registrados. <a href="import.php">Importar demanda →</a></td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                <tr data-part="<?= htmlspecialchars(strtolower($r['numero_parte']), ENT_QUOTES, 'UTF-8') ?>"
                    class="demanda-row <?= (int)$r['daily_demand'] === 0 ? 'row-pending' : '' ?>"
                    data-part-number="<?= htmlspecialchars($r['numero_parte'], ENT_QUOTES, 'UTF-8') ?>">
                    <td class="parte"><?= htmlspecialchars($r['numero_parte'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <input type="number" class="cell-input" data-field="daily_demand"
                               min="0" step="1" value="<?= (int)$r['daily_demand'] ?>">
                    </td>
                    <td>
                        <input type="number" class="cell-input" data-field="safety_stock_days"
                               min="0" step="1" value="<?= (int)$r['safety_stock_days'] ?>">
                    </td>
                    <td>
                        <input type="number" class="cell-input" data-field="lead_time_days"
                               min="0" step="1" value="<?= (int)$r['lead_time_days'] ?>">
                    </td>
                    <td class="cell-updated"><?= htmlspecialchars((string)$r['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <button type="button" class="btn-edit btn-save-demanda">💾 Guardar</button>
                        <span class="save-status"></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="js/app.js"></script>
</body>
</html>
