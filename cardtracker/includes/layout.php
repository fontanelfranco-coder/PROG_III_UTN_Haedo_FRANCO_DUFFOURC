<?php
/**
 * includes/layout.php
 * Encabezado, navegación, pie e íconos compartidos.
 * Así todas las páginas tienen la misma identidad visual.
 */

/** Íconos SVG de trazo (24x24). Se imprimen en línea para no depender de archivos externos. */
function icono(string $nombre, string $clase = 'icono'): string
{
    $trazos = [
        'tarjeta'   => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6.5 15h4"/>',
        'resumen'   => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z"/><path d="M9 8h6M9 12h6"/>',
        'historial' => '<path d="M4 19V9M10 19V5M16 19v-7M21 19H3"/>',
        'campana'   => '<path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15L6 16z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
        'buscar'    => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
        'escudo'    => '<path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.2 7.5 9.5 4.3-1.3 7.5-4.9 7.5-9.5V6L12 3z"/><path d="m9 12 2 2 4-4"/>',
        'correo'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>',
        'salir'     => '<path d="M14 4h4.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H14"/><path d="M10 8l-4 4 4 4M6 12h10"/>',
        'mas'       => '<path d="M12 5v14M5 12h14"/>',
        'editar'    => '<path d="M4 20h4L19 9l-4-4L4 16v4z"/><path d="m13.5 6.5 4 4"/>',
        'borrar'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'check'     => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'usuario'   => '<circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-3.5 4.5-5 8-5s6.5 1.5 8 5"/>',
        'usuarios'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 19c1-3 3.5-4.5 6.5-4.5s5.5 1.5 6.5 4.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.8c1.7.6 2.9 2 3.5 4.2"/>',
        'pausa'     => '<circle cx="12" cy="12" r="9"/><path d="M10 9v6M14 9v6"/>',
        'play'      => '<circle cx="12" cy="12" r="9"/><path d="m10 8.5 5 3.5-5 3.5v-7z"/>',
        'alerta'    => '<path d="M12 4 2.5 20h19L12 4z"/><path d="M12 10v4.5M12 17.5v.01"/>',
        'reloj'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'volver'    => '<path d="M10 6 4 12l6 6M4 12h16"/>',
        'ubicacion' => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'deshacer'  => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/>',
    ];
    return '<svg class="' . e($clase) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
         . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($trazos[$nombre] ?? '') . '</svg>';
}

/** Logotipo: dos tarjetas superpuestas */
function logotipo(): string
{
    return '<svg class="logo__marca" viewBox="0 0 32 32" aria-hidden="true">'
         . '<rect x="3" y="9" width="20" height="14" rx="3.5" fill="var(--c-lila)"/>'
         . '<rect x="9" y="5" width="20" height="14" rx="3.5" fill="var(--c-primario)"/>'
         . '<rect x="12" y="13" width="6" height="2.4" rx="1.2" fill="var(--c-papel)"/>'
         . '</svg><span>CardTracker</span>';
}

/**
 * Imprime <head>, la barra de navegación según el rol y los mensajes flash.
 * $activo: clave del enlace a resaltar. $claseMain: permite páginas de ancho completo.
 */
