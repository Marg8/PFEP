<?php
/**
 * PFEP – Importación de Demanda (UPDATE exclusivo)
 * import.php
 *
 *  - GET  ?template=1  → descarga la plantilla CSV de demanda.
 *  - POST (archivo)    → actualiza SOLO los campos de demanda por part_number,
 *                        conservando dimensiones e imágenes ya guardadas.
 */

require_once __DIR__ . '/config/db.php';

session_start();

/* ------------------------------------------------------------------ */
/*  Plantilla CSV de demanda                                           */
/*  Exporta todas las partes registradas con su demanda actual para    */
/*  editarla y volver a subirla. Si no hay partes, incluye un ejemplo. */
/* ------------------------------------------------------------------ */
if (isset($_GET['template'])) {
    $pdo  = get_db();
    $rows = $pdo->query(
        'SELECT numero_parte, daily_demand, safety_stock_days, lead_time_days
           FROM componentes
          ORDER BY numero_parte ASC'
    )->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="plantilla_demanda_pfep.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['part_number', 'daily_demand', 'safety_stock_days', 'lead_time_days']);

    if (empty($rows)) {
        fputcsv($out, ['PN-1001', 250, 5, 3]);
    } else {
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['numero_parte'],
                (int)$r['daily_demand'],
                (int)$r['safety_stock_days'],
                (int)$r['lead_time_days'],
            ]);
        }
    }
    fclose($out);
    exit;
}

$result = null; // filled after an import

/* ------------------------------------------------------------------ */
/*  Procesar archivo subido                                            */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = import_demand_file();
}

/**
 * Parse the uploaded CSV and apply demand-only updates.
 * Returns a summary array for rendering.
 */
function import_demand_file(): array {
    $summary = [
        'updated'    => 0,
        'not_found'  => [],
        'errors'     => [],
        'row_errors' => [],
    ];

    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] === UPLOAD_ERR_NO_FILE) {
        $summary['errors'][] = 'No se seleccionó ningún archivo.';
        return $summary;
    }

    $file = $_FILES['archivo'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $summary['errors'][] = "Error al subir el archivo (código {$file['error']}).";
        return $summary;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt'], true)) {
        $summary['errors'][] = 'El archivo debe ser CSV. Si usas Excel, guárdalo como CSV (.csv).';
        return $summary;
    }

    $handle = fopen($file['tmp_name'], 'r');
    if ($handle === false) {
        $summary['errors'][] = 'No se pudo leer el archivo.';
        return $summary;
    }

    // Detect delimiter from the first line (comma or semicolon).
    $firstLine = fgets($handle);
    if ($firstLine === false) {
        fclose($handle);
        $summary['errors'][] = 'El archivo está vacío.';
        return $summary;
    }
    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
    rewind($handle);

    // Header row → column index map.
    $header = fgetcsv($handle, 0, $delimiter);
    if ($header === false) {
        fclose($handle);
        $summary['errors'][] = 'No se pudo leer el encabezado.';
        return $summary;
    }
    $header = array_map(fn($h) => strtolower(trim((string)$h, " \t\n\r\0\x0B\xEF\xBB\xBF")), $header);
    $idx    = array_flip($header);

    $required = ['part_number', 'daily_demand'];
    foreach ($required as $col) {
        if (!isset($idx[$col])) {
            fclose($handle);
            $summary['errors'][] = "Falta la columna obligatoria '{$col}' en el encabezado.";
            return $summary;
        }
    }

    $pdo = get_db();
    $stmt = $pdo->prepare(
        'UPDATE componentes
            SET daily_demand      = :daily_demand,
                safety_stock_days = :safety_stock_days,
                lead_time_days    = :lead_time_days
          WHERE numero_parte = :part_number'
    );

    $lineNo = 1; // header already consumed
    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        $lineNo++;

        // Skip fully blank lines
        if (count($data) === 1 && trim((string)$data[0]) === '') {
            continue;
        }

        $part = trim((string)($data[$idx['part_number']] ?? ''));
        if ($part === '') {
            $summary['row_errors'][] = "Línea {$lineNo}: part_number vacío.";
            continue;
        }

        $demandRaw = trim((string)($data[$idx['daily_demand']] ?? ''));
        if ($demandRaw === '' || !is_numeric($demandRaw)) {
            $summary['row_errors'][] = "Línea {$lineNo} ({$part}): daily_demand es obligatorio y numérico.";
            continue;
        }

        $demand = max(0, (int)$demandRaw);
        $safety = isset($idx['safety_stock_days']) ? max(0, (int)($data[$idx['safety_stock_days']] ?? 0)) : 0;
        $lead   = isset($idx['lead_time_days'])    ? max(0, (int)($data[$idx['lead_time_days']] ?? 0))    : 0;

        $stmt->execute([
            ':daily_demand'      => $demand,
            ':safety_stock_days' => $safety,
            ':lead_time_days'    => $lead,
            ':part_number'       => $part,
        ]);

        if ($stmt->rowCount() > 0) {
            $summary['updated']++;
        } else {
            // rowCount 0 → part not found (values identical also returns 0, but
            // we treat "no matching part" as the meaningful case here).
            $exists = $pdo->prepare('SELECT 1 FROM componentes WHERE numero_parte = ? LIMIT 1');
            $exists->execute([$part]);
            if ($exists->fetchColumn()) {
                $summary['updated']++; // matched, values unchanged
            } else {
                $summary['not_found'][] = $part;
            }
        }
    }
    fclose($handle);

    return $summary;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Demanda – PFEP</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php
