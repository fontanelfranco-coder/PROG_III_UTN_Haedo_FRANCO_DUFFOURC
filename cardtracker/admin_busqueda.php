<?php
// admin_busqueda.php — Buscador global del Administrador
// Cada tabla define QUÉ columnas se pueden ver y en cuáles se busca.
// Así el administrador nunca ve contraseñas, fechas de nacimiento, alias ni importes.
require 'includes/config.php';
require 'includes/datos.php';
require 'includes/layout.php';

$yo = requerir_rol('Administrador');

$tablas = [
    'usuarios' => [
        'titulo'  => 'Usuarios',
        'sql'     => "SELECT documento AS 'Documento', tipo_doc AS 'Tipo', nombre AS 'Nombre', apellido AS 'Apellido',
                             email AS 'Correo', usuario AS 'Usuario', rol AS 'Rol', DATE_FORMAT(creado_el, '%d/%m/%Y') AS 'Alta'
                      FROM usuarios",
        'buscar'  => ['documento', 'nombre', 'apellido', 'email', 'usuario', 'rol'],
        'orden'   => 'apellido, nombre',
        'ocultas' => 'contraseña (hash) y fecha de nacimiento',
    ],
    'tarjetas' => [
        'titulo'  => 'Tarjetas',
        'sql'     => "SELECT t.id_tarjeta AS 'N.º', CONCAT(u.apellido, ', ', u.nombre) AS 'Titular', t.dni_titular AS 'Documento',
                             t.banco_emisor AS 'Banco', t.red AS 'Red', t.ultimos4 AS 'Últimos 4', t.estado AS 'Estado'
                      FROM tarjetas t INNER JOIN usuarios u ON u.documento = t.dni_titular",
        'buscar'  => ['t.dni_titular', 'u.apellido', 'u.nombre', 't.banco_emisor', 't.red', 't.ultimos4', 't.estado'],
        'orden'   => 'u.apellido, t.banco_emisor',
        'ocultas' => 'nombre personalizado de la tarjeta',
    ],
    'liquidaciones' => [
        'titulo'  => 'Resúmenes',
        'sql'     => "SELECT l.id_liquidacion AS 'N.º', t.dni_titular AS 'Documento',
                             CONCAT(t.banco_emisor, ' ', t.red, ' ', t.ultimos4) AS 'Tarjeta', l.periodo AS 'Período',
                             DATE_FORMAT(l.fecha_vencimiento, '%d/%m/%Y') AS 'Vencimiento',
                             IF(l.estado_pago = 'Pendiente' AND l.fecha_vencimiento < CURDATE(), 'Vencido', l.estado_pago) AS 'Estado'
                      FROM liquidaciones l INNER JOIN tarjetas t ON t.id_tarjeta = l.id_tarjeta",
        'buscar'  => ['t.dni_titular', 't.banco_emisor', 't.red', 't.ultimos4', 'l.periodo', 'l.estado_pago'],
        'orden'   => 'l.periodo DESC',
        'ocultas' => 'montos total y mínimo',
    ],
    'consultas' => [
        'titulo'  => 'Consultas',
        'sql'     => "SELECT id_consulta AS 'N.º', nombre AS 'Nombre', email AS 'Correo', categoria AS 'Motivo',
                             LEFT(mensaje, 80) AS 'Mensaje', IF(resuelto = 'Si', 'Resuelta', 'Pendiente') AS 'Estado',
                             DATE_FORMAT(fecha_envio, '%d/%m/%Y') AS 'Fecha'
                      FROM consultas",
        'buscar'  => ['nombre', 'email', 'categoria', 'mensaje'],
        'orden'   => 'fecha_envio DESC',
        'ocultas' => '',
    ],
];

$tabla = array_key_exists($_GET['tabla'] ?? '', $tablas) ? $_GET['tabla'] : '';
$q = trim(mb_substr($_GET['q'] ?? '', 0, 60));
$resultados = [];
$limite = 200;

