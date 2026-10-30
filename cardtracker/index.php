<?php
// index.php — Página principal: presentación del proyecto + ingreso
require 'includes/config.php';
require 'includes/datos.php';
require 'includes/layout.php';

// Si ya tiene sesión, lo llevamos directo a su panel
if ($u = usuario_actual()) {
    redirigir(panel_de($u['rol']));
}

$previos = datos_previos();   // documento y usuario si el ingreso falló

// Tarjetas de ejemplo para la ilustración de la portada
$ejemplos = [
    ['banco_emisor' => 'Banco Nación', 'red' => 'Cabal', 'ultimos4' => '5566', 'alias' => 'Para emergencias', 'estado' => 'Activa'],
    ['banco_emisor' => 'Banco Santander', 'red' => 'Mastercard', 'ultimos4' => '4410', 'alias' => 'Viajes', 'estado' => 'Activa'],
    ['banco_emisor' => 'Banco Galicia', 'red' => 'Visa', 'ultimos4' => '9876', 'alias' => 'Gastos del súper', 'estado' => 'Activa'],
];

// Los mensajes (errores de ingreso, cuenta creada, sesión cerrada) se muestran junto al formulario
encabezado('Todas tus tarjetas en un solo lugar', 'inicio', 'ancho-completo', false);
?>

<section class="portada">
    <div>
        <h1>Todas tus tarjetas, un solo calendario de pagos.</h1>
        <p class="portada__bajada">
            Cargá las tarjetas de cada banco, registrá sus resúmenes y CardTracker te avisa
            antes de cada vencimiento. Sin planillas, sin mirar cinco apps distintas.
        </p>
        <div class="portada__botones">
            <a href="registro.php" class="boton boton--primario">Crear cuenta gratis</a>
            <a href="#ingresar" class="boton boton--secundario">Ya tengo cuenta</a>
        </div>
    </div>

    <div class="pila" aria-hidden="true">
        <?php foreach ($ejemplos as $t) echo tarjeta_visual($t); ?>
        <div class="pila__aviso">
            <span class="aviso__icono"><?= icono('campana') ?></span>
            <div>
                <strong>Gastos del súper vence en 3 días</strong>
                <span>Total a pagar $ 48.210,40</span>
            </div>
        </div>
    </div>
</section>

<section class="banda" id="funciones">
    <div class="banda__interior">
        <div class="bloque__intro">
            <h2>Qué podés hacer con CardTracker</h2>
            <p>Pensado para quien tiene tarjetas en más de un banco y quiere saber, de un vistazo, cuánto debe y cuándo.</p>
        </div>

        <div class="funciones">
            <article class="funcion">
                <div class="funcion__icono"><?= icono('tarjeta') ?></div>
                <h3>Tus tarjetas, ordenadas</h3>
                <p>Cada tarjeta se identifica por su banco, su red y sus últimos cuatro dígitos.</p>
                <ul>
                    <li><?= icono('check') ?>Elegí banco emisor y red: Visa, Mastercard, American Express, Cabal o Naranja.</li>
                    <li><?= icono('check') ?>Ponele un nombre propio, como «Viajes» o «Gastos del súper».</li>
                    <li><?= icono('check') ?>Editala, desactivala o eliminala cuando quieras.</li>
                </ul>
            </article>

            <article class="funcion">
                <div class="funcion__icono"><?= icono('resumen') ?></div>
                <h3>Cada resumen en su lugar</h3>
                <p>Registrá los datos del resumen apenas te llega, en menos de un minuto.</p>
                <ul>
                    <li><?= icono('check') ?>Período de facturación y fecha de cierre.</li>
                    <li><?= icono('check') ?>Fecha de vencimiento del pago.</li>
                    <li><?= icono('check') ?>Monto total y pago mínimo.</li>
                </ul>
            </article>

            <article class="funcion">
                <div class="funcion__icono"><?= icono('historial') ?></div>
                <h3>Un historial que responde</h3>
                <p>Encontrá cualquier resumen y mirá cómo evolucionan tus gastos.</p>
                <ul>
                    <li><?= icono('check') ?>Filtrá por período de facturación o por estado de pago.</li>
                    <li><?= icono('check') ?>Buscá un resumen puntual por tarjeta, banco o mes.</li>
                    <li><?= icono('check') ?>Compará los totales de cada mes en un gráfico.</li>
                </ul>
            </article>

            <article class="funcion">
                <div class="funcion__icono"><?= icono('campana') ?></div>
                <h3>Avisos antes de que sea tarde</h3>
                <p>Al ingresar ves primero lo que necesita tu atención.</p>
                <ul>
                    <li><?= icono('check') ?>Te avisamos cuando un pago vence en los próximos <?= DIAS_AVISO ?> días.</li>
                    <li><?= icono('check') ?>Los pagos vencidos quedan marcados hasta que los registres como pagados.</li>
                </ul>
            </article>
        </div>
    </div>
