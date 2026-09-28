<?php
/**
 * layouts/header.php  -  Encabezado institucional (igual en login y explorador)
 *   [logo Gobierno de México/CFE]   [títulos]   [logo ZOTGM]
 * Los textos y colores se editan desde "Configuración General" (config.xml).
 * CORRECCIÓN: la versión anterior buscaba images/editable/logo1.svg que no
 * existe; se usan las imágenes reales de public/img/.
 */
$config = Config::load();
?>
<header id="header-pcam" style="background: <?= e($config->app_header_color_fondo) ?>">

    <div id="header-logo-izquierdo">
        <img src="<?= asset($config->logo_izquierdo) ?>" alt="Gobierno de México y CFE">
    </div>

    <div id="header-titulos" class="<?= $config->sombras_texto === '1' ? 'con-sombra' : '' ?>">
        <h2 style="color: <?= e($config->titulo1_color) ?>"><?= e($config->titulo1) ?></h2>
        <h3 style="color: <?= e($config->titulo2_color) ?>"><?= e($config->titulo2) ?></h3>
        <h1 style="color: <?= e($config->titulo3_color) ?>"><?= e($config->titulo3) ?></h1>
    </div>

    <div id="header-logo-derecho">
        <img src="<?= asset($config->logo_derecho) ?>" alt="ZOTGM">
    </div>

</header>
