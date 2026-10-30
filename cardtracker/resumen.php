<?php
// resumen.php — Alta y edición de un resumen de cuenta (liquidación)
require 'includes/config.php';
require 'includes/datos.php';
require 'includes/layout.php';

$yo = requerir_rol('General');
$doc = $yo['documento'];

/** Trae un resumen SOLO si es de una tarjeta del usuario. */
function resumen_propio(int $id, string $doc): ?array
{
    $st = db()->prepare('SELECT l.* FROM liquidaciones l
                         INNER JOIN tarjetas t ON t.id_tarjeta = l.id_tarjeta
                         WHERE l.id_liquidacion = :id AND t.dni_titular = :doc');
    $st->execute([':id' => $id, ':doc' => $doc]);
    return $st->fetch() ?: null;
}

$idEditar = (int)($_GET['editar'] ?? $_POST['id'] ?? 0);
$existente = $idEditar ? resumen_propio($idEditar, $doc) : null;
if ($idEditar && !$existente) {
    flash('error', 'No encontramos ese resumen.');
    redirigir('liquidaciones.php');
}

// Tarjetas disponibles: las activas, más la del resumen que se edita (aunque esté inactiva)
$st = db()->prepare("SELECT * FROM tarjetas WHERE dni_titular = :doc AND (estado = 'Activa' OR id_tarjeta = :actual)
                     ORDER BY banco_emisor");
$st->execute([':doc' => $doc, ':actual' => $existente['id_tarjeta'] ?? 0]);
$tarjetas = $st->fetchAll();
$idsPropios = array_map('intval', array_column($tarjetas, 'id_tarjeta'));

$d = $existente ?? [
    'id_tarjeta' => (int)($_GET['tarjeta'] ?? ($idsPropios[0] ?? 0)),
    'periodo' => date('Y-m'), 'fecha_cierre' => '', 'fecha_vencimiento' => '',
    'total_a_pagar' => '', 'pago_minimo' => '', 'estado_pago' => 'Pendiente', 'fecha_pago' => null,
];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf('resumen.php' . ($existente ? '?editar=' . $idEditar : ''));

    $d = [
        'id_tarjeta'        => (int)($_POST['id_tarjeta'] ?? 0),
        'periodo'           => $_POST['periodo'] ?? '',
        'fecha_cierre'      => $_POST['fecha_cierre'] ?? '',
        'fecha_vencimiento' => $_POST['fecha_vencimiento'] ?? '',
        'total_a_pagar'     => str_replace(',', '.', trim($_POST['total_a_pagar'] ?? '')),
        'pago_minimo'       => str_replace(',', '.', trim($_POST['pago_minimo'] ?? '')),
        'estado_pago'       => ($_POST['estado_pago'] ?? '') === 'Pagado' ? 'Pagado' : 'Pendiente',
        'fecha_pago'        => $_POST['fecha_pago'] ?? '',
    ];

    $fechaValida = fn($f) => ($x = DateTime::createFromFormat('Y-m-d', $f)) && $x->format('Y-m-d') === $f;

    if (!in_array($d['id_tarjeta'], $idsPropios, true)) $errores[] = 'Elegí una de tus tarjetas activas.';
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $d['periodo'])) $errores[] = 'Elegí el período de facturación.';
    if (!$fechaValida($d['fecha_cierre']))      $errores[] = 'Ingresá la fecha de cierre.';
    if (!$fechaValida($d['fecha_vencimiento'])) $errores[] = 'Ingresá la fecha de vencimiento.';
    if (!$errores && $d['fecha_vencimiento'] <= $d['fecha_cierre']) {
        $errores[] = 'El vencimiento tiene que ser posterior a la fecha de cierre.';
    }
    if (!is_numeric($d['total_a_pagar']) || $d['total_a_pagar'] < 0 || $d['total_a_pagar'] > 9999999999) {
        $errores[] = 'Ingresá el monto total (un número positivo).';
    }
    if (!is_numeric($d['pago_minimo']) || $d['pago_minimo'] < 0) {
        $errores[] = 'Ingresá el pago mínimo (un número positivo).';
    } elseif (is_numeric($d['total_a_pagar']) && (float)$d['pago_minimo'] > (float)$d['total_a_pagar']) {
        $errores[] = 'El pago mínimo no puede superar al total.';
    }
    if ($d['estado_pago'] === 'Pagado') {
        $d['fecha_pago'] = $fechaValida($d['fecha_pago']) ? $d['fecha_pago'] : date('Y-m-d');
    } else {
        $d['fecha_pago'] = null;
    }

    if (!$errores) {
        $valores = [
            ':tar' => $d['id_tarjeta'], ':per' => $d['periodo'], ':cie' => $d['fecha_cierre'],
            ':ven' => $d['fecha_vencimiento'], ':tot' => $d['total_a_pagar'], ':min' => $d['pago_minimo'],
            ':est' => $d['estado_pago'], ':fpa' => $d['fecha_pago'],
        ];
        try {
            if ($existente) {
                db()->prepare('UPDATE liquidaciones SET id_tarjeta = :tar, periodo = :per, fecha_cierre = :cie,
                               fecha_vencimiento = :ven, total_a_pagar = :tot, pago_minimo = :min,
                               estado_pago = :est, fecha_pago = :fpa WHERE id_liquidacion = :id')
                    ->execute($valores + [':id' => $idEditar]);
                flash('exito', 'Guardaste los cambios del resumen de ' . periodo_legible($d['periodo']) . '.');
            } else {
                db()->prepare('INSERT INTO liquidaciones (id_tarjeta, periodo, fecha_cierre, fecha_vencimiento,
                               total_a_pagar, pago_minimo, estado_pago, fecha_pago)
                               VALUES (:tar, :per, :cie, :ven, :tot, :min, :est, :fpa)')
                    ->execute($valores);
                flash('exito', 'Registraste el resumen de ' . periodo_legible($d['periodo']) . '.');
            }
            redirigir('liquidaciones.php');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errores[] = 'Esa tarjeta ya tiene un resumen para ' . periodo_legible($d['periodo']) . '. Editalo desde el historial.';
            } else {
                error_log($e->getMessage());
                $errores[] = 'No pudimos guardar el resumen. Intentá de nuevo.';
            }
        }
    }
}

