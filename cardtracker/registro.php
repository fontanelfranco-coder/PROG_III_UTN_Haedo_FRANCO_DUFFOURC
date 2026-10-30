<?php
// registro.php — Alta de usuarios web (reemplaza a Registro.html + altas.php)
require 'includes/config.php';
require 'includes/datos.php';
require 'includes/layout.php';

if ($u = usuario_actual()) {
    redirigir(panel_de($u['rol']));
}

$errores = [];
$d = [   // valores del formulario
    'tipo_doc' => 'DNI', 'documento' => '', 'nombre' => '', 'apellido' => '',
    'fecha_nacimiento' => '', 'email' => '', 'usuario' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf('registro.php');

    foreach ($d as $campo => $valor) {
        $d[$campo] = trim((string)($_POST[$campo] ?? ''));
    }
    $passA = $_POST['passwordA'] ?? '';
    $passB = $_POST['passwordB'] ?? '';

    // --- Validaciones del lado del servidor (el HTML se puede saltear) ---
    if (!in_array($d['tipo_doc'], TIPOS_DOC, true)) {
        $errores['tipo_doc'] = 'Elegí un tipo de documento válido.';
    }
    $patronDoc = $d['tipo_doc'] === 'DNI' ? '/^\d{7,8}$/' : '/^[A-Za-z0-9]{6,12}$/';
    if (!preg_match($patronDoc, $d['documento'])) {
        $errores['documento'] = $d['tipo_doc'] === 'DNI'
            ? 'El DNI tiene 7 u 8 números, sin puntos.'
            : 'El pasaporte tiene entre 6 y 12 letras o números.';
    }
    if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 50) {
        $errores['nombre'] = 'Ingresá tu nombre (hasta 50 caracteres).';
    }
    if ($d['apellido'] === '' || mb_strlen($d['apellido']) > 50) {
        $errores['apellido'] = 'Ingresá tu apellido (hasta 50 caracteres).';
    }
    $nacimiento = DateTime::createFromFormat('Y-m-d', $d['fecha_nacimiento']);
    if (!$nacimiento || $nacimiento->format('Y-m-d') !== $d['fecha_nacimiento']) {
        $errores['fecha_nacimiento'] = 'Ingresá una fecha válida.';
    } elseif ($nacimiento->diff(new DateTime('today'))->y < 18) {
        $errores['fecha_nacimiento'] = 'Tenés que ser mayor de 18 años.';
    }
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($d['email']) > 100) {
        $errores['email'] = 'Ingresá un correo válido.';
    }
    if (!preg_match('/^[A-Za-z0-9._]{4,30}$/', $d['usuario'])) {
        $errores['usuario'] = 'Entre 4 y 30 caracteres: letras, números, punto o guion bajo.';
    }
    if (strlen($passA) < 8 || !preg_match('/[A-Za-z]/', $passA) || !preg_match('/\d/', $passA)) {
        $errores['passwordA'] = 'Usá al menos 8 caracteres, con letras y números.';
    } elseif ($passA !== $passB) {
        $errores['passwordB'] = 'Las contraseñas no coinciden.';
    }

    // --- Duplicados ---
    if (!$errores) {
        $chequeos = [
            'documento' => ['SELECT 1 FROM usuarios WHERE documento = ?', 'Ya existe una cuenta con ese documento.'],
            'email'     => ['SELECT 1 FROM usuarios WHERE email = ?', 'Ese correo ya está registrado.'],
            'usuario'   => ['SELECT 1 FROM usuarios WHERE usuario = ?', 'Ese nombre de usuario ya está en uso.'],
        ];
        foreach ($chequeos as $campo => [$sql, $msg]) {
            $st = db()->prepare($sql);
            $st->execute([$d[$campo]]);
            if ($st->fetchColumn()) {
                $errores[$campo] = $msg;
            }
        }
    }

    // --- Alta ---
    if (!$errores) {
        $st = db()->prepare(
            'INSERT INTO usuarios (documento, tipo_doc, nombre, apellido, fecha_nacimiento, email, usuario, password_hash, rol)
             VALUES (:doc, :tipo, :nom, :ape, :nac, :email, :usu, :hash, \'General\')'   // el rol nunca viene del formulario
        );
        $st->execute([
            ':doc' => $d['documento'], ':tipo' => $d['tipo_doc'], ':nom' => $d['nombre'],
            ':ape' => $d['apellido'], ':nac' => $d['fecha_nacimiento'], ':email' => $d['email'],
            ':usu' => $d['usuario'], ':hash' => password_hash($passA, PASSWORD_DEFAULT),
        ]);
        flash('exito', '¡Listo, ' . $d['nombre'] . '! Tu cuenta fue creada. Ya podés ingresar.');
        recordar_datos(['tipo_doc' => $d['tipo_doc'], 'documento' => $d['documento'], 'usuario' => $d['usuario']]);
        redirigir('index.php#ingresar');
    }
}

