<?php

declare(strict_types=1);

$turnos = require dirname(__DIR__) . '/data/appointments.php';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$version = getenv('APP_VERSION') ?: 'dev';
$color = getenv('DEPLOYMENT_COLOR') ?: 'local';

if ($path === '/health/live' || $path === '/health/ready') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/version') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'version' => $version,
        'commitSha' => getenv('APP_COMMIT_SHA') ?: 'local',
        'buildTime' => getenv('APP_BUILD_TIME') ?: 'unknown',
        'deploymentColor' => $color,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($path === '/' || $path === '/turnos' || $path === '/api/turnos')
    && getenv('DEMO_FAILURE_MODE') === 'post-deploy') {
    $trigger = '/var/run/apache2/demo-failure-trigger';
    $delay = max(0, (int) (getenv('DEMO_FAILURE_AFTER_SECONDS') ?: 0));
    if (is_file($trigger) && time() - filemtime($trigger) >= $delay) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Falla funcional controlada de Turnos';
        exit;
    }
}

if ($path === '/api/turnos') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($turnos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($path !== '/' && $path !== '/turnos') {
    http_response_code(404);
    echo 'Página no encontrada';
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$statusClasses = ['CONFIRMADO' => 'confirmed', 'PENDIENTE' => 'pending', 'REPROGRAMADO' => 'changed'];
$months = ['01'=>'ene', '02'=>'feb', '03'=>'mar', '04'=>'abr', '05'=>'may', '06'=>'jun',
           '07'=>'jul', '08'=>'ago', '09'=>'sep', '10'=>'oct', '11'=>'nov', '12'=>'dic'];
?>
<!doctype html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis turnos · Clínica Santa Lucía</title>
    <link rel="stylesheet" href="/assets/turnos-v2.css">
</head>
<body>
<header><div class="wrap mast"><a class="brand" href="/turnos"><b aria-hidden="true">✚</b> Clínica Santa Lucía</a><span>Portal del paciente</span></div></header>
<main class="wrap">
    <h1>Mis turnos</h1>
    <p class="intro">Consultá el horario, la sede y el estado de tus citas.</p>
    <div class="agenda">
    <?php foreach ($turnos as $i => $turno):
        [$day, $month, $year] = explode('/', $turno['fecha']);
    ?>
        <article class="<?= $i === 0 ? 'featured' : 'card' ?>">
            <div class="topline"><span><?= $i === 0 ? 'Primer turno de la agenda' : e($turno['id']) ?></span>
            <span class="status <?= $statusClasses[$turno['estado']] ?? '' ?>"><?= e(ucfirst(strtolower($turno['estado']))) ?></span></div>
            <h2><?= e($turno['especialidad']) ?></h2>
            <p class="professional"><?= e($turno['profesional']) ?></p>
            <div class="when"><time datetime="<?= e("$year-$month-$day") ?>"><?= e($day . ' ' . ($months[$month] ?? $month) . ' ' . $year) ?></time><span><?= e($turno['hora']) ?> h</span></div>
            <p class="place"><?= e($turno['sede']) ?></p>
            <details><summary>Datos del turno</summary><p>Identificador: <?= e($turno['id']) ?><br>Presentate 15 minutos antes con documento y credencial.</p></details>
        </article>
    <?php endforeach; ?>
    <?php if (!$turnos): ?><p>No hay turnos registrados.</p><?php endif; ?>
    </div>
</main>
<footer class="wrap"><span>Demostración con datos ficticios</span><span>Versión <?= e($version) ?> · Entorno <?= e($color) ?></span></footer>
</body>
</html>
