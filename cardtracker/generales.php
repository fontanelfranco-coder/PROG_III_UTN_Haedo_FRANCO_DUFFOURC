<?php
// generales.php — Inicio del usuario General: avisos, resumen de deuda, tarjetas y perfil
require 'includes/config.php';
require 'includes/datos.php';
require 'includes/layout.php';

$yo = requerir_rol('General');
$doc = $yo['documento'];

// Datos personales
$st = db()->prepare('SELECT tipo_doc, documento, nombre, apellido, email, fecha_nacimiento, creado_el FROM usuarios WHERE documento = :doc');
$st->execute([':doc' => $doc]);
$cliente = $st->fetch();

// Tarjetas (activas primero)
$st = db()->prepare("SELECT * FROM tarjetas WHERE dni_titular = :doc
                     ORDER BY FIELD(estado, 'Activa', 'Inactiva', 'Bloqueada'), creado_el");
$st->execute([':doc' => $doc]);
$tarjetas = $st->fetchAll();

// Avisos de vencimiento
$avisos = obtener_avisos($doc);

// Cifras: deuda pendiente total y próximo vencimiento
$st = db()->prepare("SELECT COALESCE(SUM(l.total_a_pagar), 0) AS deuda,
                            COALESCE(SUM(l.pago_minimo), 0) AS minimo,
                            MIN(CASE WHEN l.fecha_vencimiento >= CURDATE() THEN l.fecha_vencimiento END) AS proximo
                     FROM liquidaciones l
                     INNER JOIN tarjetas t ON t.id_tarjeta = l.id_tarjeta
                     WHERE t.dni_titular = :doc AND l.estado_pago = 'Pendiente'");
$st->execute([':doc' => $doc]);
$cifras = $st->fetch();
$activas = count(array_filter($tarjetas, fn($t) => $t['estado'] === 'Activa'));

$hora = (int)date('G');
$saludo = $hora < 13 ? 'Buen día' : ($hora < 20 ? 'Buenas tardes' : 'Buenas noches');

encabezado('Inicio', 'inicio');
?>

<div class="cabecera-pagina">
    <div>
        <h1><?= $saludo ?>, <?= e($cliente['nombre']) ?></h1>
        <p>
            <?php if (!$avisos): ?>Estás al día con tus pagos.
            <?php else: ?>Tenés <?= count($avisos) ?> <?= count($avisos) === 1 ? 'pago' : 'pagos' ?> para revisar.<?php endif; ?>
        </p>
    </div>
    <a href="resumen.php" class="boton boton--primario"><?= icono('mas') ?>Registrar resumen</a>
</div>

<!-- CIFRAS -->
<div class="cifras">
    <div class="cifra cifra--destacada">
        <span class="cifra__valor numero"><?= pesos($cifras['deuda']) ?></span>
        <span class="cifra__texto">Total pendiente de pago</span>
    </div>
    <div class="cifra">
        <span class="cifra__valor numero"><?= pesos($cifras['minimo']) ?></span>
        <span class="cifra__texto">Suma de pagos mínimos</span>
    </div>
    <div class="cifra">
        <span class="cifra__valor numero"><?= $cifras['proximo'] ? fecha_corta($cifras['proximo']) : 'Sin fecha' ?></span>
        <span class="cifra__texto">Próximo vencimiento</span>
    </div>
    <div class="cifra">
        <span class="cifra__valor numero"><?= $activas ?></span>
        <span class="cifra__texto"><?= $activas === 1 ? 'Tarjeta activa' : 'Tarjetas activas' ?></span>
    </div>
</div>

<!-- AVISOS (recordatorios y notificaciones) -->
<section class="seccion" id="avisos">
    <h2>Avisos de pago</h2>
    <?php if ($avisos): ?>
        <div class="avisos">
            <?php foreach ($avisos as $a): $estado = estado_resumen($a); ?>
                <div class="aviso<?= $estado === 'vencido' ? ' aviso--vencido' : '' ?>">
                    <span class="aviso__icono"><?= icono($estado === 'vencido' ? 'alerta' : 'campana') ?></span>
                    <div class="aviso__texto">
                        <strong><?= e(nombre_tarjeta($a)) ?>: <?= texto_vencimiento($a['fecha_vencimiento']) ?></strong>
                        <span>Resumen de <?= periodo_legible($a['periodo']) ?>, vencimiento <?= fecha_corta($a['fecha_vencimiento']) ?>. Pago mínimo <?= pesos($a['pago_minimo']) ?>.</span>
                    </div>
                    <div class="aviso__monto">
                        <strong class="numero"><?= pesos($a['total_a_pagar']) ?></strong>
                        <form method="POST" action="liquidaciones.php">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="pagar">
                            <input type="hidden" name="id" value="<?= (int)$a['id_liquidacion'] ?>">
                            <input type="hidden" name="volver" value="generales.php#avisos">
                            <button class="accion accion--exito" type="submit"><?= icono('check') ?>Marcar como pagado</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="todo-en-orden"><?= icono('check') ?>No tenés pagos vencidos ni vencimientos en los próximos <?= DIAS_AVISO ?> días.</div>
    <?php endif; ?>
</section>

<!-- TARJETAS -->
<section class="seccion">
    <div class="panel__cabecera">
        <h2 style="margin:0">Mis tarjetas</h2>
        <a href="tarjetas.php" class="boton boton--secundario boton--chico"><?= icono('editar') ?>Administrar tarjetas</a>
    </div>

    <div class="grilla-tarjetas">
        <?php foreach ($tarjetas as $t): ?>
            <div class="grilla-tarjetas__item">
                <?= tarjeta_visual($t) ?>
                <div class="grilla-tarjetas__pie">
                    <a class="accion" href="liquidaciones.php?tarjeta=<?= (int)$t['id_tarjeta'] ?>"><?= icono('resumen') ?>Ver resúmenes</a>
                    <a class="accion" href="tarjetas.php?editar=<?= (int)$t['id_tarjeta'] ?>"><?= icono('editar') ?>Editar</a>
                </div>
            </div>
        <?php endforeach; ?>
        <a class="tarjeta-nueva" href="tarjetas.php">
            <?= icono('mas') ?>
            Agregar tarjeta
        </a>
    </div>
</section>

<!-- PERFIL -->
<section class="seccion">
    <h2>Mis datos</h2>
    <div class="panel">
        <dl class="datos-perfil">
            <dt>Nombre</dt><dd><?= e($cliente['nombre'] . ' ' . $cliente['apellido']) ?></dd>
            <dt>Documento</dt><dd><?= e($cliente['tipo_doc'] . ' ' . $cliente['documento']) ?></dd>
            <dt>Correo</dt><dd><?= e($cliente['email']) ?></dd>
            <dt>Nacimiento</dt><dd><?= fecha_corta($cliente['fecha_nacimiento']) ?></dd>
            <dt>Cliente desde</dt><dd><?= fecha_corta($cliente['creado_el']) ?></dd>
        </dl>
        <p class="chico texto-suave" style="margin:1rem 0 0">¿Necesitás cambiar algún dato? <a href="contacto.php">Escribinos</a>.</p>
    </div>
</section>

<?php pie(); ?>
