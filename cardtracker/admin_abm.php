<?php
// admin_abm.php — ABM del Administrador: usuarios y tarjetas
require 'includes/config.php';
require 'includes/datos.php';
require 'includes/layout.php';

$yo = requerir_rol('Administrador');
$seccion = ($_GET['seccion'] ?? $_POST['seccion'] ?? 'usuarios') === 'tarjetas' ? 'tarjetas' : 'usuarios';
$aqui = 'admin_abm.php?seccion=' . $seccion;

/** Ejecuta una consulta preparada y devuelve el statement */
function consulta(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

// =====================================================================
// ACCIONES
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf($aqui);
    $accion = $_POST['accion'] ?? '';

    // A dónde volver si falla el guardado (para no perder el formulario abierto)
    $destinoError = $aqui;
    if ($accion === 'guardar_usuario') {
        $destinoError .= ($_POST['modo'] ?? '') === 'nuevo' ? '&nuevo=1' : '&editar=' . urlencode($_POST['documento'] ?? '');
    } elseif ($accion === 'guardar_tarjeta') {
        $destinoError .= (int)($_POST['id_tarjeta'] ?? 0) ? '&editar=' . (int)$_POST['id_tarjeta'] : '&nuevo=1';
    }

    try {
        // ---------------- USUARIOS ----------------
        if ($accion === 'guardar_usuario') {
            $esNuevo = ($_POST['modo'] ?? '') === 'nuevo';
            $u = [
                'documento' => trim($_POST['documento'] ?? ''),
                'tipo_doc'  => $_POST['tipo_doc'] ?? '',
                'nombre'    => trim($_POST['nombre'] ?? ''),
                'apellido'  => trim($_POST['apellido'] ?? ''),
                'email'     => trim($_POST['email'] ?? ''),
                'usuario'   => trim($_POST['usuario'] ?? ''),
                'rol'       => ($_POST['rol'] ?? '') === 'Administrador' ? 'Administrador' : 'General',
                'nacimiento'=> $_POST['fecha_nacimiento'] ?? '',
            ];
            $pass = $_POST['password'] ?? '';

            $errores = [];
            if (!in_array($u['tipo_doc'], TIPOS_DOC, true))           $errores[] = 'Tipo de documento inválido.';
            if (!preg_match('/^[A-Za-z0-9]{6,12}$/', $u['documento'])) $errores[] = 'El documento debe tener entre 6 y 12 letras o números.';
            if ($u['nombre'] === '' || $u['apellido'] === '')          $errores[] = 'Completá nombre y apellido.';
            if (!filter_var($u['email'], FILTER_VALIDATE_EMAIL))       $errores[] = 'Correo inválido.';
            if (!preg_match('/^[A-Za-z0-9._]{4,30}$/', $u['usuario'])) $errores[] = 'Usuario: entre 4 y 30 letras, números, punto o guion bajo.';
            if ($esNuevo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $u['nacimiento'])) $errores[] = 'Ingresá la fecha de nacimiento.';
            if (($esNuevo || $pass !== '') && (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass))) {
                $errores[] = 'La contraseña necesita al menos 8 caracteres, con letras y números.';
            }
            // Un administrador no puede quitarse a sí mismo el rol (evita quedarse sin acceso)
            if (!$esNuevo && $u['documento'] === $yo['documento'] && $u['rol'] !== 'Administrador') {
                $errores[] = 'No podés quitarte tu propio rol de administrador.';
            }

            if ($errores) {
                foreach ($errores as $err) flash('error', $err);
                recordar_datos(array_diff_key($_POST, ['password' => 1, 'csrf' => 1]));   // la contraseña no se guarda
                redirigir($aqui . ($esNuevo ? '&nuevo=1' : '&editar=' . urlencode($u['documento'])));
            }

            if ($esNuevo) {
                consulta('INSERT INTO usuarios (documento, tipo_doc, nombre, apellido, fecha_nacimiento, email, usuario, password_hash, rol)
                          VALUES (:doc, :tipo, :nom, :ape, :nac, :email, :usu, :hash, :rol)', [
                    ':doc' => $u['documento'], ':tipo' => $u['tipo_doc'], ':nom' => $u['nombre'], ':ape' => $u['apellido'],
                    ':nac' => $u['nacimiento'], ':email' => $u['email'], ':usu' => $u['usuario'],
                    ':hash' => password_hash($pass, PASSWORD_DEFAULT), ':rol' => $u['rol'],
                ]);
                flash('exito', 'Creaste la cuenta de ' . $u['nombre'] . ' ' . $u['apellido'] . '.');
            } else {
                consulta('UPDATE usuarios SET tipo_doc = :tipo, nombre = :nom, apellido = :ape, email = :email,
                          usuario = :usu, rol = :rol WHERE documento = :doc', [
                    ':tipo' => $u['tipo_doc'], ':nom' => $u['nombre'], ':ape' => $u['apellido'],
                    ':email' => $u['email'], ':usu' => $u['usuario'], ':rol' => $u['rol'], ':doc' => $u['documento'],
                ]);
                // La contraseña solo se reemplaza si se escribió una nueva (nunca se muestra la anterior)
                if ($pass !== '') {
                    consulta('UPDATE usuarios SET password_hash = :h WHERE documento = :doc',
                             [':h' => password_hash($pass, PASSWORD_DEFAULT), ':doc' => $u['documento']]);
                }
                flash('exito', 'Guardaste los cambios de ' . $u['nombre'] . ' ' . $u['apellido'] . '.');
            }
        }

        if ($accion === 'eliminar_usuario') {
            $doc = $_POST['documento'] ?? '';
            if ($doc === $yo['documento']) {
                flash('error', 'No podés eliminar tu propia cuenta.');
            } else {
                $st = consulta('DELETE FROM usuarios WHERE documento = :doc', [':doc' => $doc]);
                flash($st->rowCount() ? 'exito' : 'error',
                      $st->rowCount() ? 'Eliminaste el usuario junto con sus tarjetas y resúmenes.' : 'El usuario no existe.');
            }
        }

        // ---------------- TARJETAS ----------------
        if ($accion === 'guardar_tarjeta') {
            $id = (int)($_POST['id_tarjeta'] ?? 0);
            $t = [
                ':dni'    => trim($_POST['dni_titular'] ?? ''),
                ':banco'  => $_POST['banco_emisor'] ?? '',
                ':red'    => $_POST['red'] ?? '',
                ':u4'     => trim($_POST['ultimos4'] ?? ''),
                ':estado' => $_POST['estado'] ?? '',
            ];
            $errores = [];
            $titular = consulta("SELECT rol FROM usuarios WHERE documento = :d", [':d' => $t[':dni']])->fetchColumn();
            if ($titular !== 'General')                       $errores[] = 'El documento no corresponde a ningún cliente.';
            if (!in_array($t[':banco'], BANCOS, true))         $errores[] = 'Banco inválido.';
            if (!in_array($t[':red'], REDES, true))            $errores[] = 'Red inválida.';
            if (!preg_match('/^\d{4}$/', $t[':u4']))           $errores[] = 'Ingresá exactamente 4 dígitos.';
            if (!in_array($t[':estado'], ['Activa', 'Inactiva', 'Bloqueada'], true)) $errores[] = 'Estado inválido.';

            if ($errores) {
                foreach ($errores as $err) flash('error', $err);
                recordar_datos($_POST);
                redirigir($aqui . ($id ? "&editar=$id" : '&nuevo=1'));
            }
            if ($id) {
                consulta('UPDATE tarjetas SET dni_titular = :dni, banco_emisor = :banco, red = :red, ultimos4 = :u4, estado = :estado
                          WHERE id_tarjeta = :id', $t + [':id' => $id]);
                flash('exito', 'Guardaste los cambios de la tarjeta terminada en ' . $t[':u4'] . '.');
            } else {
                consulta('INSERT INTO tarjetas (dni_titular, banco_emisor, red, ultimos4, estado)
                          VALUES (:dni, :banco, :red, :u4, :estado)', $t);
                flash('exito', 'Cargaste la tarjeta terminada en ' . $t[':u4'] . '.');
            }
        }

        if ($accion === 'bloqueo_tarjeta') {
            $id = (int)($_POST['id_tarjeta'] ?? 0);
            consulta("UPDATE tarjetas SET estado = IF(estado = 'Bloqueada', 'Activa', 'Bloqueada') WHERE id_tarjeta = :id", [':id' => $id]);
            flash('exito', 'Actualizaste el bloqueo de la tarjeta.');
        }

        if ($accion === 'eliminar_tarjeta') {
            $st = consulta('DELETE FROM tarjetas WHERE id_tarjeta = :id', [':id' => (int)($_POST['id_tarjeta'] ?? 0)]);
            flash($st->rowCount() ? 'exito' : 'error',
                  $st->rowCount() ? 'Eliminaste la tarjeta y sus resúmenes.' : 'La tarjeta no existe.');
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            flash('error', 'Ya existe un registro con esos datos (documento, correo, usuario o tarjeta repetidos).');
            recordar_datos(array_diff_key($_POST, ['password' => 1, 'csrf' => 1]));
        } else {
            error_log($e->getMessage());
            flash('error', 'No pudimos guardar los cambios.');
        }
        redirigir($destinoError);
    }
    redirigir($aqui);
}

