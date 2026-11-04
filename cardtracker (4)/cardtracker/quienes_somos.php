<?php
// quienes_somos.php — Presentación del grupo y del trabajo práctico
require 'includes/config.php';
require 'includes/datos.php';
require 'includes/layout.php';

// Busca la foto del grupo en la carpeta img/ (el primer formato que exista)
$imagen = null;
foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
    if (is_file(__DIR__ . "/img/grupo.$extension")) {
        $imagen = "img/grupo.$extension";
        break;
    }
}

// El último parámetro (true) activa el fondo verde con contenedor crema
encabezado('Quiénes somos', 'nosotros', 'contenido', true, true);
?>

<div class="cabecera-pagina">
    <div>
        <h1>Quiénes somos</h1>
        <!-- EDITAR: una frase corta de presentación -->
        <p>Somos un grupo de desarrolladores ambiciosos estudiando en la Universidad Tecnológica Nacional que buscan expandir sus conocimientos, aprender a diseñar, crear aplicaciones y servicios de calidad.</p>
    </div>
</div>

<div class="nosotros">

    <!-- IMAGEN: guardá la foto como img/grupo.jpg (o .png / .webp) y aparece sola acá -->
    <figure class="nosotros__imagen" style="margin:0">
        <?php if ($imagen): ?>
            <!-- EDITAR: describí la foto en el atributo alt (lo leen los lectores de pantalla) -->
            <img src="<?= e($imagen) ?>" alt="Foto del grupo de trabajo de CardTracker">
        <?php else: ?>
            <div class="nosotros__hueco">
                <?= icono('usuarios') ?>
                <strong>Acá va la foto del grupo</strong>
                <span class="chico">Guardala como <code>img/grupo.jpg</code> y recargá la página.</span>
            </div>
        <?php endif; ?>
    </figure>

    <div class="nosotros__texto">
        <section class="panel">
            <h2>Nuestro grupo</h2>
            <!-- EDITAR: contá brevemente quiénes son, qué estudian y cómo se organizaron -->
            <p>
                Tres estudiantes Argentinos que buscan perseguir su vocación por el diseño de software, dando nuestros primeros pasos mientras estudiamos y trabajamos; este es nuestro primer proyecto juntos.
            </p>
            <!-- EDITAR: un <li> por integrante. Para sumar o quitar, copiá o borrá una línea -->
            <ul class="integrantes">
                <li><?= icono('usuario') ?>Franco Duffourc Fontanel</li>
                <li><?= icono('usuario') ?>Axel David Saccomanno</li>
                <li><?= icono('usuario') ?>Lautaro Abuin Suarez</li>
            </ul>
        </section>

        <section class="panel">
            <h2>El trabajo práctico</h2>
            <!-- EDITAR: ajustá este texto a su gusto -->
            <p>
                CardTracker es una aplicación web que reúne en un solo lugar tarjetas de crédito de distintos bancos:
                permite cargar sus resúmenes, consultar el historial y recibir avisos antes de cada
                vencimiento.
            </p>
            <p>
                Cuenta con dos perfiles: los clientes gestionan sus propias tarjetas y resúmenes, y
                los administradores gestionan usuarios, tarjetas y consultas, con acceso limitado a
                los datos personales.
            </p>
            <!-- EDITAR: tecnologías usadas (agregá o quitá las que correspondan) -->
            <ul class="etiquetas">
                <li>PHP</li>
                <li>MySQL</li>
                <li>HTML</li>
                <li>CSS</li>
                <li>JavaScript</li>
            </ul>
        </section>
    </div>

</div>

<?php pie(); ?>
