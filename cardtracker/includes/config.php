<?php
/**
 * includes/config.php
 * Configuración central: conexión a la base, sesión segura y utilidades
 * que usan todas las páginas. Se incluye al principio de cada archivo.
 */

// --- Datos de conexión (un solo lugar para cambiarlos) ---------------------
const DB_HOST = 'localhost';
const DB_NAME = 'mi_banco_db';
const DB_USER = 'root';
const DB_PASS = '';

// Minutos de inactividad antes de cerrar la sesión automáticamente
const MINUTOS_INACTIVIDAD = 30;

// --- Sesión segura -----------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,                 // JavaScript no puede leer la cookie
        'samesite' => 'Lax',                // Mitiga ataques CSRF desde otros sitios
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

// Cierre por inactividad
if (isset($_SESSION['documento'], $_SESSION['ultimo_acceso'])
    && time() - $_SESSION['ultimo_acceso'] > MINUTOS_INACTIVIDAD * 60) {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['flash'][] = ['info', 'Tu sesión se cerró por inactividad. Volvé a ingresar.'];
}
$_SESSION['ultimo_acceso'] = time();

// --- Base de datos -------------------------------------------------------------
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            // El detalle técnico va al log del servidor, nunca a la pantalla
            error_log('CardTracker DB: ' . $e->getMessage());
            http_response_code(500);
            exit('No pudimos conectar con la base de datos. Intentá de nuevo en unos minutos.');
        }
    }
    return $pdo;
}

// --- Salida segura -------------------------------------------------------------
/** Escapa texto para imprimirlo en HTML (previene XSS). */
function e($valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

// --- Redirecciones y mensajes flash ---------------------------------------------
function redirigir(string $url): void
{
    header('Location: ' . $url);
    exit();
}

/** Guarda un mensaje para mostrarlo en la próxima página. Tipos: exito, error, info */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = [$tipo, $mensaje];
}

function obtener_flashes(): array
{
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensajes;
}

/** Recuerda lo que el usuario escribió en un formulario que falló. */
function recordar_datos(array $datos): void
{
    $_SESSION['viejos'] = $datos;
}

function datos_previos(): array
{
    $datos = $_SESSION['viejos'] ?? [];
    unset($_SESSION['viejos']);
    return $datos;
}

// --- Protección CSRF -----------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Corta el pedido si el formulario no trae el token correcto. */
function verificar_csrf(string $volverA): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        flash('error', 'El formulario venció. Volvé a enviarlo.');
        redirigir($volverA);
    }
}

// --- Autenticación y roles -----------------------------------------------------
function usuario_actual(): ?array
{
    if (!isset($_SESSION['documento'])) {
        return null;
    }
    return [
        'documento' => $_SESSION['documento'],
        'nombre'    => $_SESSION['nombre'],
        'apellido'  => $_SESSION['apellido'],
        'rol'       => $_SESSION['rol'],
    ];
}

function panel_de(string $rol): string
{
    return $rol === 'Administrador' ? 'admin.php' : 'generales.php';
}

/** Exige un rol. Si no hay sesión, va al ingreso; si el rol es otro, a su propio panel. */
function requerir_rol(string $rol): array
{
    $usuario = usuario_actual();
    if ($usuario === null) {
        flash('info', 'Ingresá a tu cuenta para continuar.');
        redirigir('index.php#ingresar');
    }
    if ($usuario['rol'] !== $rol) {
        redirigir(panel_de($usuario['rol']));
    }
    return $usuario;
}
