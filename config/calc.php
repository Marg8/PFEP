<?php
/**
 * PFEP – Space/volume calculation helpers.
 *
 * Dimensions in the DB (ancho, fondo, alto) are stored in INCHES.
 * These helpers convert them to metres to produce cubic-metre volumes.
 */

const INCH_TO_M = 0.0254; // 1 inch = 0.0254 m

/**
 * Unit volume in cubic metres from the stored dimensions (inches).
 * Returns null when any dimension is missing/zero.
 */
function unit_volume_m3(?float $ancho, ?float $fondo, ?float $alto): ?float {
    if (!$ancho || !$fondo || !$alto) {
        return null;
    }
    $m3 = ($ancho * INCH_TO_M) * ($fondo * INCH_TO_M) * ($alto * INCH_TO_M);
    return round($m3, 6);
}

/**
 * Classify a part by its unit volume (m³).
 * Thresholds are expressed in cubic metres.
 */
function size_class_by_volume(?float $vol_m3): ?string {
    if ($vol_m3 === null) {
        return null;
    }
    if ($vol_m3 < 0.02)  return 'Chico';   // < 20 L
    if ($vol_m3 < 0.10)  return 'Mediano'; // < 100 L
    return 'Grande';
}

/**
 * Compute the PFEP space requirement for one component.
 *
 * @param array $r  A `componentes` row.
 * @param float $slack Holgura factor (defaults to FACTOR_HOLGURA).
 * @return array {
 *   unit_volume_m3: ?float,
 *   max_inventory:  int,      pieces
 *   boxes_needed:   ?int,     null when pcs/box unknown
 *   space_m3:       ?float,   null when volume/pcs unknown
 *   size_class:     ?string,
 *   pending_dims:   bool,     true when dimensions or pcs/box are missing
 * }
 */
function space_calc(array $r, float $slack = FACTOR_HOLGURA): array {
    $ancho = isset($r['ancho']) ? (float)$r['ancho'] : 0.0;
    $fondo = isset($r['fondo']) ? (float)$r['fondo'] : 0.0;
    $alto  = isset($r['alto'])  ? (float)$r['alto']  : 0.0;
    $pcs   = isset($r['estandar_pack']) ? (int)$r['estandar_pack'] : 0;

    $demand = (int)($r['daily_demand']      ?? 0);
    $safety = (int)($r['safety_stock_days'] ?? 0);
    $lead   = (int)($r['lead_time_days']    ?? 0);

    $unit_vol = unit_volume_m3($ancho, $fondo, $alto);

    // Inventario Máximo (piezas) = Demanda Diaria × (Lead Time + Días de Seguridad)
    $max_inventory = max(0, $demand) * max(0, $lead + $safety);

    // Cajas Necesarias = ceil(Inventario Máximo / Piezas por Caja)
    $boxes_needed = ($pcs > 0) ? (int)ceil($max_inventory / $pcs) : null;

    // Espacio Necesario (m³) = Cajas × Volumen Unitario × Factor Holgura
    $space_m3 = ($unit_vol !== null && $boxes_needed !== null)
        ? round($boxes_needed * $unit_vol * $slack, 4)
        : null;

    $pending_dims = ($unit_vol === null || $pcs <= 0);

    return [
        'unit_volume_m3' => $unit_vol,
        'max_inventory'  => $max_inventory,
        'boxes_needed'   => $boxes_needed,
        'space_m3'       => $space_m3,
        'size_class'     => size_class_by_volume($unit_vol),
        'pending_dims'   => $pending_dims,
    ];
}
