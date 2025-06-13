<?php
// functions.php
function formatoMoneda($valor, $moneda = 'USD') {
    return number_format($valor, 2, '.', ',') . ' ' . $moneda;
}
?>
// Otras funciones útiles...