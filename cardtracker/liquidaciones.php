<?php
// liquidaciones.php — Historial de resúmenes: filtros, buscador, comparativa mensual y acciones
require 'includes/config.php';
require 'includes/datos.php';
require 'includes/layout.php';

$yo = requerir_rol('General');
$doc = $yo['documento'];

// ---------------------------------------------------------------- Acciones (pagar / deshacer / eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Solo se permite volver a páginas internas conocidas (evita redirecciones abiertas)
    $volver = $_POST['volver'] ?? '';
    if (!preg_match('/^(liquidaciones|generales)\.php([?#][\w=&%.#+-]*)?$/', $volver)) {
        $volver = 'liquidaciones.php';
    }
    verificar_csrf($volver);

    $id = (int)($_POST['id'] ?? 0);
    $accion = $_POST['accion'] ?? '';

    // El JOIN con tarjetas garantiza que el resumen sea del usuario logueado
    $condicion = 'id_liquidacion = :id AND id_tarjeta IN (SELECT id_tarjeta FROM tarjetas WHERE dni_titular = :doc)';
    $params = [':id' => $id, ':doc' => $doc];

    if ($accion === 'pagar') {
        $st = db()->prepare("UPDATE liquidaciones SET estado_pago = 'Pagado', fecha_pago = CURDATE() WHERE $condicion");
        $st->execute($params);
        flash($st->rowCount() ? 'exito' : 'error', $st->rowCount() ? 'Registraste el pago del resumen.' : 'No encontramos ese resumen.');
    } elseif ($accion === 'despagar') {
        $st = db()->prepare("UPDATE liquidaciones SET estado_pago = 'Pendiente', fecha_pago = NULL WHERE $condicion");
        $st->execute($params);
        flash($st->rowCount() ? 'exito' : 'error', $st->rowCount() ? 'El resumen volvió a figurar como pendiente.' : 'No encontramos ese resumen.');
    } elseif ($accion === 'eliminar') {
        $st = db()->prepare("DELETE FROM liquidaciones WHERE $condicion");
        $st->execute($params);
        flash($st->rowCount() ? 'exito' : 'error', $st->rowCount() ? 'Eliminaste el resumen.' : 'No encontramos ese resumen.');
    }
    redirigir($volver);
}

// ---------------------------------------------------------------- Filtros (por GET, así se pueden compartir/guardar)
$f = [
    'tarjeta' => (int)($_GET['tarjeta'] ?? 0),
    'desde'   => preg_match('/^\d{4}-\d{2}$/', $_GET['desde'] ?? '') ? $_GET['desde'] : '',
    'hasta'   => preg_match('/^\d{4}-\d{2}$/', $_GET['hasta'] ?? '') ? $_GET['hasta'] : '',
    'estado'  => in_array($_GET['estado'] ?? '', ['pendiente', 'vencido', 'pagado'], true) ? $_GET['estado'] : '',
    'q'       => trim(mb_substr($_GET['q'] ?? '', 0, 60)),
];
$hayFiltros = $f['tarjeta'] || $f['desde'] || $f['hasta'] || $f['estado'] || $f['q'] !== '';

$where = ['t.dni_titular = :doc'];
$params = [':doc' => $doc];

if ($f['tarjeta']) { $where[] = 't.id_tarjeta = :tar';  $params[':tar'] = $f['tarjeta']; }
if ($f['desde'])   { $where[] = 'l.periodo >= :desde';  $params[':desde'] = $f['desde']; }
if ($f['hasta'])   { $where[] = 'l.periodo <= :hasta';  $params[':hasta'] = $f['hasta']; }
if ($f['estado'] === 'pagado')    $where[] = "l.estado_pago = 'Pagado'";
if ($f['estado'] === 'pendiente') $where[] = "l.estado_pago = 'Pendiente' AND l.fecha_vencimiento >= CURDATE()";
if ($f['estado'] === 'vencido')   $where[] = "l.estado_pago = 'Pendiente' AND l.fecha_vencimiento < CURDATE()";

// BUSCADOR: texto libre sobre nombre de tarjeta, banco, red, dígitos y período.
// También entiende nombres de meses ("mayo") y montos ("48210").
if ($f['q'] !== '') {
    $texto = '%' . $f['q'] . '%';
    $partes = ['t.alias LIKE :q1', 't.banco_emisor LIKE :q2', 't.red LIKE :q3', 't.ultimos4 LIKE :q4', 'l.periodo LIKE :q5'];
    $params += [':q1' => $texto, ':q2' => $texto, ':q3' => $texto, ':q4' => $texto, ':q5' => $texto];

    foreach (MESES as $num => $nombreMes) {
        if (mb_stripos($nombreMes, $f['q']) === 0 && mb_strlen($f['q']) >= 3) {
            $partes[] = 'l.periodo LIKE :mes';
            $params[':mes'] = '%-' . sprintf('%02d', $num);
            break;
        }
    }
    $montoBuscado = str_replace(['.', ',', '$', ' '], ['', '.', '', ''], $f['q']);
    if (is_numeric($montoBuscado)) {
        $partes[] = 'CAST(l.total_a_pagar AS CHAR) LIKE :monto';
        $params[':monto'] = '%' . $montoBuscado . '%';
    }
    $where[] = '(' . implode(' OR ', $partes) . ')';
}

