<?php
require_once '00_connect/pdo.php';

header('Content-Type: application/json');

try {
    $pdo = getConexion();
    $pdo->query('SELECT 1');

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'timestamp' => date('c')]);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Servicio no disponible']);
}
