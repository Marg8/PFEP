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
/*  Upload a photo into uploads/photos/ with a record-linked name.     */
/*  Returns the stored relative path (e.g. "photos/1042_...") on        */
/*  success, null when no file was sent, or false on failure           */
/*  (sets $error by reference).                                        */
/* ------------------------------------------------------------------ */
function upload_foto(string $field, int $record_id, string &$error): string|false|null {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // no new file – keep existing
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Error al subir el archivo del campo '{$field}' (código {$file['error']}).";
        return false;
    }

    // Validate extension (allow only jpg, jpeg, png, webp)
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) {
        $error = "El archivo '{$field}' debe ser JPG, JPEG, PNG o WEBP.";
        return false;
    }

    // Validate MIME type
    $allowed_mime = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo        = finfo_open(FILEINFO_MIME_TYPE);
    $mime         = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_mime, true)) {
        $error = "El archivo '{$field}' no es una imagen válida (JPG, PNG o WEBP).";
        return false;
    }

    // Max 10 MB
    if ($file['size'] > 10 * 1024 * 1024) {
        $error = "El archivo '{$field}' supera el límite de 10 MB.";
        return false;
    }

    // Ensure the target directory exists (create it with write permissions)
    $upload_dir = __DIR__ . '/uploads/photos';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0775, true) && !is_dir($upload_dir)) {
        $error = "No se pudo crear el directorio de imágenes 'uploads/photos'.";
        return false;
    }

    // Structured name: {record_id}_{field}_{original_or_timestamp}.{ext}
    $base = preg_replace('/[^a-zA-Z0-9_-]+/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    $base = trim((string)$base, '_');
    if ($base === '') {
        $base = date('YmdHis');
    }

    $filename = "{$record_id}_{$field}_{$base}.{$ext}";
    $dest     = $upload_dir . '/' . $filename;

    // Avoid clobbering an existing file that shares the same name
    if (is_file($dest)) {
        $filename = "{$record_id}_{$field}_{$base}_" . time() . ".{$ext}";
        $dest     = $upload_dir . '/' . $filename;
    }

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $error = "No se pudo guardar la imagen del campo '{$field}'.";
        return false;
    }

    // Stored value is relative to uploads/ so foto_url() keeps working
    return 'photos/' . $filename;
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
/*  Current photo values (may be replaced by new uploads below)        */
/* ------------------------------------------------------------------ */

$foto_producto = $existing['foto_producto'] ?? null;
$foto_empaque  = $existing['foto_empaque']  ?? null;
$upload_error  = '';

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
/*  Persist record + photos inside a transaction.                      */
/*  New rows are inserted first so we have the record_id used to name   */
/*  the files; if any image fails to save the transaction is rolled     */
/*  back so no orphaned record (or file) is left behind.               */
/* ------------------------------------------------------------------ */

$moved_files   = []; // files written this request (cleaned up on failure)
$old_to_delete = []; // replaced files, removed only after a good commit

try {
    $pdo->beginTransaction();

    if ($id > 0) {
        $record_id = $id;
    } else {
        // Create the row first to obtain the id used in the file names
        $ins = $pdo->prepare('INSERT INTO componentes (numero_parte) VALUES (:numero_parte)');
        $ins->execute([':numero_parte' => $numero_parte]);
        $record_id = (int)$pdo->lastInsertId();
    }

    // Product photo
    $result = upload_foto('foto_producto', $record_id, $upload_error);
    if ($result === false) {
        throw new RuntimeException($upload_error);
    }
    if ($result !== null) {
        if ($foto_producto) $old_to_delete[] = $foto_producto;
        $foto_producto = $result;
        $moved_files[] = $result;
    }

    // Packaging photo
    $result = upload_foto('foto_empaque', $record_id, $upload_error);
    if ($result === false) {
        throw new RuntimeException($upload_error);
    }
    if ($result !== null) {
        if ($foto_empaque) $old_to_delete[] = $foto_empaque;
        $foto_empaque = $result;
        $moved_files[] = $result;
    }

    // Persist every field (covers both the new and the existing row)
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
        ':id'             => $record_id,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Remove files written this request so nothing is orphaned on disk
    foreach ($moved_files as $mf) {
        $p = __DIR__ . '/uploads/' . $mf;
        if (is_file($p)) {
            unlink($p);
        }
    }
    $_SESSION['form_errors'] = [$upload_error !== '' ? $upload_error : 'No se pudo guardar el componente.'];
    $_SESSION['form_old']    = $_POST;
    $redirect = $id > 0 ? "form.php?id={$id}" : 'form.php';
    header("Location: {$redirect}");
    exit;
}

// Remove replaced photos only after the record is safely committed
foreach ($old_to_delete as $old) {
    $p = __DIR__ . '/uploads/' . $old;
    if (is_file($p) && is_writable($p)) {
        unlink($p);
    }
}

$_SESSION['flash'] = [
    'type' => 'success',
    'msg'  => $id > 0
        ? "Componente '{$numero_parte}' actualizado correctamente."
        : "Componente '{$numero_parte}' agregado correctamente.",
];

header('Location: index.php');
exit;
