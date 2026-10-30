<?php
// admin_consultas.php — Bandeja de consultas recibidas desde Contacto
require 'includes/config.php';
require 'includes/datos.php';
require 'includes/layout.php';

$yo = requerir_rol('Administrador');
$vista = ($_GET['vista'] ?? 'pendientes') === 'resueltas' ? 'resueltas' : 'pendientes';
$aqui = 'admin_consultas.php?vista=' . $vista;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf($aqui);
    $id = (int)($_POST['id_consulta'] ?? 0);
    $nuevoEstado = ($_POST['accion'] ?? '') === 'reabrir' ? 'No' : 'Si';
    $st = db()->prepare('UPDATE consultas SET resuelto = :r WHERE id_consulta = :id');
    $st->execute([':r' => $nuevoEstado, ':id' => $id]);
    flash('exito', $nuevoEstado === 'Si' ? 'Marcaste la consulta como resuelta.' : 'La consulta volvió a la bandeja de pendientes.');
    redirigir($aqui);   // patrón POST-redirect-GET: F5 no reenvía el formulario
}

$cuenta = db()->query("SELECT SUM(resuelto = 'No') AS pendientes, SUM(resuelto = 'Si') AS resueltas FROM consultas")->fetch();

// Pendientes: las más viejas primero. Resueltas: las más nuevas primero.
$sql = $vista === 'pendientes'
    ? "SELECT * FROM consultas WHERE resuelto = 'No' ORDER BY fecha_envio ASC"
    : "SELECT * FROM consultas WHERE resuelto = 'Si' ORDER BY fecha_envio DESC LIMIT 50";
$consultas = db()->query($sql)->fetchAll();

$textoCategoria = ['Alta' => 'Alta de tarjeta', 'Baja' => 'Baja', 'Mensaje' => 'Consulta general'];

encabezado('Consultas', 'consultas');
?>

<div class="cabecera-pagina">
    <div>
        <h1>Consultas</h1>
        <p>Mensajes enviados desde la página de contacto por clientes y visitantes.</p>
    </div>
</div>

<nav class="pestanas" aria-label="Filtrar consultas">
    <a href="admin_consultas.php?vista=pendientes" class="<?= $vista === 'pendientes' ? 'es-activa' : '' ?>">
        Pendientes <?php if ($cuenta['pendientes']): ?><span class="contador"><?= (int)$cuenta['pendientes'] ?></span><?php endif; ?>
    </a>
    <a href="admin_consultas.php?vista=resueltas" class="<?= $vista === 'resueltas' ? 'es-activa' : '' ?>">Resueltas</a>
</nav>

<?php if ($consultas): ?>
    <div class="consultas">
        <?php foreach ($consultas as $c): ?>
            <article class="panel consulta">
                <div class="consulta__cabecera">
                    <div class="consulta__autor">
                        <strong><?= e($c['nombre']) ?></strong>
                        <span><?= e($c['email']) ?></span>
                    </div>
                    <span class="categoria categoria--<?= strtolower($c['categoria']) ?>"><?= e($textoCategoria[$c['categoria']] ?? $c['categoria']) ?></span>
                </div>
                <p class="consulta__mensaje"><?= e($c['mensaje']) ?></p>
                <div class="consulta__pie">
                    <span class="chico texto-suave">Recibida el <?= date('d/m/Y \a \l\a\s H:i', strtotime($c['fecha_envio'])) ?></span>
                    <div class="acciones">
                        <a class="accion" href="mailto:<?= e($c['email']) ?>?subject=<?= rawurlencode('Respuesta a tu consulta en CardTracker') ?>"><?= icono('correo') ?>Responder por correo</a>
                        <form method="POST">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="id_consulta" value="<?= (int)$c['id_consulta'] ?>">
                            <?php if ($vista === 'pendientes'): ?>
                                <button class="boton boton--secundario boton--chico" name="accion" value="resolver" type="submit"><?= icono('check') ?>Marcar como resuelta</button>
                            <?php else: ?>
                                <button class="accion" name="accion" value="reabrir" type="submit"><?= icono('deshacer') ?>Volver a pendientes</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="vacio">
        <?= icono('check') ?>
        <h3><?= $vista === 'pendientes' ? 'No hay consultas pendientes' : 'Todavía no hay consultas resueltas' ?></h3>
        <p><?= $vista === 'pendientes' ? 'Las nuevas consultas aparecen acá, de la más antigua a la más reciente.' : 'Cuando resuelvas una consulta, la vas a encontrar acá.' ?></p>
    </div>
<?php endif; ?>

<?php pie(); ?>
