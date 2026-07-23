<?php
/**
 * PFEP – Catálogo para Plataforma – Componentes
 * form.php  –  Add / Edit component form
 */

require_once __DIR__ . '/config/db.php';

session_start();

$pdo = get_db();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Load existing record for edit mode
$row    = null;
$errors = $_SESSION['form_errors'] ?? [];
$old    = $_SESSION['form_old']    ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_old']);

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM componentes WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        header('Location: index.php');
        exit;
    }
}

$is_edit = ($row !== null);
$title   = $is_edit ? 'Editar Componente' : 'Agregar Componente';

/**
 * Return old (re-populated) value, then the DB value, then empty string.
 */
function val(string $field, ?array $old, ?array $row): string {
    if (isset($old[$field])) return htmlspecialchars((string)$old[$field], ENT_QUOTES, 'UTF-8');
    if ($row && isset($row[$field]) && $row[$field] !== null) {
        return htmlspecialchars((string)$row[$field], ENT_QUOTES, 'UTF-8');
    }
    return '';
}

/**
 * Check if a select value matches the given option.
 */
function sel(string $field, string $option, ?array $old, ?array $row): string {
    $current = '';
    if (isset($old[$field]))                                  $current = (string)$old[$field];
    elseif ($row && isset($row[$field]) && $row[$field] !== null) $current = (string)$row[$field];
    return $current === $option ? ' selected' : '';
}

function foto_url(?string $filename): ?string {
    if (!$filename) return null;
    return 'uploads/' . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> – PFEP</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="site-header">
    <h1>📦 PFEP – <?= htmlspecialchars($title) ?></h1>
    <a href="index.php" class="btn-add">← Volver al Catálogo</a>
</header>

<div class="container">
    <div class="form-card">
        <h2><?= htmlspecialchars($title) ?></h2>

        <?php if (!empty($errors)): ?>
            <div class="flash error">
                <ul style="margin-left:16px">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST"
              action="process.php"
              enctype="multipart/form-data"
              novalidate>

            <input type="hidden" name="id"
                   value="<?= $is_edit ? (int)$row['id'] : '' ?>">

            <div class="form-grid">

                <!-- Número de Parte -->
                <div class="form-group full">
                    <label for="numero_parte">Número de Parte <span style="color:red">*</span></label>
                    <input type="text"
                           id="numero_parte"
                           name="numero_parte"
                           value="<?= val('numero_parte', $old, $row) ?>"
                           placeholder="Ej. 1003344"
                           maxlength="50"
                           required>
                </div>

                <!-- Foto del Producto -->
                <div class="form-group">
                    <label for="foto_producto">Foto del Producto / Material</label>
                    <div class="file-wrap">
                        <input type="file"
                               id="foto_producto"
                               name="foto_producto"
                               class="foto-input"
                               data-preview="prev_producto"
                               accept="image/*"
                               capture="environment">
                    </div>
                    <div id="prev_producto" class="foto-preview">
                        <?php $u = foto_url($row['foto_producto'] ?? null); ?>
                        <?php if ($u): ?>
                            <img src="<?= $u ?>" alt="Foto actual del producto">
                            <span class="hint">Foto actual – sube otra para reemplazar</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Foto del Empaque -->
                <div class="form-group">
                    <label for="foto_empaque">Foto del Empaque <small>(parcial/bolsas o completo/cerrado)</small></label>
                    <div class="file-wrap">
                        <input type="file"
                               id="foto_empaque"
                               name="foto_empaque"
                               class="foto-input"
                               data-preview="prev_empaque"
                               accept="image/*"
                               capture="environment">
                    </div>
                    <div id="prev_empaque" class="foto-preview">
                        <?php $u = foto_url($row['foto_empaque'] ?? null); ?>
                        <?php if ($u): ?>
                            <img src="<?= $u ?>" alt="Foto actual del empaque">
                            <span class="hint">Foto actual – sube otra para reemplazar</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Estándar Pack -->
                <div class="form-group">
                    <label for="estandar_pack">Estándar Pack <small>(Caja o Bolsa Individual)</small></label>
                    <input type="number"
                           id="estandar_pack"
                           name="estandar_pack"
                           value="<?= val('estandar_pack', $old, $row) ?>"
                           min="1"
                           placeholder="Ej. 1200">
                </div>

                <!-- Niveles por Pallet -->
                <div class="form-group">
                    <label for="niveles_pallet">Niveles por Pallet <small>(3, 4 o 5, etc.)</small></label>
                    <input type="number"
                           id="niveles_pallet"
                           name="niveles_pallet"
                           value="<?= val('niveles_pallet', $old, $row) ?>"
                           min="1"
                           placeholder="Ej. 5">
                </div>

                <!-- Cajas por Nivel -->
                <div class="form-group">
                    <label for="cajas_nivel">Cajas por Nivel <small>(6, 7 o 10, etc.)</small></label>
                    <input type="number"
                           id="cajas_nivel"
                           name="cajas_nivel"
                           value="<?= val('cajas_nivel', $old, $row) ?>"
                           min="1"
                           placeholder="Ej. 8">
                </div>

                <!-- Dimensiones -->
                <div class="form-group">
                    <label for="ancho">Ancho <small>(pulgadas)</small></label>
                    <input type="number"
                           id="ancho"
                           name="ancho"
                           value="<?= val('ancho', $old, $row) ?>"
                           min="0"
                           step="0.001"
                           placeholder="Ej. 14">
                </div>

                <div class="form-group">
                    <label for="fondo">Fondo <small>(pulgadas)</small></label>
                    <input type="number"
                           id="fondo"
                           name="fondo"
                           value="<?= val('fondo', $old, $row) ?>"
                           min="0"
                           step="0.001"
                           placeholder="Ej. 21">
                </div>

                <div class="form-group">
                    <label for="alto">Alto <small>(pulgadas)</small></label>
                    <input type="number"
                           id="alto"
                           name="alto"
                           value="<?= val('alto', $old, $row) ?>"
                           min="0"
                           step="0.001"
                           placeholder="Ej. 13">
                </div>

                <!-- Peso -->
                <div class="form-group">
                    <label for="peso">Peso <small>(libras)</small></label>
                    <input type="number"
                           id="peso"
                           name="peso"
                           value="<?= val('peso', $old, $row) ?>"
                           min="0"
                           step="0.001"
                           placeholder="Ej. 20.34">
                </div>

                <!-- Clasificación de Tamaño -->
                <div class="form-group">
                    <label for="clasificacion">Clasificación de Tamaño</label>
                    <select id="clasificacion" name="clasificacion">
                        <option value="">— Seleccionar —</option>
                        <option value="Chico"  <?= sel('clasificacion', 'Chico',   $old, $row) ?>>Chico</option>
                        <option value="Mediano" <?= sel('clasificacion', 'Mediano', $old, $row) ?>>Mediano</option>
                        <option value="Grande" <?= sel('clasificacion', 'Grande',  $old, $row) ?>>Grande</option>
                    </select>
                </div>

            </div><!-- /.form-grid -->

            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    <?= $is_edit ? '💾 Guardar Cambios' : '✅ Agregar Componente' ?>
                </button>
                <a href="index.php" class="btn-cancel">Cancelar</a>
            </div>

        </form>
    </div>
</div>

<script src="js/app.js"></script>
</body>
</html>
