<?php
// tarjetas.php — ABM de tarjetas del usuario General (alta, baja, modificación, activar/desactivar)
require 'includes/config.php';
require 'includes/datos.php';
require 'includes/layout.php';

$yo = requerir_rol('General');
$doc = $yo['documento'];

/** Trae una tarjeta SOLO si pertenece al usuario logueado. */
function tarjeta_propia(int $id, string $doc): ?array
{
    $st = db()->prepare('SELECT * FROM tarjetas WHERE id_tarjeta = :id AND dni_titular = :doc');
    $st->execute([':id' => $id, ':doc' => $doc]);
    return $st->fetch() ?: null;
}

/** Valida los datos del formulario. Devuelve [datos limpios, errores]. */
function validar_tarjeta(array $post): array
{
    $d = [
        'banco_emisor' => $post['banco_emisor'] ?? '',
        'red'          => $post['red'] ?? '',
        'ultimos4'     => trim($post['ultimos4'] ?? ''),
        'alias'        => trim($post['alias'] ?? ''),
    ];
    $errores = [];
    if (!in_array($d['banco_emisor'], BANCOS, true)) $errores[] = 'Elegí un banco de la lista.';
    if (!in_array($d['red'], REDES, true))           $errores[] = 'Elegí una red de la lista.';
    if (!preg_match('/^\d{4}$/', $d['ultimos4']))    $errores[] = 'Ingresá exactamente los últimos 4 dígitos.';
    if (mb_strlen($d['alias']) > 40)                 $errores[] = 'El nombre puede tener hasta 40 caracteres.';
    $d['alias'] = $d['alias'] === '' ? null : $d['alias'];
    return [$d, $errores];
}

