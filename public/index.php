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

$statusClasses = [
    'CONFIRMADO' => 'ok',
    'PENDIENTE' => 'wait',
    'REPROGRAMADO' => 'changed',
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clínica Santa Lucía — Mis turnos</title>
    <link rel="stylesheet" href="/assets/legacy.css">
    <link rel="stylesheet" href="/assets/turnos-v2.css?v=integrada">
</head>
<body>
    <div class="demo-banner">ENTORNO DE DEMOSTRACIÓN — TODOS LOS DATOS SON FICTICIOS</div>
    <header class="header">
        <span class="logo" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
        <div class="brand"><strong>CLÍNICA SANTA LUCÍA</strong><span>Sistema Integrado de Atención</span></div>
        <div class="user">Bienvenido/a, Paciente Demo<br><a href="#">Ayuda</a> · <a href="#">Salir</a></div>
    </header>
    <div class="layout">
        <nav class="nav" aria-label="Módulos principales">
            <h2>Menú principal</h2>
            <a href="#">Inicio</a>
            <a class="active" href="/turnos">Turnos ›</a>
            <a class="sub active-sub" href="/turnos">Mis próximos turnos</a>
            <span>Estudios <small>próximamente</small></span>
            <span>Historia clínica <small>próximamente</small></span>
            <span>Medicamentos <small>próximamente</small></span>
            <span>Cartilla médica <small>próximamente</small></span>
            <span>Facturación <small>próximamente</small></span>
        </nav>
        <main>
            <div class="breadcrumb">Inicio &gt; Gestión de turnos &gt; Mis próximos turnos</div>
            <h1>Mis próximos turnos</h1>
            <p class="subtitle">Consulte aquí las citas programadas en las distintas sedes de la Clínica.</p>
            <div class="notice"><strong>Importante:</strong> Preséntese 15 minutos antes con documento y credencial.</div>
            <section class="filters">
                <h2>Criterios de búsqueda</h2>
                <div>
                    <label>Desde <input value="20/10/2026" readonly></label>
                    <label>Hasta <input value="31/12/2026" readonly></label>
                    <label>Especialidad <select><option>Todas</option></select></label>
                    <button type="button">BUSCAR</button>
                </div>
            </section>
            <p class="summary"><strong>Turnos encontrados: <?= count($turnos) ?></strong><span>Última actualización: 20/10/2026 18:42</span></p>
            <section class="turnos-agenda" aria-label="Agenda de turnos">
                <?php foreach ($turnos as $turno): ?>
                    <article class="turno-card">
                        <div class="turno-card-heading">
                            <h2><?= e($turno['especialidad']) ?></h2>
                            <span class="status <?= $statusClasses[$turno['estado']] ?? '' ?>"><?= e($turno['estado']) ?></span>
                        </div>
                        <p class="turno-card-time"><?= e($turno['fecha']) ?> · <?= e($turno['hora']) ?> h</p>
                        <p class="turno-card-location"><?= e($turno['profesional']) ?> · <?= e($turno['sede']) ?></p>
                        <details>
                            <summary>Ver detalle</summary>
                            <p>Número de turno: <?= e($turno['id']) ?></p>
                        </details>
                    </article>
                <?php endforeach; ?>
                <?php if (!$turnos): ?>
                    <p>No hay turnos registrados.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <footer><span>Mesa de ayuda: 0800-000-0000</span><span><b>Sistema HSL v<?= e($version) ?></b> · Entorno <?= e($color) ?> · <strong>DEMO</strong></span></footer>
</body>
</html>