if ($tabla) {
    $cfg = $tablas[$tabla];
    $sql = $cfg['sql'];
    $params = [];
    if ($q !== '') {
        // La búsqueda se hace en la base con LIKE y parámetros (no se trae toda la tabla a PHP)
        $condiciones = [];
        foreach ($cfg['buscar'] as $i => $columna) {
            $condiciones[] = "$columna LIKE :q$i";   // los nombres de columna salen de nuestra lista, no del usuario
            $params[":q$i"] = '%' . $q . '%';
        }
        $sql .= ' WHERE ' . implode(' OR ', $condiciones);
    }
    $sql .= ' ORDER BY ' . $cfg['orden'] . ' LIMIT ' . $limite;
    $st = db()->prepare($sql);
    $st->execute($params);
    $resultados = $st->fetchAll();
}

/** Resalta el término buscado dentro de un valor (ya escapado) */
function resaltar(string $valor, string $q): string
{
    $seguro = e($valor);
    if ($q === '') return $seguro;
    return preg_replace('/' . preg_quote(e($q), '/') . '/iu', '<mark>$0</mark>', $seguro);
}

encabezado('Búsqueda', 'busqueda');
?>

<div class="cabecera-pagina">
    <div>
        <h1>Búsqueda</h1>
        <p>Consultá los registros del sistema. Elegí una tabla y, si querés, un término para filtrar.</p>
    </div>
</div>

<form class="filtros" method="GET" role="search">
    <div class="campo">
        <label for="tabla">Tabla</label>
        <select name="tabla" id="tabla" required>
            <option value="" disabled <?= $tabla ? '' : 'selected' ?>>Elegí una tabla</option>
            <?php foreach ($tablas as $clave => $cfg): ?>
                <option value="<?= $clave ?>" <?= $tabla === $clave ? 'selected' : '' ?>><?= e($cfg['titulo']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="campo filtros__busqueda">
        <label for="q">Buscar</label>
        <div class="buscador">
            <?= icono('buscar') ?>
            <input type="search" name="q" id="q" value="<?= e($q) ?>" placeholder="Ej.: Galicia, Gómez, 20123456, 2026-05">
        </div>
    </div>
    <div class="filtros__acciones">
        <button class="boton boton--primario boton--chico" type="submit">Buscar</button>
        <?php if ($tabla): ?><a class="boton boton--fantasma boton--chico" href="admin_busqueda.php">Limpiar</a><?php endif; ?>
    </div>
</form>

<?php if (!$tabla): ?>
    <div class="vacio">
        <?= icono('buscar') ?>
        <h3>Elegí una tabla para empezar</h3>
        <p>Podés buscar en usuarios, tarjetas, resúmenes y consultas.</p>
    </div>
<?php else: ?>
    <div class="resultado-info">
        <span>
            <?= count($resultados) ?> <?= count($resultados) === 1 ? 'resultado' : 'resultados' ?> en <?= e($tablas[$tabla]['titulo']) ?>
            <?= $q !== '' ? 'para «' . e($q) . '»' : '' ?>
            <?= count($resultados) === $limite ? "(se muestran los primeros $limite)" : '' ?>
        </span>
    </div>

    <?php if ($resultados): ?>
        <div class="tabla-scroll">
            <table class="tabla">
                <thead>
                    <tr><?php foreach (array_keys($resultados[0]) as $col): ?><th><?= e($col) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                <?php foreach ($resultados as $fila): ?>
                    <tr><?php foreach ($fila as $valor): ?><td><?= resaltar((string)$valor, $q) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="vacio"><?= icono('buscar') ?><h3>Sin coincidencias</h3><p>Probá con otro término o elegí otra tabla.</p></div>
    <?php endif; ?>

    <?php if ($tablas[$tabla]['ocultas']): ?>
        <p class="privacidad-nota"><?= icono('escudo') ?>Por privacidad no se muestran: <?= e($tablas[$tabla]['ocultas']) ?>.</p>
    <?php endif; ?>
<?php endif; ?>

<?php pie(); ?>
