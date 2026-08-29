<?php
/**
 * PFEP – Reusable site header (Littelfuse Matamoros layout)
 *
 * Before including this file, an including view may define:
 *   $active_page   string  Key of the active menu item (see $menu below).
 *   $page_subtitle string  Optional page-specific subtitle shown under the menu.
 */

$active_page   = $active_page   ?? '';
$page_subtitle = $page_subtitle ?? '';

$menu = [
    'index'     => ['label' => 'Home',                'url' => 'index.php'],
    'form'      => ['label' => 'Agregar Componente',  'url' => 'form.php'],
    'import'    => ['label' => 'Importar Demanda',    'url' => 'import.php'],
    'demanda'   => ['label' => 'Ver Demanda',         'url' => 'demanda.php'],
    'dashboard' => ['label' => 'Dashboard',           'url' => 'dashboard.php'],
];
?>
<header class="lf-header">
    <div class="lf-banner">
        <span class="lf-welcome">Bienvenido [<b>Usuario</b>] SysAdministrator!</span>
        <span class="lf-accent">|</span>
        <a href="index.php" class="lf-banner-link">Inicio</a>
        <span class="lf-accent">|</span>
    </div>

    <div class="lf-logobar">
        <a href="index.php" class="lf-logo-link">
            <img src="images/LFLogo_White.svg" alt="Littelfuse" class="lf-logo">
        </a>
        <span class="lf-portal-title">Littelfuse Matamoros PFEP&nbsp;&ndash;&nbsp;Catálogo para Plataforma</span>
    </div>

    <nav class="lf-menu">
        <ul>
            <?php foreach ($menu as $key => $item): ?>
                <li class="<?= $key === $active_page ? 'current' : '' ?>">
                    <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>">
                        <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</header>

<?php if ($page_subtitle !== ''): ?>
<div class="lf-page-subtitle"><?= htmlspecialchars($page_subtitle, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
