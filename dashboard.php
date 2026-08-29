<?php
/**
 * PFEP – Dashboard de Análisis y Cálculo de Espacio
 * dashboard.php
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/calc.php';

$pdo  = get_db();
$rows = $pdo->query('SELECT * FROM componentes ORDER BY numero_parte ASC')->fetchAll();

/* ---- Aggregate calculations + audit ---- */
$items          = [];
$total_space    = 0.0;
$class_count    = ['Chico' => 0, 'Mediano' => 0, 'Grande' => 0, 'N/D' => 0];
$audit_zero     = []; // demanda cargada = 0
$audit_pending  = []; // dimensiones o pcs/caja pendientes

foreach ($rows as $r) {
    $calc = space_calc($r);
    $items[] = ['row' => $r, 'calc' => $calc];

    if ($calc['space_m3'] !== null) {
        $total_space += $calc['space_m3'];
    }

    $cls = $calc['size_class'] ?? 'N/D';
    $class_count[$cls] = ($class_count[$cls] ?? 0) + 1;

    if ((int)($r['daily_demand'] ?? 0) === 0) {
        $audit_zero[] = $r['numero_parte'];
    }
    if ($calc['pending_dims']) {
        $audit_pending[] = $r['numero_parte'];
    }
}

function badge_class(?string $clase): string {
    return match ($clase) {
        'Chico'   => 'badge-chico',
        'Mediano' => 'badge-mediano',
        'Grande'  => 'badge-grande',
        default   => '',
    };
}
function fmt(?float $n, int $dec = 4): string {
    return $n === null ? '—' : number_format($n, $dec);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard PFEP – Cálculo de Espacio</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="site-header">
    <h1>📊 PFEP – Dashboard de Espacio</h1>
    <nav class="header-nav">
        <a href="index.php" class="btn-add">← Catálogo</a>
        <a href="import.php" class="btn-add">📥 Importar Demanda</a>
        <a href="demanda.php" class="btn-add">👁️ Ver Demanda</a>
    </nav>
</header>

<div class="container">

    <!-- KPI cards -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <span class="kpi-label">Partes</span>
            <span class="kpi-value"><?= count($items) ?></span>
        </div>
        <div class="kpi-card">
            <span class="kpi-label">Espacio total (m³)</span>
            <span class="kpi-value"><?= number_format($total_space, 3) ?></span>
        </div>
        <div class="kpi-card kpi-chico">
            <span class="kpi-label">Chico</span>
            <span class="kpi-value"><?= (int)$class_count['Chico'] ?></span>
        </div>
        <div class="kpi-card kpi-mediano">
            <span class="kpi-label">Mediano</span>
            <span class="kpi-value"><?= (int)$class_count['Mediano'] ?></span>
        </div>
        <div class="kpi-card kpi-grande">
            <span class="kpi-label">Grande</span>
            <span class="kpi-value"><?= (int)$class_count['Grande'] ?></span>
        </div>
        <div class="kpi-card kpi-warn">
            <span class="kpi-label">Factor holgura</span>
            <span class="kpi-value">×<?= number_format(FACTOR_HOLGURA, 2) ?></span>
        </div>
    </div>

    <!-- Auditoría de datos -->
    <?php if (!empty($audit_zero) || !empty($audit_pending)): ?>
    <div class="audit-grid">
        <?php if (!empty($audit_pending)): ?>
        <div class="audit-card audit-danger">
            <h3>⚠️ Dimensiones pendientes (<?= count($audit_pending) ?>)</h3>
            <p>Falta ancho/fondo/alto o estándar de pack, no se puede calcular el espacio:</p>
            <p class="audit-list"><?= htmlspecialchars(implode(', ', $audit_pending), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($audit_zero)): ?>
        <div class="audit-card audit-warn">
            <h3>🟡 Demanda en 0 (<?= count($audit_zero) ?>)</h3>
            <p>Partes sin demanda cargada desde la plantilla:</p>
            <p class="audit-list"><?= htmlspecialchars(implode(', ', $audit_zero), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Buscador -->
    <div class="search-bar">
        <input type="text"
               id="pfep-search"
               data-target="dashboard-table"
               placeholder="🔎 Filtrar por Número de Parte…"
               autocomplete="off">
        <span id="search-count" class="muted"></span>
    </div>

    <div class="table-wrap">
        <table class="catalog" id="dashboard-table">
            <thead>
                <tr>
                    <th>Número de<br>Parte</th>
                    <th>Demanda<br>Diaria</th>
                    <th>Días<br>Seguridad</th>
                    <th>Lead Time<br>(días)</th>
                    <th>Pcs/Caja<br><small>(BD)</small></th>
                    <th>Vol. Unit.<br>(m³)</th>
                    <th>Inv. Máx.<br>(piezas)</th>
                    <th>Cajas<br>Necesarias</th>
                    <th>Espacio<br>(m³)</th>
                    <th>Clasificación</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($items)): ?>
                <tr class="empty-row">
                    <td colspan="10">No hay componentes registrados. <a href="import.php">Importar demanda →</a></td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $it):
                    $r = $it['row']; $c = $it['calc']; ?>
                <tr data-part="<?= htmlspecialchars(strtolower($r['numero_parte']), ENT_QUOTES, 'UTF-8') ?>"
                    class="<?= $c['pending_dims'] ? 'row-pending' : '' ?>">
                    <td class="parte"><?= htmlspecialchars($r['numero_parte'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int)($r['daily_demand'] ?? 0) ?></td>
                    <td><?= (int)($r['safety_stock_days'] ?? 0) ?></td>
                    <td><?= (int)($r['lead_time_days'] ?? 0) ?></td>
                    <td><?= $r['estandar_pack'] !== null ? (int)$r['estandar_pack'] : '—' ?></td>
                    <td><?= fmt($c['unit_volume_m3'], 6) ?></td>
                    <td><?= number_format($c['max_inventory']) ?></td>
                    <td><?= $c['boxes_needed'] !== null ? number_format($c['boxes_needed']) : '—' ?></td>
                    <td><strong><?= fmt($c['space_m3'], 4) ?></strong></td>
                    <td>
                        <?php if ($c['size_class']): ?>
                            <span class="badge <?= badge_class($c['size_class']) ?>">
                                <?= htmlspecialchars($c['size_class'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php else: ?>
                            <span class="badge">N/D</span>
                        <?php endif; ?>
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