</section>

<section class="bloque" id="como-funciona">
    <div class="bloque__intro">
        <h2>Empezar lleva tres pasos</h2>
    </div>
    <ol class="pasos">
        <li class="paso">
            <h3>Creá tu cuenta</h3>
            <p>Con tu documento, un usuario y una contraseña de al menos 8 caracteres.</p>
        </li>
        <li class="paso">
            <h3>Cargá tus tarjetas</h3>
            <p>Solo necesitás el banco, la red y los últimos cuatro dígitos. Nunca el número completo.</p>
        </li>
        <li class="paso">
            <h3>Registrá cada resumen</h3>
            <p>Desde ahí CardTracker calcula qué vence, qué está pago y cuánto gastaste por mes.</p>
        </li>
    </ol>
</section>

<section class="banda" id="ingresar">
    <div class="banda__interior ingreso">
        <div class="ingreso__texto">
            <h2>Ingresá a tu cuenta</h2>
            <p>Usá el documento y el usuario con los que te registraste.</p>
            <ul class="ingreso__lista">
                <li><?= icono('escudo') ?>Tu contraseña se guarda protegida, nunca en texto plano.</li>
                <li><?= icono('tarjeta') ?>Solo guardamos los últimos cuatro dígitos.</li>
                <li><?= icono('reloj') ?>La sesión se cierra sola tras <?= MINUTOS_INACTIVIDAD ?> minutos sin actividad.</li>
            </ul>
        </div>

        <div class="panel panel--formulario">
            <?= mensajes_flash() ?>
            <form action="ingreso.php" method="POST" class="formulario">
                <?= campo_csrf() ?>
                <div class="fila">
                    <div class="campo">
                        <label for="tipo_doc">Tipo de documento</label>
                        <select name="tipo_doc" id="tipo_doc">
                            <?php foreach (TIPOS_DOC as $tipo): ?>
                                <option value="<?= $tipo ?>" <?= ($previos['tipo_doc'] ?? '') === $tipo ? 'selected' : '' ?>>
                                    <?= $tipo === 'DNI' ? 'DNI' : 'Pasaporte' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="documento">Número</label>
                        <input type="text" name="documento" id="documento" required maxlength="20"
                               inputmode="numeric" autocomplete="off" value="<?= e($previos['documento'] ?? '') ?>">
                    </div>
                </div>
                <div class="campo">
                    <label for="usuario">Usuario</label>
                    <input type="text" name="usuario" id="usuario" required maxlength="30"
                           autocomplete="username" value="<?= e($previos['usuario'] ?? '') ?>">
                </div>
                <div class="campo">
                    <label for="password">Contraseña</label>
                    <input type="password" name="password" id="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="boton boton--primario boton--bloque">Ingresar</button>
            </form>
            <div class="enlaces-formulario">
                <a href="registro.php">¿No tenés cuenta? Creala acá</a>
                <a href="contacto.php">¿Problemas para ingresar? Escribinos</a>
            </div>
        </div>
    </div>
</section>

<?php pie(); ?>
