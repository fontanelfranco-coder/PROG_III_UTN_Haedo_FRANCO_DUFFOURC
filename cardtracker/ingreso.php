<?php
// ingreso.php — Procesa el formulario de ingreso (no tiene pantalla propia)
require 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#ingresar');
}
verificar_csrf('index.php#ingresar');

const MAX_INTENTOS = 5;
const SEGUNDOS_BLOQUEO = 120;

/** Vuelve al formulario con un error, recordando lo que escribió (menos la contraseña). */
function fallar(string $mensaje): void
{
    flash('error', $mensaje);
    recordar_datos([
        'tipo_doc'  => $_POST['tipo_doc'] ?? '',
        'documento' => $_POST['documento'] ?? '',
        'usuario'   => $_POST['usuario'] ?? '',
    ]);
    redirigir('index.php#ingresar');
}

// 1. Freno contra fuerza bruta: después de varios intentos fallidos se espera un rato
if (($_SESSION['bloqueo_hasta'] ?? 0) > time()) {
    $espera = $_SESSION['bloqueo_hasta'] - time();
    fallar("Hubo demasiados intentos fallidos. Esperá $espera segundos y volvé a probar.");
}

$tipo_doc  = $_POST['tipo_doc'] ?? '';
$documento = trim($_POST['documento'] ?? '');
$usuario   = trim($_POST['usuario'] ?? '');
$password  = $_POST['password'] ?? '';

if ($documento === '' || $usuario === '' || $password === '') {
    fallar('Completá documento, usuario y contraseña.');
}

// 2. Buscamos al usuario SOLO por sus datos de identificación...
$stmt = db()->prepare(
    'SELECT documento, nombre, apellido, rol, password_hash FROM usuarios
     WHERE tipo_doc = :tipo AND documento = :doc AND usuario = :usu'
);
$stmt->execute([':tipo' => $tipo_doc, ':doc' => $documento, ':usu' => $usuario]);
$encontrado = $stmt->fetch();

// 3. ...y la contraseña se compara contra el hash con password_verify()
if (!$encontrado || !password_verify($password, $encontrado['password_hash'])) {
    $_SESSION['intentos'] = ($_SESSION['intentos'] ?? 0) + 1;
    if ($_SESSION['intentos'] >= MAX_INTENTOS) {
        $_SESSION['bloqueo_hasta'] = time() + SEGUNDOS_BLOQUEO;
        $_SESSION['intentos'] = 0;
    }
    // Mensaje genérico: no revelamos si falló el usuario o la contraseña
    fallar('Los datos no coinciden. Revisá documento, usuario y contraseña.');
}

// 4. Si PHP mejoró su algoritmo de hash, actualizamos el guardado
if (password_needs_rehash($encontrado['password_hash'], PASSWORD_DEFAULT)) {
    db()->prepare('UPDATE usuarios SET password_hash = :h WHERE documento = :doc')
        ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':doc' => $encontrado['documento']]);
}

// 5. Ingreso correcto: nuevo ID de sesión (evita el secuestro de sesión)
session_regenerate_id(true);
unset($_SESSION['intentos'], $_SESSION['bloqueo_hasta'], $_SESSION['csrf']);

$_SESSION['documento'] = $encontrado['documento'];
$_SESSION['nombre']    = $encontrado['nombre'];
$_SESSION['apellido']  = $encontrado['apellido'];
$_SESSION['rol']       = $encontrado['rol'];

redirigir(panel_de($encontrado['rol']));
