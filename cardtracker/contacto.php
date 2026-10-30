<?php
// contacto.php — Formulario de consulta + mapa. Abierto a visitantes y a clientes.
require 'includes/config.php';
require 'includes/datos.php';
require 'includes/layout.php';

$yo = usuario_actual();
if ($yo && $yo['rol'] === 'Administrador') {
    redirigir('admin_consultas.php');   // el admin responde consultas, no las envía
}
$esCliente = $yo !== null;

// Si es cliente, sus datos salen de la base y no se pueden editar
$nombre = $email = '';
if ($esCliente) {
    $st = db()->prepare('SELECT nombre, apellido, email FROM usuarios WHERE documento = :doc');
    $st->execute([':doc' => $yo['documento']]);
    $u = $st->fetch();
    $nombre = $u['nombre'] . ' ' . $u['apellido'];
    $email = $u['email'];
}

$categorias = [
    'Alta'    => 'Quiero agregar una tarjeta o un banco nuevo',
    'Baja'    => 'Quiero dar de baja una tarjeta o mi cuenta',
    'Mensaje' => 'Otra consulta',
];
$errores = [];
$categoria = $_GET['motivo'] ?? '';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf('contacto.php');

    // Campo trampa: los humanos no lo ven, los robots de spam lo completan
    if (!empty($_POST['sitio_web'])) {
        redirigir('contacto.php');
    }

    if (!$esCliente) {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($nombre === '' || mb_strlen($nombre) > 100) $errores[] = 'Ingresá tu nombre.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) $errores[] = 'Ingresá un correo válido para poder responderte.';
    }
    $categoria = $_POST['categoria'] ?? '';
    $mensaje = trim($_POST['mensaje'] ?? '');
    if (!array_key_exists($categoria, $categorias)) $errores[] = 'Elegí el motivo de la consulta.';
    if (mb_strlen($mensaje) < 10 || mb_strlen($mensaje) > 2000) $errores[] = 'Escribí tu consulta (entre 10 y 2000 caracteres).';

    // Límite simple: una consulta cada 30 segundos por sesión
    if (!$errores && ($_SESSION['ultima_consulta'] ?? 0) > time() - 30) {
        $errores[] = 'Acabás de enviar una consulta. Esperá unos segundos antes de mandar otra.';
    }

    if (!$errores) {
        db()->prepare('INSERT INTO consultas (nombre, email, categoria, mensaje) VALUES (:n, :e, :c, :m)')
            ->execute([':n' => $nombre, ':e' => $email, ':c' => $categoria, ':m' => $mensaje]);
        $_SESSION['ultima_consulta'] = time();

        // Aviso por mail al equipo (en localhost no sale sin un servidor SMTP configurado)
        $asunto = 'Nueva consulta web: ' . $categoria;
        $cuerpo = "De: $nombre <$email>\n\n$mensaje";
        @mail('admin@cardtracker.com', $asunto, $cuerpo, 'Reply-To: ' . str_replace(["\r", "\n"], '', $email));

        flash('exito', 'Recibimos tu consulta. Te respondemos a ' . $email . ' en menos de 48 horas hábiles.');
        redirigir('contacto.php');
    }
}

encabezado('Contacto', 'contacto');
?>

<div class="cabecera-pagina">
    <div>
        <h1>Contacto</h1>
        <p>¿Una duda, un banco que no está en la lista o un problema para ingresar? Escribinos.</p>
    </div>
</div>

<div class="contacto">
    <section class="panel panel--formulario">
        <h2>Envianos tu consulta</h2>

        <?php if ($errores): ?>
            <div class="mensajes">
                <?php foreach ($errores as $err): ?>
                    <div class="mensaje mensaje--error" role="alert"><?= icono('alerta') ?><p><?= e($err) ?></p></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="contacto.php" class="formulario">
            <?= campo_csrf() ?>
            <div class="trampa" aria-hidden="true">
                <label for="sitio_web">No completar</label>
                <input type="text" name="sitio_web" id="sitio_web" tabindex="-1" autocomplete="off">
            </div>

            <?php if ($esCliente): ?>
                <p class="chico texto-suave" style="margin:0">Respondemos a <strong><?= e($email) ?></strong>, el correo de tu cuenta.</p>
            <?php else: ?>
                <div class="fila">
                    <div class="campo">
                        <label for="nombre">Nombre y apellido</label>
                        <input type="text" name="nombre" id="nombre" required maxlength="100" autocomplete="name" value="<?= e($nombre) ?>">
                    </div>
                    <div class="campo">
                        <label for="email">Correo electrónico</label>
                        <input type="email" name="email" id="email" required maxlength="100" autocomplete="email" value="<?= e($email) ?>">
                    </div>
                </div>
            <?php endif; ?>

            <div class="campo">
                <label for="categoria">Motivo</label>
                <select name="categoria" id="categoria" required>
                    <option value="" disabled <?= $categoria === '' ? 'selected' : '' ?>>Elegí una opción</option>
                    <?php foreach ($categorias as $valor => $texto): ?>
                        <option value="<?= $valor ?>" <?= $categoria === $valor ? 'selected' : '' ?>><?= e($texto) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label for="mensaje">Tu consulta</label>
                <textarea name="mensaje" id="mensaje" required minlength="10" maxlength="2000"
                          placeholder="Contanos qué necesitás. Si es sobre una tarjeta, indicá el banco y los últimos 4 dígitos."><?= e($mensaje) ?></textarea>
                <span class="campo__ayuda">Nunca te vamos a pedir el número completo de tu tarjeta ni tu contraseña.</span>
            </div>

            <button type="submit" class="boton boton--primario boton--bloque"><?= icono('correo') ?>Enviar consulta</button>
        </form>
    </section>

    <section class="mapa">
        <div class="panel">
            <h2>Dónde estamos</h2>
            <ul class="datos-contacto">
                <li><?= icono('correo') ?>soporte@cardtracker.com</li>
                <li><?= icono('reloj') ?>Lunes a viernes, de 9 a 18 h</li>
                <li><?= icono('ubicacion') ?>Av. 9 de Julio s/n, Ciudad Autónoma de Buenos Aires</li>
            </ul>
        </div>
        <div class="mapa__marco">
            <iframe title="Mapa de nuestras oficinas en Buenos Aires"
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3284.016713276848!2d-58.38414532328103!3d-34.6037388729543!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x95bccacf7a4d5b29%3A0xc316c0201d4a04b!2sObelisco!5e0!3m2!1ses-419!2sar!4v1700000000000!5m2!1ses-419!2sar"
                    allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </section>
</div>

<?php pie(); ?>
