<?php
// admin.php — Panel del Administrador: cifras agregadas y accesos rápidos
require 'includes/config.php';
require 'includes/datos.php';
require 'includes/layout.php';

$yo = requerir_rol('Administrador');

// Solo cifras agregadas: el panel no expone importes ni datos de un cliente en particular
$c = db()->query("SELECT
        (SELECT COUNT(*) FROM usuarios WHERE rol = 'General') AS clientes,
        (SELECT COUNT(*) FROM usuarios WHERE rol = 'General' AND creado_el >= CURDATE() - INTERVAL 30 DAY) AS clientes_nuevos,
        (SELECT COUNT(*) FROM tarjetas WHERE estado = 'Activa') AS activas,
        (SELECT COUNT(*) FROM tarjetas WHERE estado = 'Bloqueada') AS bloqueadas,
        (SELECT COUNT(*) FROM consultas WHERE resuelto = 'No') AS pendientes,
        (SELECT COUNT(*) FROM liquidaciones WHERE estado_pago = 'Pendiente' AND fecha_vencimiento < CURDATE()) AS vencidos
    ")->fetch();

// Tarjetas por red (para ver la distribución)
$porRed = db()->query("SELECT red, COUNT(*) AS cantidad FROM tarjetas GROUP BY red ORDER BY cantidad DESC")->fetchAll();
$maxRed = $porRed ? max(array_column($porRed, 'cantidad')) : 0;

// Últimas consultas sin resolver
$ultimas = db()->query("SELECT id_consulta, nombre, categoria, fecha_envio FROM consultas
                        WHERE resuelto = 'No' ORDER BY fecha_envio ASC LIMIT 4")->fetchAll();

encabezado('Panel de administración', 'panel');
?>

<div class="cabecera-pagina">
    <div>
        <h1>Hola, <?= e($yo['nombre']) ?></h1>
        <p>Estado general de CardTracker al <?= date('d/m/Y') ?>.</p>
    </div>
</div>

<div class="cifras">
    <div class="cifra cifra--destacada">
        <span class="cifra__valor numero"><?= (int)$c['pendientes'] ?></span>
        <span class="cifra__texto"><a href="admin_consultas.php">Consultas sin responder</a></span>
    </div>
    <div class="cifra">
        <span class="cifra__valor numero"><?= (int)$c['clientes'] ?></span>
        <span class="cifra__texto">Clientes, <?= (int)$c['clientes_nuevos'] ?> en los últimos 30 días</span>
    </div>
    <div class="cifra">
        <span class="cifra__valor numero"><?= (int)$c['activas'] ?></span>
        <span class="cifra__texto">Tarjetas activas, <?= (int)$c['bloqueadas'] ?> bloqueadas</span>
    </div>
    <div class="cifra">
        <span class="cifra__valor numero"><?= (int)$c['vencidos'] ?></span>
        <span class="cifra__texto">Resúmenes vencidos sin pagar</span>
    </div>
</div>

<div class="dos-columnas seccion">
    <section class="panel">
        <div class="panel__cabecera">
            <h2>Consultas más antiguas</h2>
            <a class="accion" href="admin_consultas.php">Ver todas</a>
        </div>
        <?php if ($ultimas): ?>
            <div class="tabla-scroll" style="border:0">
                <table class="tabla">
                    <tbody>
                    <?php foreach ($ultimas as $q): ?>
                        <tr>
                            <td><strong><?= e($q['nombre']) ?></strong></td>
                            <td><span class="categoria categoria--<?= strtolower($q['categoria']) ?>"><?= e($q['categoria']) ?></span></td>
                            <td class="texto-suave chico numero"><?= date('d/m/Y H:i', strtotime($q['fecha_envio'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="todo-en-orden"><?= icono('check') ?>No hay consultas pendientes.</div>
        <?php endif; ?>

        <h3 style="margin-top:1.75rem">Tarjetas por red</h3>
        <div style="display:grid; gap:.6rem">
            <?php foreach ($porRed as $r): ?>
                <div style="display:grid; grid-template-columns: 9rem 1fr 2rem; gap:.75rem; align-items:center">
                    <span class="chico"><?= e($r['red']) ?></span>
                    <span class="mini-tarjeta__muestra mini-tarjeta__muestra--<?= clase_red($r['red']) ?>"
                          style="width: <?= round($r['cantidad'] / $maxRed * 100) ?>%; height: 14px; border-radius: 4px"></span>
                    <span class="numero chico"><?= (int)$r['cantidad'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <aside>
        <div class="accesos">
            <a class="acceso" href="admin_abm.php?seccion=usuarios">
                <?= icono('usuarios') ?>
                <div><strong>Usuarios</strong><span>Dar de alta, editar o eliminar cuentas</span></div>
            </a>
            <a class="acceso" href="admin_abm.php?seccion=tarjetas">
                <?= icono('tarjeta') ?>
                <div><strong>Tarjetas</strong><span>Cargar, bloquear o eliminar tarjetas</span></div>
            </a>
            <a class="acceso" href="admin_busqueda.php">
                <?= icono('buscar') ?>
                <div><strong>Búsqueda</strong><span>Consultar cualquier tabla del sistema</span></div>
            </a>
        </div>
        <p class="privacidad-nota">
            <?= icono('escudo') ?>
            Por privacidad, la administración no ve contraseñas, fechas de nacimiento, nombres personalizados de tarjetas ni importes de los resúmenes. Esos datos solo los ve su titular.
        </p>
    </aside>
</div>

<?php pie(); ?>
