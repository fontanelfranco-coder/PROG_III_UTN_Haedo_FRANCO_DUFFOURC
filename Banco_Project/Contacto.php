<?php
// contacto.php
session_start();

// CONTROL DE SEGURIDAD
if (!isset($_SESSION['documento']) || $_SESSION['rol'] !== 'General') {
    header("Location: ingreso.html");
    exit();
}

$documento_sesion = $_SESSION['documento'];

$host = 'localhost';
$dbname = 'mi_banco_db';
$username = 'root';
$password_db = ''; 

$mensajeExito = "";
$mensajeError = "";

try {
    $conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password_db);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Si el usuario envió el formulario
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        
        $categoria = $_POST['categoria'];
        $mensaje_texto = $_POST['mensaje'];

        // 1. Buscamos los datos personales del usuario logueado
        $queryUser = "SELECT nombre, apellido, email FROM usuarios WHERE documento = :doc";
        $stmtUser = $conexion->prepare($queryUser);
        $stmtUser->execute([':doc' => $documento_sesion]);
        $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

        $nombreCompleto = $usuario['nombre'] . ' ' . $usuario['apellido'];
        $emailUsuario = $usuario['email'];

        // 2. Insertamos la consulta en la base de datos
        $queryInsert = "INSERT INTO consultas (nombre, email, categoria, mensaje) 
                        VALUES (:nom, :email, :cat, :msg)";
        $stmtInsert = $conexion->prepare($queryInsert);
        $resultado = $stmtInsert->execute([
            ':nom' => $nombreCompleto,
            ':email' => $emailUsuario,
            ':cat' => $categoria,
            ':msg' => $mensaje_texto
        ]);

        if ($resultado) {
            $mensajeExito = "¡Tu solicitud ha sido enviada! Los administradores la revisarán a la brevedad.";
            
            // 3. REQUISITO DEL PROFE: Función mail() simulada
            $destinatario = "admin@cardtracker.com"; // Dirección simulada del sitio
            $asunto = "Nueva Consulta Web - " . $categoria;
            $cuerpo = "De: $nombreCompleto\nEmail: $emailUsuario\n\nMensaje/Detalle:\n$mensaje_texto";
            
            // Usamos @ para suprimir advertencias, ya que localhost no puede enviar mails reales sin configuración SMTP.
            @mail($destinatario, $asunto, $cuerpo); 
        } else {
            $mensajeError = "Ocurrió un error al procesar tu solicitud.";
        }
    }
} catch (PDOException $e) {
    $mensajeError = "Error de conexión: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto - CardTracker</title>
    <link rel="stylesheet" href="Banco.css">
</head>
<body>

    <!-- BARRA SUPERIOR -->
    <header class="navbar">
        <div class="navbar-brand">
            <h1>CardTracker</h1>
        </div>
        <nav class="navbar-menu">
            <a href="generales.php">Mi Perfil</a>
            <a href="liquidaciones.php">Liquidaciones</a>
            <a href="contacto.php" class="active">Contacto</a>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </nav>
    </header>

    <main class="dashboard-container">
        
        <h2 class="section-title">Centro de Atención al Cliente</h2>
        
        <!-- Contenedor dividido en 2 columnas: Formulario y Mapa -->
        <div class="contact-layout">
            
            <!-- Columna Izquierda: Formulario -->
            <section class="contact-form-section">
                <h3>Envianos tu solicitud</h3>
                <p class="contact-desc">Seleccioná el motivo y dejanos el detalle. Gestionaremos tu pedido rápidamente.</p>

                <!-- Mensajes de feedback -->
                <?php if($mensajeExito): ?>
                    <div class="alert alert-success"><?php echo $mensajeExito; ?></div>
                <?php endif; ?>
                <?php if($mensajeError): ?>
                    <div class="alert alert-danger"><?php echo $mensajeError; ?></div>
                <?php endif; ?>

                <form action="contacto.php" method="POST" class="contact-form">
                    
                    <div class="input-group">
                        <label for="categoria">Especifique razón de contacto</label>
                        <select name="categoria" id="categoria" required>
                            <option value="" disabled selected>-- Seleccione una opción --</option>
                            <option value="Alta">Solicitud: Alta de tarjeta nueva</option>
                            <option value="Baja">Solicitud: Baja de tarjeta existente</option>
                            <option value="Mensaje">Mensaje a los administradores / Otros</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label for="mensaje">Detalle su solicitud</label>
                        <textarea name="mensaje" id="mensaje" rows="5" placeholder="Ingrese mensaje o número de tarjeta afectada aquí..." required></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Enviar Solicitud</button>
                </form>
            </section>

            <!-- Columna Derecha: Mapa de Google (Requisito) -->
            <section class="contact-map-section">
                <h3>Nuestras Oficinas Centrales</h3>
                <div class="map-container">
                    <!-- Mapa de Google Embebido (Buenos Aires, Obelisco como ejemplo) -->
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3284.016713276848!2d-58.38414532328103!3d-34.6037388729543!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x95bccacf7a4d5b29%3A0xc316c0201d4a04b!2sObelisco!5e0!3m2!1ses-419!2sar!4v1700000000000!5m2!1ses-419!2sar" 
                            width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </section>

        </div>
    </main>

    <footer class="footer">
        Portal Oficial de Consultas de Liquidaciones CardTracker.
    </footer>

</body>
</html>