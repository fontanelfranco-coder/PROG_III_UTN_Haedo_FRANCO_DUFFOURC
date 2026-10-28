<?php
// generales.php
session_start();

// CONTROL DE SEGURIDAD: Debe estar logueado y ser usuario 'General'
if (!isset($_SESSION['documento']) || $_SESSION['rol'] !== 'General') {
    header("Location: ingreso.html");
    exit();
}

$documento_sesion = $_SESSION['documento'];

$host = 'localhost';
$dbname = 'mi_banco_db';
$username = 'root';
$password_db = ''; 

try {
    $conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password_db);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Obtener datos personales del cliente
    $queryUsuario = "SELECT * FROM usuarios WHERE documento = :doc";
    $stmtUsuario = $conexion->prepare($queryUsuario);
    $stmtUsuario->execute([':doc' => $documento_sesion]);
    $cliente = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

    // 2. Obtener TODAS las tarjetas activas de este cliente
    $queryTarjetas = "SELECT * FROM tarjetas WHERE dni_titular = :doc AND estado = 'Activa'";
    $stmtTarjetas = $conexion->prepare($queryTarjetas);
    $stmtTarjetas->execute([':doc' => $documento_sesion]);
    $tarjetas = $stmtTarjetas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Panel - CardTracker</title>
    <link rel="stylesheet" href="Banco.css">
</head>
<body>

    <!-- BARRA SUPERIOR DE NAVEGACIÓN -->
    <header class="navbar">
        <div class="navbar-brand">
            <h1>CardTracker</h1>
        </div>
        <nav class="navbar-menu">
            <a href="generales.php" class="active">Mi Perfil</a>
            <a href="liquidaciones.php">Liquidaciones</a>
            <a href="contacto.php">Contacto</a>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </nav>
    </header>

    <main class="dashboard-container">
        
        <!-- SECCIÓN PERFIL DEL CLIENTE -->
        <section class="profile-section">
            <h2 class="section-title">Información del Cliente</h2>
            <div class="profile-card">
                <div class="profile-info">
                    <p><strong>Nombre completo:</strong> <?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?></p>
                    <p><strong>Documento:</strong> <?php echo htmlspecialchars($cliente['tipo_doc'] . ' ' . $cliente['documento']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($cliente['email']); ?></p>
                    <p><strong>Fecha de nacimiento:</strong> <?php echo date("d/m/Y", strtotime($cliente['fecha_nacimiento'])); ?></p>
                </div>
            </div>
        </section>

        <!-- SECCIÓN TARJETAS ACTIVAS -->
        <section class="cards-section">
            <h2 class="section-title">Mis Tarjetas Activas</h2>
            
            <?php if(count($tarjetas) > 0): ?>
                <div class="cards-grid">
                    <?php foreach($tarjetas as $tarjeta): ?>
                        <div class="credit-card">
                            <div class="card-header">
                                <span class="bank-name"><?php echo htmlspecialchars($tarjeta['banco_emisor']); ?></span>
                                <!-- Simulamos el logo de un chip -->
                                <div class="chip"></div> 
                            </div>
                            <div class="card-body">
                                <!-- Mostramos solo los últimos 4 números por seguridad -->
                                <p class="card-number">
                                    **** **** **** <?php echo substr($tarjeta['numero_tarjeta'], -4); ?>
                                </p>
                            </div>
                            <div class="card-footer">
                                <div>
                                    <span class="card-label">Titular</span>
                                    <p class="card-holder"><?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?></p>
                                </div>
                                <div class="text-right">
                                    <span class="card-label">Estado</span>
                                    <p class="card-status"><?php echo htmlspecialchars($tarjeta['estado']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>Actualmente no posees tarjetas activas en el sistema.</p>
                    <a href="contacto.php" class="btn-submit inline-block mt-1">Solicitar una tarjeta</a>
                </div>
            <?php endif; ?>

        </section>

    </main>

    <footer class="footer">
        Portal Oficial de Consultas de Liquidaciones CardTracker.
    </footer>

</body>
</html>