/** Mensaje de error debajo de un campo */
function error_de(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? '<span class="campo__error">' . e($errores[$campo]) . '</span>' : '';
}

encabezado('Crear cuenta');
?>

<div class="angosto">
    <div class="cabecera-pagina">
        <div>
            <h1>Creá tu cuenta</h1>
            <p>Con tu cuenta vas a poder cargar tarjetas, registrar resúmenes y recibir avisos de vencimiento.</p>
        </div>
    </div>

    <?php if ($errores): ?>
        <div class="mensaje mensaje--error" role="alert" style="margin-bottom:1.25rem">
            <?= icono('alerta') ?><p>Revisá los campos marcados para continuar.</p>
        </div>
    <?php endif; ?>

    <form method="POST" action="registro.php" class="panel panel--formulario formulario" novalidate>
        <?= campo_csrf() ?>
        <h2>Tus datos</h2>
        <div class="fila">
            <div class="campo">
                <label for="tipo_doc">Tipo de documento</label>
                <select name="tipo_doc" id="tipo_doc">
                    <option value="DNI" <?= $d['tipo_doc'] === 'DNI' ? 'selected' : '' ?>>DNI</option>
                    <option value="PASAPORTE" <?= $d['tipo_doc'] === 'PASAPORTE' ? 'selected' : '' ?>>Pasaporte</option>
                </select>
            </div>
            <div class="campo">
                <label for="documento">Número de documento</label>
                <input type="text" name="documento" id="documento" required maxlength="12" value="<?= e($d['documento']) ?>">
                <?= error_de($errores, 'documento') ?>
            </div>
        </div>
        <div class="fila">
            <div class="campo">
                <label for="nombre">Nombre</label>
                <input type="text" name="nombre" id="nombre" required maxlength="50" autocomplete="given-name" value="<?= e($d['nombre']) ?>">
                <?= error_de($errores, 'nombre') ?>
            </div>
            <div class="campo">
                <label for="apellido">Apellido</label>
                <input type="text" name="apellido" id="apellido" required maxlength="50" autocomplete="family-name" value="<?= e($d['apellido']) ?>">
                <?= error_de($errores, 'apellido') ?>
            </div>
        </div>
        <div class="fila">
            <div class="campo">
                <label for="fecha_nacimiento">Fecha de nacimiento</label>
                <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required value="<?= e($d['fecha_nacimiento']) ?>">
                <?= error_de($errores, 'fecha_nacimiento') ?>
            </div>
            <div class="campo">
                <label for="email">Correo electrónico</label>
                <input type="email" name="email" id="email" required maxlength="100" autocomplete="email" value="<?= e($d['email']) ?>">
                <?= error_de($errores, 'email') ?>
            </div>
        </div>

        <hr class="separador">
        <h2>Tu acceso</h2>
        <div class="campo">
            <label for="usuario">Nombre de usuario</label>
            <input type="text" name="usuario" id="usuario" required maxlength="30" autocomplete="username" value="<?= e($d['usuario']) ?>">
            <?= error_de($errores, 'usuario') ?: '<span class="campo__ayuda">Lo vas a usar junto con tu documento para ingresar.</span>' ?>
        </div>
        <div class="fila">
            <div class="campo">
                <label for="passwordA">Contraseña</label>
                <input type="password" name="passwordA" id="passwordA" required minlength="8" autocomplete="new-password">
                <?= error_de($errores, 'passwordA') ?: '<span class="campo__ayuda">Al menos 8 caracteres, con letras y números.</span>' ?>
            </div>
            <div class="campo">
                <label for="passwordB">Repetí la contraseña</label>
                <input type="password" name="passwordB" id="passwordB" required minlength="8" autocomplete="new-password">
                <?= error_de($errores, 'passwordB') ?>
            </div>
        </div>

        <button type="submit" class="boton boton--primario boton--bloque">Crear cuenta</button>
        <div class="enlaces-formulario" style="margin-top:0">
            <a href="index.php#ingresar">¿Ya tenés cuenta? Ingresá</a>
        </div>
    </form>
</div>

<?php pie(); ?>