encabezado($existente ? 'Editar resumen' : 'Registrar resumen', 'resumenes');
?>

<div class="angosto">
    <div class="cabecera-pagina">
        <div>
            <h1><?= $existente ? 'Editar resumen' : 'Registrar resumen' ?></h1>
            <p>Copiá los datos tal como figuran en el resumen que te envió el banco.</p>
        </div>
        <a class="boton boton--fantasma" href="liquidaciones.php"><?= icono('volver') ?>Volver al historial</a>
    </div>

    <?php if ($errores): ?>
        <div class="mensajes">
            <?php foreach ($errores as $err): ?>
                <div class="mensaje mensaje--error" role="alert"><?= icono('alerta') ?><p><?= e($err) ?></p></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$tarjetas): ?>
        <div class="vacio">
            <?= icono('tarjeta') ?>
            <h3>Primero necesitás una tarjeta activa</h3>
            <p>Cada resumen pertenece a una tarjeta. Cargá una y volvé.</p>
            <a class="boton boton--primario" href="tarjetas.php"><?= icono('mas') ?>Agregar tarjeta</a>
        </div>
    <?php else: ?>
        <form method="POST" class="panel panel--formulario formulario">
            <?= campo_csrf() ?>
            <?php if ($existente): ?><input type="hidden" name="id" value="<?= $idEditar ?>"><?php endif; ?>

            <div class="fila">
                <div class="campo">
                    <label for="id_tarjeta">Tarjeta</label>
                    <select name="id_tarjeta" id="id_tarjeta" required>
                        <?php foreach ($tarjetas as $t): ?>
                            <option value="<?= (int)$t['id_tarjeta'] ?>" <?= (int)$d['id_tarjeta'] === (int)$t['id_tarjeta'] ? 'selected' : '' ?>>
                                <?= e(nombre_tarjeta($t)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo">
                    <label for="periodo">Período de facturación</label>
                    <input type="month" name="periodo" id="periodo" required value="<?= e($d['periodo']) ?>">
                </div>
            </div>

            <div class="fila">
                <div class="campo">
                    <label for="fecha_cierre">Fecha de cierre</label>
                    <input type="date" name="fecha_cierre" id="fecha_cierre" required value="<?= e($d['fecha_cierre']) ?>">
                </div>
                <div class="campo">
                    <label for="fecha_vencimiento">Vencimiento del pago</label>
                    <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" required value="<?= e($d['fecha_vencimiento']) ?>">
                </div>
            </div>

            <div class="fila">
                <div class="campo">
                    <label for="total_a_pagar">Monto total a pagar ($)</label>
                    <input type="number" step="0.01" min="0" name="total_a_pagar" id="total_a_pagar" required inputmode="decimal" value="<?= e($d['total_a_pagar']) ?>">
                </div>
                <div class="campo">
                    <label for="pago_minimo">Pago mínimo ($)</label>
                    <input type="number" step="0.01" min="0" name="pago_minimo" id="pago_minimo" required inputmode="decimal" value="<?= e($d['pago_minimo']) ?>">
                </div>
            </div>

            <hr class="separador">

            <div class="fila">
                <div class="campo">
                    <label for="estado_pago">Estado del pago</label>
                    <select name="estado_pago" id="estado_pago">
                        <option value="Pendiente" <?= $d['estado_pago'] === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="Pagado" <?= $d['estado_pago'] === 'Pagado' ? 'selected' : '' ?>>Pagado</option>
                    </select>
                </div>
                <div class="campo" id="campo-fecha-pago">
                    <label for="fecha_pago">Fecha en que pagaste</label>
                    <input type="date" name="fecha_pago" id="fecha_pago" value="<?= e($d['fecha_pago'] ?? '') ?>">
                    <span class="campo__ayuda">Si la dejás vacía usamos la fecha de hoy.</span>
                </div>
            </div>

            <button type="submit" class="boton boton--primario boton--bloque">
                <?= $existente ? 'Guardar cambios' : 'Registrar resumen' ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<script>
// La fecha de pago solo tiene sentido si el resumen está pagado
(function () {
    const estado = document.getElementById('estado_pago');
    const campo = document.getElementById('campo-fecha-pago');
    if (!estado) return;
    const sync = () => { campo.style.display = estado.value === 'Pagado' ? '' : 'none'; };
    estado.addEventListener('change', sync);
    sync();
})();
</script>

<?php pie(); ?>