$sql = 'SELECT l.*, t.banco_emisor, t.red, t.ultimos4, t.alias, t.estado AS estado_tarjeta
        FROM liquidaciones l
        INNER JOIN tarjetas t ON t.id_tarjeta = l.id_tarjeta
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY l.periodo DESC, l.fecha_vencimiento DESC';
$st = db()->prepare($sql);
$st->execute($params);
$resumenes = $st->fetchAll();

// Tarjetas del usuario para el filtro
$st = db()->prepare('SELECT * FROM tarjetas WHERE dni_titular = :doc ORDER BY banco_emisor');
$st->execute([':doc' => $doc]);
$misTarjetas = $st->fetchAll();

// ---------------------------------------------------------------- Comparativa mensual (sobre los resultados filtrados)
$porMes = [];
foreach ($resumenes as $r) {
    $porMes[$r['periodo']] = ($porMes[$r['periodo']] ?? 0) + (float)$r['total_a_pagar'];
}
ksort($porMes);
$porMes = array_slice($porMes, -12, null, true);   // últimos 12 períodos
$maximo = $porMes ? max($porMes) : 0;
$periodos = array_keys($porMes);
$ultimo = end($porMes) ?: 0;
$anterior = count($porMes) > 1 ? $porMes[$periodos[count($periodos) - 2]] : null;
$variacion = ($anterior) ? ($ultimo - $anterior) / $anterior * 100 : null;
$promedio = $porMes ? array_sum($porMes) / count($porMes) : 0;

$totalFiltrado = array_sum(array_column($resumenes, 'total_a_pagar'));
$urlActual = 'liquidaciones.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');

encabezado('Resúmenes', 'resumenes');
?>

<div class="cabecera-pagina">
    <div>
        <h1>Resúmenes</h1>
        <p>Tu historial de resúmenes de cuenta. Filtrá, buscá y compará mes a mes.</p>
    </div>
    <a href="resumen.php<?= $f['tarjeta'] ? '?tarjeta=' . $f['tarjeta'] : '' ?>" class="boton boton--primario"><?= icono('mas') ?>Registrar resumen</a>
</div>