function encabezado(string $titulo, string $activo = '', string $claseMain = 'contenido', bool $conMensajes = true): void
{
    $usuario = usuario_actual();
    $rol = $usuario['rol'] ?? null;

    // Enlaces de cada tipo de usuario: [clave, href, texto, contador]
    if ($rol === 'General') {
        $cantidadAvisos = count(obtener_avisos($usuario['documento']));
        $enlaces = [
            ['inicio', 'generales.php', 'Inicio', 0],
            ['tarjetas', 'tarjetas.php', 'Mis tarjetas', 0],
            ['resumenes', 'liquidaciones.php', 'Resúmenes', 0],
            ['contacto', 'contacto.php', 'Contacto', 0],
        ];
    } elseif ($rol === 'Administrador') {
        $pendientes = (int)db()->query("SELECT COUNT(*) FROM consultas WHERE resuelto = 'No'")->fetchColumn();
        $enlaces = [
            ['panel', 'admin.php', 'Panel', 0],
            ['gestion', 'admin_abm.php', 'Usuarios y tarjetas', 0],
            ['busqueda', 'admin_busqueda.php', 'Búsqueda', 0],
            ['consultas', 'admin_consultas.php', 'Consultas', $pendientes],
        ];
    } else {
        $enlaces = [
            ['inicio', 'index.php', 'Inicio', 0],
            ['funciones', 'index.php#funciones', 'Funciones', 0],
            ['como', 'index.php#como-funciona', 'Cómo funciona', 0],
            ['contacto', 'contacto.php', 'Contacto', 0],
        ];
    }
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?> | CardTracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,650;12..96,750&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="banco.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect x='3' y='9' width='20' height='14' rx='3.5' fill='%23B9B3E8'/><rect x='9' y='5' width='20' height='14' rx='3.5' fill='%232F6B66'/></svg>">
</head>
<body class="<?= $rol === 'Administrador' ? 'modo-admin' : '' ?>">
<a class="saltar" href="#principal">Saltar al contenido</a>

<header class="barra">
    <div class="barra__interior">
        <a class="logo" href="<?= $rol ? panel_de($rol) : 'index.php' ?>"><?= logotipo() ?></a>
        <?php if ($rol === 'Administrador'): ?>
            <span class="etiqueta-admin">Administración</span>
        <?php endif; ?>

        <input type="checkbox" id="menu-movil" class="menu-movil__control">
        <label for="menu-movil" class="menu-movil__boton" aria-label="Abrir menú"><?= icono('menu') ?></label>

        <nav class="nav" aria-label="Principal">
            <ul class="nav__lista">
                <?php foreach ($enlaces as [$clave, $href, $texto, $contador]): ?>
                    <li>
                        <a href="<?= $href ?>" class="nav__enlace<?= $clave === $activo ? ' es-activo' : '' ?>"
                           <?= $clave === $activo ? 'aria-current="page"' : '' ?>>
                            <?= e($texto) ?>
                            <?php if ($contador > 0): ?><span class="contador"><?= $contador ?></span><?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="nav__acciones">
                <?php if ($rol === 'General'): ?>
                    <a href="generales.php#avisos" class="campana<?= $cantidadAvisos ? ' tiene-avisos' : '' ?>"
                       title="<?= $cantidadAvisos ? $cantidadAvisos . ' pagos para revisar' : 'Sin avisos' ?>">
                        <?= icono('campana') ?>
                        <?php if ($cantidadAvisos): ?><span class="contador"><?= $cantidadAvisos ?></span><?php endif; ?>
                        <span class="solo-lector">Avisos</span>
                    </a>
                <?php endif; ?>

                <?php if ($usuario): ?>
                    <span class="nav__usuario"><?= icono('usuario') ?><?= e($usuario['nombre']) ?></span>
                    <a href="logout.php" class="boton boton--fantasma boton--chico"><?= icono('salir') ?>Salir</a>
                <?php else: ?>
                    <a href="index.php#ingresar" class="boton boton--fantasma boton--chico">Ingresar</a>
                    <a href="registro.php" class="boton boton--primario boton--chico">Crear cuenta</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>

<main id="principal" class="<?= e($claseMain) ?>">
    <?php if ($conMensajes) echo mensajes_flash(); ?>
<?php
}

/** Mensajes flash pendientes, listos para imprimir */
function mensajes_flash(): string
{
    $flashes = obtener_flashes();
    if (!$flashes) return '';
    ob_start(); ?>
        <div class="mensajes">
            <?php foreach ($flashes as [$tipo, $texto]): ?>
                <div class="mensaje mensaje--<?= e($tipo) ?>" role="<?= $tipo === 'error' ? 'alert' : 'status' ?>">
                    <?= icono($tipo === 'error' ? 'alerta' : ($tipo === 'exito' ? 'check' : 'campana')) ?>
                    <p><?= e($texto) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
<?php
    return ob_get_clean();
}

function pie(): void
{
    ?>
</main>

<footer class="pie">
    <div class="pie__interior">
        <a class="logo logo--chico" href="index.php"><?= logotipo() ?></a>
        <p>Tus tarjetas de distintos bancos en un solo lugar. Trabajo práctico de Programación IV.</p>
        <p class="pie__privacidad"><?= icono('escudo') ?>Solo guardamos los últimos cuatro dígitos de cada tarjeta.</p>
    </div>
</footer>
</body>
</html>
<?php
}

/** Dibuja una tarjeta con el color de su red. Solo usa los últimos 4 dígitos. */
function tarjeta_visual(array $t): string
{
    $apagada = $t['estado'] !== 'Activa' ? ' tarjeta-visual--apagada' : '';
    $alias = $t['alias'] !== null && $t['alias'] !== '' ? $t['alias'] : 'Sin nombre';
    $estado = $t['estado'] !== 'Activa' ? '<span class="tv__estado">' . e($t['estado']) . '</span>' : '';
    return '<div class="tarjeta-visual tarjeta-visual--' . clase_red($t['red']) . $apagada . '">'
         . '<div class="tv__arriba"><span class="tv__banco">' . e($t['banco_emisor']) . '</span>'
         . '<span class="tv__red">' . e($t['red']) . '</span></div>'
         . '<div class="tv__chip"></div>'
         . '<div class="tv__numero"><span>••••</span>' . e($t['ultimos4']) . '</div>'
         . '<div class="tv__abajo"><span class="tv__alias">' . e($alias) . '</span>' . $estado . '</div>'
         . '</div>';
}

/** Muestra chica de tarjeta para tablas */
function mini_tarjeta(array $t, string $detalle = ''): string
{
    $titulo = $t['alias'] ?? '' ?: $t['banco_emisor'];
    $sub = $detalle !== '' ? $detalle : $t['red'] . ' terminada en ' . $t['ultimos4'];
    return '<div class="mini-tarjeta"><span class="mini-tarjeta__muestra mini-tarjeta__muestra--' . clase_red($t['red']) . '"></span>'
         . '<div><strong>' . e($titulo) . '</strong><span>' . e($sub) . '</span></div></div>';
}
