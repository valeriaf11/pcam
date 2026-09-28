<?php
/**
 * layouts/cargador.php  -  Pantalla de "Procesando..." que se muestra mientras
 * se generan ZIPs, se suben archivos, etc.  En JS:  PCAM.cargando(true/false)
 * (antes usaba images/assets/demo_wait.gif que no existía; ahora es CSS puro)
 */
?>
<div id="wait" role="status" aria-live="polite">
    <div class="spinner"></div>
    <span id="wait_texto">Procesando...</span>
</div>