<!-- FILTROS Y BUSCADOR -->
<form class="filtros" method="GET" action="liquidaciones.php" role="search">
    <div class="campo filtros__busqueda">
        <label for="q">Buscar</label>
        <div class="buscador">
            <?= icono('buscar') ?>
            <input type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Tarjeta, banco, mes o monto">
        </div>
    </div>
    <div class="campo">
        <label for="tarjeta">Tarjeta</label>
        <select id="tarjeta" name="tarjeta">
            <option value="0">Todas</option>
            <?php foreach ($misTarjetas as $t): ?>
                <option value="<?= (int)$t['id_tarjeta'] ?>" <?= $f['tarjeta'] == $t['id_tarjeta'] ? 'selected' : '' ?>><?= e(nombre_tarjeta($t)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="campo">
        <label for="desde">Desde el período</label>
        <input type="month" id="desde" name="desde" value="<?= e($f['desde']) ?>">
    </div>
    <div class="campo">
        <label for="hasta">Hasta el período</label>
        <input type="month" id="hasta" name="hasta" value="<?= e($f['hasta']) ?>">
    </div>
    <div class="campo">
        <label for="estado">Estado de pago</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            <option value="pendiente" <?= $f['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
            <option value="vencido" <?= $f['estado'] === 'vencido' ? 'selected' : '' ?>>Vencido</option>
            <option value="pagado" <?= $f['estado'] === 'pagado' ? 'selected' : '' ?>>Pagado</option>
        </select>
    </div>
    <div class="filtros__acciones">
        <button class="boton boton--primario boton--chico" type="submit">Aplicar</button>
        <?php if ($hayFiltros): ?><a class="boton boton--fantasma boton--chico" href="liquidaciones.php">Limpiar</a><?php endif; ?>
    </div>
</form>

<?php if ($resumenes): ?>

    <!-- COMPARATIVA DE TOTALES MENSUALES -->
    <section class="panel" style="margin-bottom:1.5rem">
        <div class="panel__cabecera">
            <h2>Total por mes</h2>
            <div class="chico texto-suave" style="text-align:right">
                <?php if ($variacion !== null): ?>
                    <?= periodo_legible(end($periodos)) ?> frente al mes anterior:
                    <span class="variacion variacion--<?= $variacion > 0 ? 'sube' : 'baja' ?>">
                        <?= $variacion > 0 ? '+' : '' ?><?= number_format($variacion, 1, ',', '.') ?> %
                    </span><br>
                <?php endif; ?>
                Promedio mensual: <strong class="numero"><?= pesos($promedio) ?></strong>
            </div>
        </div>
        <div class="grafico" role="img" aria-label="Gráfico de barras con el total a pagar de cada mes">
            <?php foreach ($porMes as $periodo => $total): ?>
                <div class="grafico__columna">
                    <div class="grafico__barra-zona">
                        <div class="grafico__barra<?= $periodo === end($periodos) ? ' grafico__barra--actual' : '' ?>"
                             style="--alto: <?= $maximo > 0 ? round($total / $maximo * 100, 1) : 0 ?>%"
                             title="<?= e(periodo_legible($periodo) . ': ' . pesos($total)) ?>">
                            <span><?= number_format($total / 1000, 0, ',', '.') ?> mil</span>
                        </div>
                    </div>
                    <span class="grafico__mes"><?= periodo_legible($periodo, true) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- LISTADO -->
    <div class="resultado-info">
        <span><?= count($resumenes) ?> <?= count($resumenes) === 1 ? 'resumen' : 'resúmenes' ?><?= $hayFiltros ? ' con estos filtros' : '' ?></span>
        <span>Suma: <strong class="numero"><?= pesos($totalFiltrado) ?></strong></span>
    </div>

    <div class="tabla-scroll">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Período</th><th>Tarjeta</th><th>Cierre</th><th>Vencimiento</th>
                    <th class="derecha">Total</th><th class="derecha">Mínimo</th><th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($resumenes as $r): $estado = estado_resumen($r); ?>
                <tr>
                    <td><strong><?= periodo_legible($r['periodo']) ?></strong></td>
                    <td><?= mini_tarjeta($r, $r['banco_emisor'] . ' ' . $r['ultimos4']) ?></td>
                    <td class="numero"><?= fecha_corta($r['fecha_cierre']) ?></td>
                    <td class="numero">
                        <?= fecha_corta($r['fecha_vencimiento']) ?>
                        <?php if ($estado === 'proximo' || $estado === 'vencido'): ?>
                            <div class="chico texto-suave"><?= texto_vencimiento($r['fecha_vencimiento']) ?></div>
                        <?php elseif ($estado === 'pagado' && $r['fecha_pago']): ?>
                            <div class="chico texto-suave">Pagado el <?= fecha_corta($r['fecha_pago']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="derecha numero"><strong><?= pesos($r['total_a_pagar']) ?></strong></td>
                    <td class="derecha numero"><?= pesos($r['pago_minimo']) ?></td>
                    <td><span class="insignia insignia--<?= $estado ?>"><?= texto_estado($estado) ?></span></td>
                    <td>
                        <div class="acciones">
                            <form method="POST">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="id" value="<?= (int)$r['id_liquidacion'] ?>">
                                <input type="hidden" name="volver" value="<?= e($urlActual) ?>">
                                <?php if ($r['estado_pago'] === 'Pendiente'): ?>
                                    <button class="accion accion--exito" name="accion" value="pagar" type="submit"><?= icono('check') ?>Pagado</button>
                                <?php else: ?>
                                    <button class="accion" name="accion" value="despagar" type="submit" title="Volver a pendiente"><?= icono('deshacer') ?>Deshacer</button>
                                <?php endif; ?>
                            </form>
                            <a class="accion" href="resumen.php?editar=<?= (int)$r['id_liquidacion'] ?>"><?= icono('editar') ?>Editar</a>
                            <form method="POST" onsubmit="return confirm('¿Eliminar el resumen de <?= e(periodo_legible($r['periodo'])) ?>?');">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="id" value="<?= (int)$r['id_liquidacion'] ?>">
                                <input type="hidden" name="volver" value="<?= e($urlActual) ?>">
                                <button class="accion accion--peligro" name="accion" value="eliminar" type="submit" aria-label="Eliminar"><?= icono('borrar') ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($hayFiltros): ?>
    <div class="vacio">
        <?= icono('buscar') ?>
        <h3>No hay resúmenes que coincidan</h3>
        <p>Probá con otro término o ampliá el rango de períodos.</p>
        <a class="boton boton--secundario" href="liquidaciones.php">Ver todos los resúmenes</a>
    </div>
<?php else: ?>
    <div class="vacio">
        <?= icono('resumen') ?>
        <h3>Todavía no registraste resúmenes</h3>
        <p>Cuando te llegue el resumen de una tarjeta, cargalo para seguir sus vencimientos.</p>
        <a class="boton boton--primario" href="resumen.php"><?= icono('mas') ?>Registrar mi primer resumen</a>
    </div>
<?php endif; ?>

<?php pie(); ?>
