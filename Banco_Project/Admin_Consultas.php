<?php
// admin_consultas.php
session_start();

// CONTROL DE SEGURIDAD ESTRICTO: Solo 'Administrador'
if (!isset($_SESSION['documento']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ingreso.html");
    exit();
}

$host = 'localhost';
$dbname = 'mi_banco_db';
$username = 'root';
$password_db = ''; 

try {
    $conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password_db);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. PROCESAR ACCIÓN: Si el admin hizo clic en "Marcar como Resuelta"
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_consulta'])) {
        $id_a_resolver = $_POST['id_consulta'];
        
        $queryUpdate = "UPDATE consultas SET resuelto = 'Si' WHERE id_consulta = :id";
        $stmtUpdate = $conexion->prepare($queryUpdate);
        $stmtUpdate->execute([':id' => $id_a_resolver]);
        
        // Redirigimos a la misma página para limpiar el POST y evitar que F5 reenvíe el formulario
        header("Location: admin_consultas.php");
        exit();
    }

    // 2. OBTENER DATOS: Traemos solo las consultas pendientes (resuelto = 'No')
    // Ordenamos por fecha ascendente (las más viejas primero, regla básica de atención al cliente)
    $queryConsultas = "SELECT * FROM consultas WHERE resuelto = 'No' ORDER BY fecha_envio ASC";
    $stmtConsultas = $conexion->prepare($queryConsultas);
    $stmtConsultas->execute();
    $consultas = $stmtConsultas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Consultas - CardTracker ADMIN</title>
    <link rel="stylesheet" href="Banco.css">
</head>
<body>

    <!-- BARRA SUPERIOR MODO ADMIN -->
    <header class="navbar admin-navbar">
        <div class="navbar-brand">
            <h1>CardTracker <span class="badge-admin">ADMIN</span></h1>
        </div>
        <nav class="navbar-menu">
            <!-- Mantenemos la opción Consultas visible y activa para saber dónde estamos -->
            <a href="admin.php">Perfil</a>
            <a href="admin_consultas.php" class="active">Consultas</a>
            <a href="admin_abm.php">ABM</a>
            <a href="admin_busqueda.php">Búsqueda</a>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </nav>
    </header>

    <main class="dashboard-container">
        
        <div class="flex-header">
            <h2 class="section-title">Bandeja de Consultas Pendientes</h2>
            <span class="badge-counter"><?php echo count($consultas); ?> pendientes</span>
        </div>
        
        <?php if(count($consultas) > 0): ?>
            <div class="consultas-list">
                <?php foreach($consultas as $consulta): ?>
                    <div class="consulta-card">
                        
                        <!-- Encabezado de la caja: Categoría y Fecha -->
                        <div class="consulta-header">
                            <!-- Agregamos una clase dinámica según la categoría para darle distintos colores -->
                            <span class="badge-cat cat-<?php echo strtolower($consulta['categoria']); ?>">
                                <?php echo htmlspecialchars($consulta['categoria']); ?>
                            </span>
                            <span class="consulta-date">
                                Enviado el <?php echo date("d/m/Y - H:i", strtotime($consulta['fecha_envio'])); ?> hs.
                            </span>
                        </div>

                        <!-- Cuerpo: Datos del cliente y Mensaje -->
                        <div class="consulta-body">
                            <h4 class="consulta-author">
                                <?php echo htmlspecialchars($consulta['nombre']); ?> 
                                <span class="consulta-email">(<?php echo htmlspecialchars($consulta['email']); ?>)</span>
                            </h4>
                            <!-- nl2br respeta los saltos de línea que el cliente haya puesto en el textarea -->
                            <p class="consulta-message">
                                "<?php echo nl2br(htmlspecialchars($consulta['mensaje'])); ?>"
                            </p>
                        </div>

                        <!-- Pie: Botón de acción -->
                        <div class="consulta-footer">
                            <form action="admin_consultas.php" method="POST">
                                <input type="hidden" name="id_consulta" value="<?php echo $consulta['id_consulta']; ?>">
                                <button type="submit" class="btn-resolve">
                                    <svg class="icon-check" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Marcar como Resuelta
                                </button>
                            </form>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>¡Excelente trabajo! No hay consultas pendientes de revisión.</p>
            </div>
        <?php endif; ?>

    </main>

    <footer class="footer">
        Sistema Interno de Gestión CardTracker. Acceso restringido.
    </footer>

</body>
</html>