// ---------------------------------------------------------------- Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf('tarjetas.php');
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    try {
        if ($accion === 'crear' || $accion === 'actualizar') {
            [$d, $errores] = validar_tarjeta($_POST);
            if ($accion === 'actualizar' && !tarjeta_propia($id, $doc)) {
                $errores[] = 'La tarjeta no existe.';
            }
            if ($errores) {
                foreach ($errores as $err) flash('error', $err);
                recordar_datos($_POST);
                redirigir('tarjetas.php' . ($accion === 'actualizar' ? "?editar=$id" : ''));
            }

            if ($accion === 'crear') {
                db()->prepare('INSERT INTO tarjetas (dni_titular, banco_emisor, red, ultimos4, alias)
                               VALUES (:doc, :banco, :red, :u4, :alias)')
                    ->execute([':doc' => $doc, ':banco' => $d['banco_emisor'], ':red' => $d['red'],
                               ':u4' => $d['ultimos4'], ':alias' => $d['alias']]);
                flash('exito', 'Agregaste la tarjeta terminada en ' . $d['ultimos4'] . '.');
            } else {
                db()->prepare('UPDATE tarjetas SET banco_emisor = :banco, red = :red, ultimos4 = :u4, alias = :alias
                               WHERE id_tarjeta = :id AND dni_titular = :doc')
                    ->execute([':banco' => $d['banco_emisor'], ':red' => $d['red'], ':u4' => $d['ultimos4'],
                               ':alias' => $d['alias'], ':id' => $id, ':doc' => $doc]);
                flash('exito', 'Guardaste los cambios de la tarjeta.');
            }
        }

        if ($accion === 'cambiar_estado') {
            $t = tarjeta_propia($id, $doc);
            if (!$t) {
                flash('error', 'La tarjeta no existe.');
            } elseif ($t['estado'] === 'Bloqueada') {
                // Solo un administrador puede desbloquear
                flash('error', 'Esta tarjeta fue bloqueada por un administrador. Escribinos desde Contacto para revisarla.');
            } else {
                $nuevo = $t['estado'] === 'Activa' ? 'Inactiva' : 'Activa';
                db()->prepare('UPDATE tarjetas SET estado = :e WHERE id_tarjeta = :id AND dni_titular = :doc')
                    ->execute([':e' => $nuevo, ':id' => $id, ':doc' => $doc]);
                flash('exito', $nuevo === 'Activa' ? 'Activaste la tarjeta.' : 'Desactivaste la tarjeta. Sus resúmenes se conservan.');
            }
        }

        if ($accion === 'eliminar') {
            $st = db()->prepare('DELETE FROM tarjetas WHERE id_tarjeta = :id AND dni_titular = :doc');
            $st->execute([':id' => $id, ':doc' => $doc]);
            flash($st->rowCount() ? 'exito' : 'error',
                  $st->rowCount() ? 'Eliminaste la tarjeta y sus resúmenes.' : 'La tarjeta no existe.');
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {   // violación de UNIQUE
            flash('error', 'Ya tenés cargada una tarjeta de ese banco y red con esos 4 dígitos.');
            recordar_datos($_POST);
        } else {
            error_log($e->getMessage());
            flash('error', 'No pudimos guardar los cambios. Intentá de nuevo.');
        }
    }
    redirigir('tarjetas.php');
}

// ---------------------------------------------------------------- Datos de la vista
$st = db()->prepare("SELECT t.*, COUNT(l.id_liquidacion) AS resumenes
                     FROM tarjetas t
                     LEFT JOIN liquidaciones l ON l.id_tarjeta = t.id_tarjeta
                     WHERE t.dni_titular = :doc
                     GROUP BY t.id_tarjeta
                     ORDER BY FIELD(t.estado, 'Activa', 'Inactiva', 'Bloqueada'), t.creado_el");
$st->execute([':doc' => $doc]);
$tarjetas = $st->fetchAll();

$editando = isset($_GET['editar']) ? tarjeta_propia((int)$_GET['editar'], $doc) : null;
$previos = datos_previos();
$form = array_merge(
    ['banco_emisor' => 'Banco Galicia', 'red' => 'Visa', 'ultimos4' => '', 'alias' => '', 'estado' => 'Activa'],
    $editando ?? [],
    array_intersect_key($previos, array_flip(['banco_emisor', 'red', 'ultimos4', 'alias']))
);

encabezado('Mis tarjetas', 'tarjetas');
?>

<div class="cabecera-pagina">
    <div>
        <h1>Mis tarjetas</h1>
        <p>Agregá, editá, desactivá o eliminá tus tarjetas. Solo guardamos los últimos cuatro dígitos.</p>
    </div>
</div>

<div class="dos-columnas">
    <!-- LISTADO -->
    <section>
        <?php if ($tarjetas): ?>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead>
                        <tr><th>Tarjeta</th><th>Banco</th><th>Estado</th><th class="derecha">Resúmenes</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tarjetas as $t): ?>
                        <tr class="<?= $editando && $editando['id_tarjeta'] == $t['id_tarjeta'] ? 'es-seleccionada' : '' ?>">
                            <td><?= mini_tarjeta($t) ?></td>
                            <td><?= e($t['banco_emisor']) ?></td>
                            <td><span class="insignia insignia--<?= strtolower($t['estado']) ?>"><?= e($t['estado']) ?></span></td>
                            <td class="derecha numero"><a href="liquidaciones.php?tarjeta=<?= (int)$t['id_tarjeta'] ?>"><?= (int)$t['resumenes'] ?></a></td>
                            <td>
                                <div class="acciones">
                                    <a class="accion" href="tarjetas.php?editar=<?= (int)$t['id_tarjeta'] ?>"><?= icono('editar') ?>Editar</a>
                                    <?php if ($t['estado'] !== 'Bloqueada'): ?>
                                        <form method="POST">
                                            <?= campo_csrf() ?>
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="id" value="<?= (int)$t['id_tarjeta'] ?>">
                                            <button class="accion" type="submit">
                                                <?= $t['estado'] === 'Activa' ? icono('pausa') . 'Desactivar' : icono('play') . 'Activar' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" onsubmit="return confirm('Se eliminará la tarjeta y sus <?= (int)$t['resumenes'] ?> resúmenes. Esta acción no se puede deshacer.');">
                                        <?= campo_csrf() ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?= (int)$t['id_tarjeta'] ?>">
                                        <button class="accion accion--peligro" type="submit"><?= icono('borrar') ?>Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="privacidad-nota"><?= icono('pausa') ?>Desactivar oculta la tarjeta del uso diario pero conserva su historial. Eliminar borra también sus resúmenes.</p>
        <?php else: ?>
            <div class="vacio">
                <?= icono('tarjeta') ?>
                <h3>Todavía no cargaste tarjetas</h3>
                <p>Completá el formulario con el banco, la red y los últimos cuatro dígitos.</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- FORMULARIO ALTA / EDICIÓN -->
    <aside class="columna-fija">
        <div class="panel">
            <div class="panel__cabecera">
                <h2><?= $editando ? 'Editar tarjeta' : 'Agregar tarjeta' ?></h2>
                <?php if ($editando): ?><a class="accion" href="tarjetas.php"><?= icono('volver') ?>Cancelar</a><?php endif; ?>
            </div>

            <!-- Vista previa en vivo -->
            <div id="vista-previa" style="margin-bottom:1.25rem">
                <?= tarjeta_visual(['banco_emisor' => $form['banco_emisor'], 'red' => $form['red'],
                                    'ultimos4' => $form['ultimos4'] ?: '0000', 'alias' => $form['alias'],
                                    'estado' => $form['estado']]) ?>
            </div>

            <form method="POST" action="tarjetas.php" class="formulario">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="<?= $editando ? 'actualizar' : 'crear' ?>">
                <?php if ($editando): ?><input type="hidden" name="id" value="<?= (int)$editando['id_tarjeta'] ?>"><?php endif; ?>

                <div class="fila">
                    <div class="campo">
                        <label for="banco_emisor">Banco emisor</label>
                        <select name="banco_emisor" id="banco_emisor">
                            <?php foreach (BANCOS as $b): ?>
                                <option <?= $b === $form['banco_emisor'] ? 'selected' : '' ?>><?= e($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="red">Red</label>
                        <select name="red" id="red">
                            <?php foreach (REDES as $r): ?>
                                <option value="<?= e($r) ?>" data-clase="<?= clase_red($r) ?>" <?= $r === $form['red'] ? 'selected' : '' ?>><?= e($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="campo campo--digitos">
                    <label for="ultimos4">Últimos 4 dígitos</label>
                    <input type="text" name="ultimos4" id="ultimos4" required maxlength="4" minlength="4"
                           pattern="\d{4}" inputmode="numeric" placeholder="0000" value="<?= e($form['ultimos4']) ?>">
                </div>
                <div class="campo">
                    <label for="alias">Nombre de la tarjeta <span class="texto-suave">(opcional)</span></label>
                    <input type="text" name="alias" id="alias" maxlength="40" placeholder="Ej.: Viajes, Gastos del súper" value="<?= e($form['alias']) ?>">
                </div>
                <button class="boton boton--primario boton--bloque" type="submit">
                    <?= $editando ? 'Guardar cambios' : 'Agregar tarjeta' ?>
                </button>
            </form>
        </div>
    </aside>
</div>

<script>
// Actualiza la vista previa de la tarjeta mientras se completa el formulario
(function () {
    const tarjeta = document.querySelector('#vista-previa .tarjeta-visual');
    const banco = document.getElementById('banco_emisor');
    const red = document.getElementById('red');
    const digitos = document.getElementById('ultimos4');
    const alias = document.getElementById('alias');

    function actualizar() {
        tarjeta.querySelector('.tv__banco').textContent = banco.value;
        tarjeta.querySelector('.tv__red').textContent = red.value;
        const clase = red.selectedOptions[0].dataset.clase;
        tarjeta.className = tarjeta.className.replace(/tarjeta-visual--(visa|mastercard|amex|cabal|naranja)/, 'tarjeta-visual--' + clase);
        const num = tarjeta.querySelector('.tv__numero');
        num.lastChild.textContent = (digitos.value.replace(/\D/g, '') + '0000').slice(0, 4);
        tarjeta.querySelector('.tv__alias').textContent = alias.value.trim() || 'Sin nombre';
    }
    digitos.addEventListener('input', () => { digitos.value = digitos.value.replace(/\D/g, ''); });
    [banco, red, digitos, alias].forEach(el => el.addEventListener('input', actualizar));
})();
</script>

<?php pie(); ?>
