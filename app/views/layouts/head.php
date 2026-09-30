<?php
/**
 * layouts/head.php  -  <head> común de todas las páginas.
 * Variables que recibe (opcionales):
 *   $tituloPagina  texto de la pestaña del navegador
 *   $estilos       arreglo de CSS extra, ej. ['css/login.css']
 *   $conBootstrap  true para cargar Bootstrap (explorador y modales)
 * Todas las librerías están EN LOCAL (public/plugins) para que funcione
 * dentro de la intranet aunque no haya salida a Internet.
 */
$config = Config::load();
$tituloPagina = $tituloPagina ?? $config->titulo_pagina;
$estilos = $estilos ?? [];
$conBootstrap = $conBootstrap ?? false;
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($config->titulo3) ?> - <?= e($tituloPagina) ?></title>
    <link rel="icon" href="<?= asset('img/inicio/cfe_logo.png') ?>">

    <?php if ($conBootstrap): ?>
        <link rel="stylesheet" href="<?= asset('plugins/bootstrap/bootstrap.min.css') ?>">
    <?php endif; ?>

    <!-- Estilos generales (header, cargador) -->
    <link rel="stylesheet" href="<?= asset('css/estilos.css?v=2') ?>">

    <?php foreach ($estilos as $css): ?>
        <link rel="stylesheet" href="<?= asset($css) ?>?v=2">
    <?php endforeach; ?>
</head>
