<?php
$GLOBALS['__metr_inicio'] = microtime(true);

register_shutdown_function(function () {
    try {
        $duracion_ms = (int)round((microtime(true) - $GLOBALS['__metr_inicio']) * 1000);
        $usua_id     = $_SESSION['usua_id'] ?? null;
        $http_status = http_response_code();
        $endpoint    = substr($_SERVER['REQUEST_URI'] ?? '', 0, 255);

        require_once __DIR__ . '/pdo.php';
        $pdo = getConexion();
        $stmt = $pdo->prepare("
            INSERT INTO metricasdesempeno (metr_endpoint, metr_duracion_ms, metr_usua_id, metr_http_status)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$endpoint, $duracion_ms, $usua_id, $http_status]);
    } catch (Throwable $e) {
        // Silencioso a propósito: un fallo al registrar la métrica nunca
        // debe afectar la respuesta ya enviada al usuario.
    }
});
