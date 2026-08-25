<?php

function formatearUltimoAcceso(?string $ultimoAcceso, ?string $fechaCreacion): string {
    $valor = $ultimoAcceso ?? $fechaCreacion;
    if (!$valor) return '—';
    $ts = strtotime($valor);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}