$active_page   = 'import';
$page_subtitle = '📥 Importar Demanda';
require __DIR__ . '/partials/header.php';
?>

<div class="container">

    <!-- Guía de 3 pasos -->
    <ol class="import-steps">
        <li class="step-card">
            <span class="step-num">1</span>
            <div class="step-body">
                <h3>Descarga la plantilla</h3>
                <p>Obtén un CSV con la demanda actual de todas tus partes registradas.</p>
                <a href="import.php?template=1" class="btn-step">⬇️ Descargar plantilla</a>
            </div>
        </li>
        <li class="step-card">
            <span class="step-num">2</span>
            <div class="step-body">
                <h3>Edita los valores</h3>
                <p>Actualiza <code>daily_demand</code>, <code>safety_stock_days</code> y <code>lead_time_days</code>. Vuelve a guardar como CSV.</p>
            </div>
        </li>
        <li class="step-card">
            <span class="step-num">3</span>
            <div class="step-body">
                <h3>Sube el archivo</h3>
                <p>Carga el CSV para actualizar la demanda de forma masiva en segundos.</p>
            </div>
        </li>
    </ol>

    <div class="import-grid">
        <!-- Zona de carga -->
        <div class="form-card upload-card">
            <h2>Carga masiva de demanda</h2>

            <form method="POST" action="import.php" enctype="multipart/form-data" class="import-form" id="import-form">
                <label class="dropzone" id="dropzone" for="archivo">
                    <input type="file" id="archivo" name="archivo"
                           accept=".csv,text/csv" required class="dropzone-input">
                    <span class="dropzone-icon">📄</span>
                    <span class="dropzone-title">Arrastra tu archivo CSV aquí</span>
                    <span class="dropzone-hint">o haz clic para seleccionarlo</span>
                    <span class="dropzone-file" id="dropzone-file"></span>
                </label>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">🚀 Importar y actualizar</button>
                    <a href="demanda.php" class="btn-cancel">👁️ Ver demanda cargada</a>
                </div>
            </form>
        </div>

        <!-- Formato requerido -->
        <aside class="form-card info-card">
            <h2>Formato requerido</h2>
            <p class="muted">La primera fila del CSV debe ser el encabezado con estas columnas:</p>
            <table class="cols-table">
                <tbody>
                    <tr><td><code>part_number</code></td><td><span class="tag tag-req">Obligatorio</span></td></tr>
                    <tr><td><code>daily_demand</code></td><td><span class="tag tag-req">Obligatorio</span></td></tr>
                    <tr><td><code>safety_stock_days</code></td><td><span class="tag tag-opt">Opcional</span></td></tr>
                    <tr><td><code>lead_time_days</code></td><td><span class="tag tag-opt">Opcional</span></td></tr>
                </tbody>
            </table>
            <div class="info-note">
                ℹ️ Solo se actualiza la demanda y los parámetros logísticos de partes
                <strong>ya existentes</strong>. Las dimensiones e imágenes registradas
                <strong>no se modifican</strong>.
            </div>
        </aside>
    </div>

    <?php if ($result !== null): ?>
        <?php
            $n_updated  = (int)$result['updated'];
            $n_notfound = count($result['not_found']);
            $n_ignored  = count($result['row_errors']);
            $has_fatal  = !empty($result['errors']);
        ?>
        <div class="form-card result-card">
            <h2>Resultado de la importación</h2>

            <?php if (!$has_fatal): ?>
                <div class="result-stats">
                    <div class="stat stat-ok">
                        <span class="stat-num"><?= $n_updated ?></span>
                        <span class="stat-label">Actualizadas</span>
                    </div>
                    <div class="stat stat-warn">
                        <span class="stat-num"><?= $n_notfound ?></span>
                        <span class="stat-label">No encontradas</span>
                    </div>
                    <div class="stat stat-err">
                        <span class="stat-num"><?= $n_ignored ?></span>
                        <span class="stat-label">Líneas ignoradas</span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($has_fatal): ?>
                <div class="flash error">
                    <ul style="margin-left:16px">
                        <?php foreach ($result['errors'] as $e): ?>
                            <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($n_updated > 0): ?>
                <div class="flash success">
                    ✅ <?= $n_updated ?> parte(s) actualizada(s) correctamente.
                </div>
            <?php endif; ?>

            <?php if ($n_notfound > 0): ?>
                <div class="flash error">
                    <strong>No encontradas (<?= $n_notfound ?>):</strong>
                    <?= htmlspecialchars(implode(', ', $result['not_found']), ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($n_ignored > 0): ?>
                <div class="flash error">
                    <strong>Líneas ignoradas:</strong>
                    <ul style="margin-left:16px">
                        <?php foreach ($result['row_errors'] as $e): ?>
                            <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <a href="dashboard.php" class="btn-submit">📊 Ver cálculo de espacio</a>
                <a href="import.php" class="btn-cancel">↻ Importar otro archivo</a>
            </div>
        </div>
    <?php endif; ?>

</div>

<script src="js/app.js"></script>
</body>
</html>
