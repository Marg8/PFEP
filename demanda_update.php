<?php
/**
 * PFEP – Guardado inline de demanda (UPDATE exclusivo)
 * demanda_update.php  –  Endpoint AJAX usado por demanda.php.
 *
 * Actualiza SOLO daily_demand, safety_stock_days y lead_time_days para un
 * part_number existente. No toca dimensiones ni imágenes. Responde JSON.
 */

require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

$part = trim((string)($_POST['part_number'] ?? ''));
if ($part === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'part_number es obligatorio.']);
    exit;
}

$demandRaw = trim((string)($_POST['daily_demand'] ?? ''));
if ($demandRaw === '' || !is_numeric($demandRaw)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'daily_demand debe ser numérico.']);
    exit;
}

$demand = max(0, (int)$demandRaw);
$safety = max(0, (int)($_POST['safety_stock_days'] ?? 0));
$lead   = max(0, (int)($_POST['lead_time_days'] ?? 0));

$pdo  = get_db();
$stmt = $pdo->prepare(
    'UPDATE componentes
        SET daily_demand      = :daily_demand,
            safety_stock_days = :safety_stock_days,
            lead_time_days    = :lead_time_days
      WHERE numero_parte = :part_number'
);
$stmt->execute([
    ':daily_demand'      => $demand,
    ':safety_stock_days' => $safety,
    ':lead_time_days'    => $lead,
    ':part_number'       => $part,
]);

// Confirmar que la parte existe (rowCount es 0 también si nada cambió)
$exists = $pdo->prepare('SELECT 1 FROM componentes WHERE numero_parte = ? LIMIT 1');
$exists->execute([$part]);
if (!$exists->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => "Parte '{$part}' no encontrada."]);
    exit;
}

echo json_encode([
    'ok'                => true,
    'part_number'       => $part,
    'daily_demand'      => $demand,
    'safety_stock_days' => $safety,
    'lead_time_days'    => $lead,
]);