// =====================================================================
// DATOS PARA LA VISTA
// =====================================================================
$q = trim(mb_substr($_GET['q'] ?? '', 0, 60));
$like = '%' . $q . '%';
$previos = datos_previos();
$nuevo = isset($_GET['nuevo']);

if ($seccion === 'usuarios') {
    // Columnas explícitas: nunca se trae password_hash ni fecha_nacimiento
    $lista = consulta("SELECT u.documento, u.tipo_doc, u.nombre, u.apellido, u.email, u.usuario, u.rol,
                              COUNT(t.id_tarjeta) AS tarjetas
                       FROM usuarios u LEFT JOIN tarjetas t ON t.dni_titular = u.documento
                       WHERE (:vacio = '' OR u.documento LIKE :q1 OR u.nombre LIKE :q2 OR u.apellido LIKE :q3
                              OR u.email LIKE :q4 OR u.usuario LIKE :q5)
                       GROUP BY u.documento ORDER BY u.rol DESC, u.apellido, u.nombre",
                      [':vacio' => $q, ':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $like])->fetchAll();

    $editando = null;
    if (isset($_GET['editar'])) {
        $editando = consulta('SELECT documento, tipo_doc, nombre, apellido, email, usuario, rol FROM usuarios WHERE documento = :d',
                             [':d' => $_GET['editar']])->fetch() ?: null;
    }
    $form = array_merge(['documento' => '', 'tipo_doc' => 'DNI', 'nombre' => '', 'apellido' => '', 'email' => '',
                         'usuario' => '', 'rol' => 'General', 'fecha_nacimiento' => ''],
                        $editando ?? [], array_diff_key($previos, ['password' => 1, 'csrf' => 1]));
    $mostrarForm = $nuevo || $editando;
} else {
    // Tarjetas: sin alias (es un dato personal del cliente)
    $lista = consulta("SELECT t.id_tarjeta, t.dni_titular, t.banco_emisor, t.red, t.ultimos4, t.estado,
                              u.nombre, u.apellido, COUNT(l.id_liquidacion) AS resumenes
                       FROM tarjetas t
                       INNER JOIN usuarios u ON u.documento = t.dni_titular
                       LEFT JOIN liquidaciones l ON l.id_tarjeta = t.id_tarjeta
                       WHERE (:vacio = '' OR t.dni_titular LIKE :q1 OR t.banco_emisor LIKE :q2 OR t.red LIKE :q3
                              OR t.ultimos4 LIKE :q4 OR u.apellido LIKE :q5 OR u.nombre LIKE :q6)
                       GROUP BY t.id_tarjeta ORDER BY u.apellido, t.banco_emisor",
                      [':vacio' => $q, ':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $like, ':q6' => $like])->fetchAll();

    $editando = null;
    if (isset($_GET['editar'])) {
        $editando = consulta('SELECT id_tarjeta, dni_titular, banco_emisor, red, ultimos4, estado FROM tarjetas WHERE id_tarjeta = :id',
                             [':id' => (int)$_GET['editar']])->fetch() ?: null;
    }
    $form = array_merge(['id_tarjeta' => 0, 'dni_titular' => $_GET['dni'] ?? '', 'banco_emisor' => 'Banco Galicia', 'red' => 'Visa',
                         'ultimos4' => '', 'estado' => 'Activa'],
                        $editando ?? [], array_diff_key($previos, ['csrf' => 1]));
    $mostrarForm = $nuevo || $editando;
    $clientes = consulta("SELECT documento, nombre, apellido FROM usuarios WHERE rol = 'General' ORDER BY apellido")->fetchAll();
}

encabezado('Usuarios y tarjetas', 'gestion');
?>

<div class="cabecera-pagina">
    <div>
        <h1>Usuarios y tarjetas</h1>
        <p>Altas, bajas y modificaciones. Las contraseñas nunca se muestran: solo se pueden reemplazar.</p>
    </div>
    <a class="boton boton--primario" href="<?= $aqui ?>&nuevo=1"><?= icono('mas') ?><?= $seccion === 'usuarios' ? 'Nuevo usuario' : 'Nueva tarjeta' ?></a>
</div>

<nav class="pestanas" aria-label="Secciones">
    <a href="admin_abm.php?seccion=usuarios" class="<?= $seccion === 'usuarios' ? 'es-activa' : '' ?>"><?= icono('usuarios') ?>Usuarios</a>
    <a href="admin_abm.php?seccion=tarjetas" class="<?= $seccion === 'tarjetas' ? 'es-activa' : '' ?>"><?= icono('tarjeta') ?>Tarjetas</a>
</nav>

<div class="<?= $mostrarForm ? 'dos-columnas' : '' ?>">
    <section>
        <form class="filtros" method="GET" role="search" style="grid-template-columns: 1fr auto">
            <input type="hidden" name="seccion" value="<?= $seccion ?>">
            <div class="campo">
                <label for="q">Buscar <?= $seccion === 'usuarios' ? 'usuario' : 'tarjeta' ?></label>
                <div class="buscador">
                    <?= icono('buscar') ?>
                    <input type="search" id="q" name="q" value="<?= e($q) ?>"
                           placeholder="<?= $seccion === 'usuarios' ? 'Documento, nombre, correo o usuario' : 'Documento, apellido, banco, red o dígitos' ?>">
                </div>
            </div>
            <div class="filtros__acciones">
                <button class="boton boton--primario boton--chico" type="submit">Buscar</button>
                <?php if ($q !== ''): ?><a class="boton boton--fantasma boton--chico" href="<?= $aqui ?>">Limpiar</a><?php endif; ?>
            </div>
        </form>

        <div class="resultado-info"><span><?= count($lista) ?> resultados</span></div>

        <?php if (!$lista): ?>
            <div class="vacio"><?= icono('buscar') ?><h3>Sin resultados</h3><p>Probá con otro término de búsqueda.</p></div>
        <?php elseif ($seccion === 'usuarios'): ?>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead><tr><th>Documento</th><th>Nombre</th><th>Correo</th><th>Usuario</th><th>Rol</th><th class="derecha">Tarjetas</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($lista as $u): ?>
                        <tr class="<?= $editando && $editando['documento'] === $u['documento'] ? 'es-seleccionada' : '' ?>">
                            <td class="numero"><?= e($u['tipo_doc'] . ' ' . $u['documento']) ?></td>
                            <td><strong><?= e($u['apellido'] . ', ' . $u['nombre']) ?></strong></td>
                            <td><?= e($u['email']) ?></td>
                            <td><?= e($u['usuario']) ?></td>
                            <td><span class="insignia <?= $u['rol'] === 'Administrador' ? 'insignia--admin' : '' ?>"><?= e($u['rol']) ?></span></td>
                            <td class="derecha numero">
                                <?php if ($u['rol'] === 'General'): ?>
                                    <a href="admin_abm.php?seccion=tarjetas&q=<?= urlencode($u['documento']) ?>"><?= (int)$u['tarjetas'] ?></a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="acciones">
                                    <a class="accion" href="<?= $aqui ?>&editar=<?= urlencode($u['documento']) ?>"><?= icono('editar') ?>Editar</a>
                                    <?php if ($u['documento'] !== $yo['documento']): ?>
                                        <form method="POST" onsubmit="return confirm('Se eliminará la cuenta de <?= e($u['nombre']) ?> con todas sus tarjetas y resúmenes. ¿Continuar?');">
                                            <?= campo_csrf() ?>
                                            <input type="hidden" name="seccion" value="usuarios">
                                            <input type="hidden" name="accion" value="eliminar_usuario">
                                            <input type="hidden" name="documento" value="<?= e($u['documento']) ?>">
                                            <button class="accion accion--peligro" type="submit" aria-label="Eliminar"><?= icono('borrar') ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead><tr><th>Tarjeta</th><th>Titular</th><th>Estado</th><th class="derecha">Resúmenes</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($lista as $t): ?>
                        <tr class="<?= $editando && (int)$editando['id_tarjeta'] === (int)$t['id_tarjeta'] ? 'es-seleccionada' : '' ?>">
                            <td><?= mini_tarjeta(['alias' => null] + $t, $t['red'] . ' terminada en ' . $t['ultimos4']) ?></td>
                            <td><strong><?= e($t['apellido'] . ', ' . $t['nombre']) ?></strong><br><span class="chico texto-suave numero">DNI <?= e($t['dni_titular']) ?></span></td>
                            <td><span class="insignia insignia--<?= strtolower($t['estado']) ?>"><?= e($t['estado']) ?></span></td>
                            <td class="derecha numero"><?= (int)$t['resumenes'] ?></td>
                            <td>
                                <div class="acciones">
                                    <a class="accion" href="<?= $aqui ?>&editar=<?= (int)$t['id_tarjeta'] ?>"><?= icono('editar') ?>Editar</a>
                                    <form method="POST">
                                        <?= campo_csrf() ?>
                                        <input type="hidden" name="seccion" value="tarjetas">
                                        <input type="hidden" name="accion" value="bloqueo_tarjeta">
                                        <input type="hidden" name="id_tarjeta" value="<?= (int)$t['id_tarjeta'] ?>">
                                        <button class="accion" type="submit"><?= icono($t['estado'] === 'Bloqueada' ? 'play' : 'pausa') ?><?= $t['estado'] === 'Bloqueada' ? 'Desbloquear' : 'Bloquear' ?></button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Se eliminará la tarjeta terminada en <?= e($t['ultimos4']) ?> y sus resúmenes. ¿Continuar?');">
                                        <?= campo_csrf() ?>
                                        <input type="hidden" name="seccion" value="tarjetas">
                                        <input type="hidden" name="accion" value="eliminar_tarjeta">
                                        <input type="hidden" name="id_tarjeta" value="<?= (int)$t['id_tarjeta'] ?>">
                                        <button class="accion accion--peligro" type="submit" aria-label="Eliminar"><?= icono('borrar') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($mostrarForm): ?>
    <aside class="columna-fija">
        <div class="panel">
            <div class="panel__cabecera">
                <h2>
                    <?php if ($seccion === 'usuarios'): ?><?= $editando ? 'Editar usuario' : 'Nuevo usuario' ?>
                    <?php else: ?><?= $editando ? 'Editar tarjeta' : 'Nueva tarjeta' ?><?php endif; ?>
                </h2>
                <a class="accion" href="<?= $aqui ?>"><?= icono('volver') ?>Cerrar</a>
            </div>

            <form method="POST" class="formulario">
                <?= campo_csrf() ?>
                <input type="hidden" name="seccion" value="<?= $seccion ?>">

            <?php if ($seccion === 'usuarios'): ?>
                <input type="hidden" name="accion" value="guardar_usuario">
                <input type="hidden" name="modo" value="<?= $editando ? 'editar' : 'nuevo' ?>">
                <div class="fila">
                    <div class="campo">
                        <label for="tipo_doc">Tipo</label>
                        <select name="tipo_doc" id="tipo_doc">
                            <?php foreach (TIPOS_DOC as $tipo): ?><option <?= $form['tipo_doc'] === $tipo ? 'selected' : '' ?>><?= $tipo ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="documento">Documento</label>
                        <input type="text" name="documento" id="documento" required maxlength="12" value="<?= e($form['documento']) ?>" <?= $editando ? 'readonly' : '' ?>>
                    </div>
                </div>
                <div class="fila">
                    <div class="campo"><label for="nombre">Nombre</label><input type="text" name="nombre" id="nombre" required maxlength="50" value="<?= e($form['nombre']) ?>"></div>
                    <div class="campo"><label for="apellido">Apellido</label><input type="text" name="apellido" id="apellido" required maxlength="50" value="<?= e($form['apellido']) ?>"></div>
                </div>
                <div class="campo"><label for="email">Correo</label><input type="email" name="email" id="email" required maxlength="100" value="<?= e($form['email']) ?>"></div>
                <div class="fila">
                    <div class="campo"><label for="usuario">Usuario</label><input type="text" name="usuario" id="usuario" required maxlength="30" value="<?= e($form['usuario']) ?>"></div>
                    <div class="campo">
                        <label for="rol">Rol</label>
                        <select name="rol" id="rol">
                            <option <?= $form['rol'] === 'General' ? 'selected' : '' ?>>General</option>
                            <option <?= $form['rol'] === 'Administrador' ? 'selected' : '' ?>>Administrador</option>
                        </select>
                    </div>
                </div>
                <?php if (!$editando): ?>
                    <div class="campo"><label for="fecha_nacimiento">Fecha de nacimiento</label><input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required value="<?= e($form['fecha_nacimiento']) ?>"></div>
                <?php endif; ?>
                <div class="campo">
                    <label for="password"><?= $editando ? 'Nueva contraseña' : 'Contraseña inicial' ?></label>
                    <input type="password" name="password" id="password" <?= $editando ? '' : 'required' ?> minlength="8" autocomplete="new-password">
                    <span class="campo__ayuda"><?= $editando ? 'Dejala vacía para no cambiarla.' : 'El usuario podrá ingresar con ella.' ?></span>
                </div>
                <button class="boton boton--primario boton--bloque" type="submit"><?= $editando ? 'Guardar cambios' : 'Crear usuario' ?></button>

            <?php else: ?>
                <input type="hidden" name="accion" value="guardar_tarjeta">
                <input type="hidden" name="id_tarjeta" value="<?= (int)$form['id_tarjeta'] ?>">
                <div class="campo">
                    <label for="dni_titular">Titular</label>
                    <select name="dni_titular" id="dni_titular" required>
                        <option value="" disabled <?= $form['dni_titular'] === '' ? 'selected' : '' ?>>Elegí un cliente</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= e($c['documento']) ?>" <?= $form['dni_titular'] === $c['documento'] ? 'selected' : '' ?>>
                                <?= e($c['apellido'] . ', ' . $c['nombre'] . ' (' . $c['documento'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fila">
                    <div class="campo">
                        <label for="banco_emisor">Banco</label>
                        <select name="banco_emisor" id="banco_emisor">
                            <?php foreach (BANCOS as $b): ?><option <?= $form['banco_emisor'] === $b ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="red">Red</label>
                        <select name="red" id="red">
                            <?php foreach (REDES as $r): ?><option <?= $form['red'] === $r ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="fila">
                    <div class="campo campo--digitos">
                        <label for="ultimos4">Últimos 4</label>
                        <input type="text" name="ultimos4" id="ultimos4" required maxlength="4" pattern="\d{4}" inputmode="numeric" value="<?= e($form['ultimos4']) ?>">
                    </div>
                    <div class="campo">
                        <label for="estado">Estado</label>
                        <select name="estado" id="estado">
                            <?php foreach (['Activa', 'Inactiva', 'Bloqueada'] as $est): ?><option <?= $form['estado'] === $est ? 'selected' : '' ?>><?= $est ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button class="boton boton--primario boton--bloque" type="submit"><?= $editando ? 'Guardar cambios' : 'Cargar tarjeta' ?></button>
            <?php endif; ?>
            </form>
        </div>
    </aside>
    <?php endif; ?>
</div>

<?php pie(); ?>
