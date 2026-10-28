<?php
// liquidaciones.php
session_start();

// CONTROL DE SEGURIDAD: Solo usuarios 'General'
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

    // CONSULTA: Unimos tarjetas con liquidaciones filtrando por el DNI del titular
    $query = "SELECT t.banco_emisor, t.numero_tarjeta, 
                     l.periodo, l.fecha_vencimiento, l.total_a_pagar, l.pago_minimo
              FROM tarjetas t
              INNER JOIN liquidaciones l ON t.num_cuenta = l.num_cuenta
              WHERE t.dni_titular = :doc
              ORDER BY l.periodo DESC, l.fecha_vencimiento DESC";

    $stmt = $conexion->prepare($query);
    $stmt->execute([':doc' => $documento_sesion]);
    
    // Obtenemos todas las liquidaciones asociadas a las tarjetas de este cliente
    $liquidaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Liquidaciones - CardTracker</title>
    <link rel="stylesheet" href="Banco.css">
</head>
<body>

    <!-- BARRA SUPERIOR DE NAVEGACIÓN -->
    <header class="navbar">
        <div class="navbar-brand">
            <h1>CardTracker</h1>
        </div>
        <nav class="navbar-menu">
            <a href="generales.php">Mi Perfil</a>
            <a href="liquidaciones.php" class="active">Liquidaciones</a>
            <a href="contacto.php">Contacto</a>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </nav>
    </header>

    <main class="dashboard-container">
        
        <section class="liquidaciones-section">
            <h2 class="section-title">Historial de Liquidaciones</h2>
            
            <?php if(count($liquidaciones) > 0): ?>
                <div class="invoice-list">
                    <?php foreach($liquidaciones as $liq): ?>
                        <div class="invoice-card">
                            
                            <!-- Izquierda: Datos de la tarjeta y período -->
                            <div class="invoice-info">
                                <div class="invoice-period">
                                    Período: <span><?php echo htmlspecialchars($liq['periodo']); ?></span>
                                </div>
                                <div class="invoice-bank">
                                    <?php echo htmlspecialchars($liq['banco_emisor']); ?>
                                    <span class="invoice-card-num">(Terminada en <?php echo substr($liq['numero_tarjeta'], -4); ?>)</span>
                                </div>
                            </div>

                            <!-- Derecha: Importes y Vencimiento -->
                            <div class="invoice-amounts">
                                <div class="amount-group text-right">
                                    <span class="amount-label">Total a Pagar</span>
                                    <span class="amount-total">$<?php echo number_format($liq['total_a_pagar'], 2, ',', '.'); ?></span>
                                </div>
                                
                                <div class="amount-group text-right">
                                    <span class="amount-label">Pago Mínimo</span>
                                    <span class="amount-min">$<?php echo number_format($liq['pago_minimo'], 2, ',', '.'); ?></span>
                                </div>

                                <div class="invoice-due text-right">
                                    Vencimiento: <strong><?php echo date("d/m/Y", strtotime($liq['fecha_vencimiento'])); ?></strong>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No se encontraron liquidaciones emitidas para tus tarjetas.</p>
                </div>
            <?php endif; ?>
        </section>

    </main>

    <footer class="footer">
        Portal Oficial de Consultas de Liquidaciones CardTracker.
    </footer>

</body>
</html>