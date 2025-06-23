<?php
header('Content-Type: application/json');
echo json_encode([
    'message' => 'Will\'s Attic API Test',
    'php_version' => PHP_VERSION,
    'timestamp' => date('c')
]);
?>