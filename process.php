<?php
/**
 * PFEP – Catálogo para Plataforma – Componentes
 * process.php  –  Handles form submission (insert / update)
 *
 * Accepts only POST requests.
 * On validation error: redirects back to form.php with errors in session.
 * On success: redirects to index.php with a success flash message.
 */

require_once __DIR__ . '/config/db.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

/* ------------------------------------------------------------------ */
/*  Helpers                                                             */
/* ------------------------------------------------------------------ */

/**
 * Return a trimmed string or null if empty.
 */
function str_input(string $key): ?string {
    $val = trim($_POST[$key] ?? '');
    return $val !== '' ? $val : null;
}

/**
 * Return a positive integer or null.
 */
function int_input(string $key): ?int {
    $val = trim($_POST[$key] ?? '');
    if ($val === '') return null;
    $n = (int)$val;
    return $n > 0 ? $n : null;
}

/**
 * Return a non-negative float or null.
 */
function float_input(string $key): ?float {
    $val = trim($_POST[$key] ?? '');
    if ($val === '') return null;
    $n = (float)$val;
    return $n >= 0.0 ? round($n, 3) : null;
}

/* ------------------------------------------------------------------ */
/*  Upload a photo; returns filename on success, null if no file.      */
/*  Returns false if upload failed (sets $error string by reference).  */
/* ------------------------------------------------------------------ */
function upload_foto(string $field, ?string $existing_name, string &$error): string|false|null {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // no new file – keep existing
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Error al subir el archivo del campo '{$field}' (código {$file['error']}).";
        return false;
    }

    // Validate MIME type
    $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo        = finfo_open(FILEINFO_MIME_TYPE);
    $mime         = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_mime, true)) {
        $error = "El archivo '{$field}' no es una imagen válida (JPEG, PNG, GIF o WEBP).";
        return false;
    }

    // Max 10 MB
    if ($file['size'] > 10 * 1024 * 1024) {
        $error = "El archivo '{$field}' supera el límite de 10 MB.";
        return false;
    }

    $ext       = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $safe_ext  = preg_replace('/[^a-z0-9]/i', '', $ext);
    $filename  = uniqid('pfep_', true) . '.' . strtolower($safe_ext);
    $dest      = __DIR__ . '/uploads/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $error = "No se pudo guardar la imagen del campo '{$field}'.";
        return false;
    }

    // Delete old photo after successful upload
    if ($existing_name) {
        $old_path = __DIR__ . '/uploads/' . $existing_name;
        if (is_file($old_path)) {
            @unlink($old_path);
        }
    }

    return $filename;
}

/* ------------------------------------------------------------------ */
/*  Read & validate inputs                                             */
/* ------------------------------------------------------------------ */

$id             = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : 0;
$numero_parte   = str_input('numero_parte');
$estandar_pack  = int_input('estandar_pack');
$niveles_pallet = int_input('niveles_pallet');
$cajas_nivel    = int_input('cajas_nivel');
$ancho          = float_input('ancho');
$fondo          = float_input('fondo');
$alto           = float_input('alto');
$peso           = float_input('peso');
$clasificacion  = str_input('clasificacion');

$errors = [];

if (!$numero_parte) {
    $errors[] = 'El Número de Parte es obligatorio.';
}

$valid_clases = ['Chico', 'Mediano', 'Grande', null];
if ($clasificacion !== null && !in_array($clasificacion, ['Chico', 'Mediano', 'Grande'], true)) {
    $errors[] = 'Clasificación de tamaño no válida.';
    $clasificacion = null;
}

/* ------------------------------------------------------------------ */
/*  Load existing record when editing (needed for photo management)   */
/* ------------------------------------------------------------------ */

$pdo      = get_db();
$existing = null;

if ($id > 0) {
    $stmt     = $pdo->prepare('SELECT * FROM componentes WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Componente no encontrado.'];
        header('Location: index.php');
        exit;
    }
}

/* ------------------------------------------------------------------ */
/*  Handle photo uploads (only if no validation errors so far)        */
/* ------------------------------------------------------------------ */

