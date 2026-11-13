<?php
// Préfixé à chaque requête du serveur de test (php -d auto_prepend_file=export_prepend.php -S ...) :
// consigne, pour chaque page, la durée côté serveur et le pic de mémoire PHP dans le fichier
// désigné par EXPORT_BENCH_LOG (une ligne JSON par requête). Utilisé par export_bench.py.
$exportbenchstart = microtime(true);
register_shutdown_function(function () use ($exportbenchstart) {
    $log = getenv('EXPORT_BENCH_LOG');
    if ($log) {
        file_put_contents($log, json_encode([
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'seconds' => round(microtime(true) - $exportbenchstart, 3),
            'peakmb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ]) . "\n", FILE_APPEND | LOCK_EX);
    }
});
