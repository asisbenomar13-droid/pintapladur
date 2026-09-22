<?php
// Recibe el formulario de presupuesto de pintareformas.es y lo envía por email.
// Respuesta JSON siempre. Exito: {"ok":true,"message":...}
// Error:  {"ok":false,"code":...,"error":...,"hint":...} con 405 / 422 / 502.
// El formulario de la web, si recibe ok:false, abre WhatsApp con los datos del cliente.
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok'      => false,
        'code'    => 'method_not_allowed',
        'error'   => 'Este endpoint solo acepta POST.',
        'hint'    => 'Envia los campos como application/x-www-form-urlencoded con POST.',
        'expects' => ['nombre', 'telefono', 'servicio', 'mensaje', 'email (opcional)', 'ciudad (opcional)', 'superficie (opcional)'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Honeypot anti-spam: el campo "web" está oculto; si viene relleno, es un bot.
if (!empty($_POST['web'])) {
    echo json_encode(['ok' => true]);
    exit;
}

function campo($k) {
    return trim(str_replace(["\r", "\n"], ' ', $_POST[$k] ?? ''));
}

$nombre   = campo('nombre');
$telefono = campo('telefono');
$email    = campo('email');
$servicio = campo('servicio');
$ciudad   = campo('ciudad');
$sup      = campo('superficie');
$mensaje  = trim($_POST['mensaje'] ?? '');

$obligatorios = ['nombre' => $nombre, 'telefono' => $telefono, 'servicio' => $servicio, 'mensaje' => $mensaje];
$faltan = array_keys(array_filter($obligatorios, function ($v) { return $v === ''; }));
if ($faltan) {
    http_response_code(422);
    echo json_encode([
        'ok'      => false,
        'code'    => 'missing_fields',
        'error'   => 'Faltan campos obligatorios: ' . implode(', ', $faltan) . '.',
        'missing' => array_values($faltan),
        'hint'    => 'Rellena esos campos y vuelve a enviar. Si prefieres, escribe por WhatsApp al +34633915898.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = '';
}

$cuerpo = "Nueva solicitud de presupuesto desde pintareformas.es\n\n"
    . "Nombre: $nombre\n"
    . "Teléfono: $telefono\n"
    . ($email ? "Email: $email\n" : '')
    . "Servicio: $servicio\n"
    . ($ciudad ? "Ciudad: $ciudad\n" : '')
    . ($sup ? "Superficie: $sup\n" : '')
    . "\nProyecto:\n$mensaje\n"
    . "\nFecha: " . date('d/m/Y H:i');

// El remite tiene que ser del propio dominio: el SPF de pintareformas.es
// autoriza al servidor de Hostinger. Con el remite viejo, spam seguro.
$headers = "From: PintaReformas Web <info@pintareformas.es>\r\n";
$headers .= "Cc: info@pintareformas.es\r\n";
if ($email) {
    $headers .= "Reply-To: $nombre <$email>\r\n";
}
$headers .= "Content-Type: text/plain; charset=utf-8\r\n";

$ok = mail('asisbenomar13@gmail.com', "Presupuesto web: $servicio — $nombre", $cuerpo, $headers);

// Copia de seguridad de cada lead en un log fuera de public_html
@file_put_contents(
    __DIR__ . '/../leads-pintareformas.log',
    date('c') . " | $nombre | $telefono | $email | $servicio | $ciudad | $sup | " . str_replace("\n", ' ', $mensaje) . "\n",
    FILE_APPEND | LOCK_EX
);

if ($ok) {
    echo json_encode(['ok' => true, 'message' => 'Solicitud recibida. Te contesto en breve.'], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(502);
    echo json_encode([
        'ok'    => false,
        'code'  => 'mail_failed',
        'error' => 'No se ha podido enviar el correo.',
        'hint'  => 'Escribe por WhatsApp al +34633915898 o llama al 633 915 898.',
    ], JSON_UNESCAPED_UNICODE);
}