$foto_producto = $existing['foto_producto'] ?? null;
$foto_empaque  = $existing['foto_empaque']  ?? null;
$upload_error  = '';

if (empty($errors)) {
    $result = upload_foto('foto_producto', $foto_producto, $upload_error);
    if ($result === false) {
        $errors[] = $upload_error;
    } elseif ($result !== null) {
        $foto_producto = $result;
    }

    $result = upload_foto('foto_empaque', $foto_empaque, $upload_error);
    if ($result === false) {
        $errors[] = $upload_error;
    } elseif ($result !== null) {
        $foto_empaque = $result;
    }
}

/* ------------------------------------------------------------------ */
/*  On validation error: redirect back                                 */
/* ------------------------------------------------------------------ */

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_old']    = $_POST;
    $redirect = $id > 0 ? "form.php?id={$id}" : 'form.php';
    header("Location: {$redirect}");
    exit;
}

/* ------------------------------------------------------------------ */
/*  Check duplicate numero_parte                                       */
/* ------------------------------------------------------------------ */

$stmt = $pdo->prepare(
    'SELECT id FROM componentes WHERE numero_parte = ? AND id != ?'
);
$stmt->execute([$numero_parte, $id]);
if ($stmt->fetch()) {
    $_SESSION['form_errors'] = ["El Número de Parte '{$numero_parte}' ya existe."];
    $_SESSION['form_old']    = $_POST;
    $redirect = $id > 0 ? "form.php?id={$id}" : 'form.php';
    header("Location: {$redirect}");
    exit;
}

/* ------------------------------------------------------------------ */
/*  Save to database                                                   */
/* ------------------------------------------------------------------ */

if ($id > 0) {
    // UPDATE
    $stmt = $pdo->prepare(
        'UPDATE componentes SET
            numero_parte   = :numero_parte,
            foto_producto  = :foto_producto,
            foto_empaque   = :foto_empaque,
            estandar_pack  = :estandar_pack,
            niveles_pallet = :niveles_pallet,
            cajas_nivel    = :cajas_nivel,
            ancho          = :ancho,
            fondo          = :fondo,
            alto           = :alto,
            peso           = :peso,
            clasificacion  = :clasificacion
         WHERE id = :id'
    );
    $stmt->execute([
        ':numero_parte'   => $numero_parte,
        ':foto_producto'  => $foto_producto,
        ':foto_empaque'   => $foto_empaque,
        ':estandar_pack'  => $estandar_pack,
        ':niveles_pallet' => $niveles_pallet,
        ':cajas_nivel'    => $cajas_nivel,
        ':ancho'          => $ancho,
        ':fondo'          => $fondo,
        ':alto'           => $alto,
        ':peso'           => $peso,
        ':clasificacion'  => $clasificacion,
        ':id'             => $id,
    ]);
    $_SESSION['flash'] = [
        'type' => 'success',
        'msg'  => "Componente '{$numero_parte}' actualizado correctamente.",
    ];
} else {
    // INSERT
    $stmt = $pdo->prepare(
        'INSERT INTO componentes
            (numero_parte, foto_producto, foto_empaque,
             estandar_pack, niveles_pallet, cajas_nivel,
             ancho, fondo, alto, peso, clasificacion)
         VALUES
            (:numero_parte, :foto_producto, :foto_empaque,
             :estandar_pack, :niveles_pallet, :cajas_nivel,
             :ancho, :fondo, :alto, :peso, :clasificacion)'
    );
    $stmt->execute([
        ':numero_parte'   => $numero_parte,
        ':foto_producto'  => $foto_producto,
        ':foto_empaque'   => $foto_empaque,
        ':estandar_pack'  => $estandar_pack,
        ':niveles_pallet' => $niveles_pallet,
        ':cajas_nivel'    => $cajas_nivel,
        ':ancho'          => $ancho,
        ':fondo'          => $fondo,
        ':alto'           => $alto,
        ':peso'           => $peso,
        ':clasificacion'  => $clasificacion,
    ]);
    $_SESSION['flash'] = [
        'type' => 'success',
        'msg'  => "Componente '{$numero_parte}' agregado correctamente.",
    ];
}

header('Location: index.php');
exit;
