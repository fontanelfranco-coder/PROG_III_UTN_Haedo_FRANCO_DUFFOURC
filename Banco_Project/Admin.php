<?php
// admin.php
session_start();

// CONTROL DE SEGURIDAD ESTRICTO: Solo 'Administrador'
if (!isset($_SESSION['documento']) || $_SESSION['rol'] !== 'Administrador') {
    // Si un usuario General intenta entrar acá tipeando la URL, lo pateamos a su panel
    if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'General') {
        header("Location: generales.php");
    } else {
        header("Location: ingreso.html");
    }
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

    // Obtener datos del Administrador logueado
    $queryAdmin = "SELECT * FROM usuarios WHERE documento = :doc";
    $stmtAdmin = $conexion->prepare($queryAdmin);
    $stmtAdmin->execute([':doc' => $documento_sesion]);
    $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - CardTracker</title>
    <link rel="stylesheet" href="Banco.css">
</head>
<body>

    <!-- BARRA SUPERIOR MODO ADMIN (Más oscura para diferenciar) -->
    <header class="navbar admin-navbar">
        <div class="navbar-brand">
            <h1>CardTracker <span class="badge-admin">ADMIN</span></h1>
        </div>
        <nav class="navbar-menu">
            <a href="admin.php" class="active">Inicio</a>
            <a href="admin_consultas.php">Consultas</a>
            <a href="admin_abm.php">ABM</a>
            <a href="admin_busqueda.php">Búsqueda</a>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </nav>
    </header>

    <main class="dashboard-container admin-centered-layout">
        
        <div class="admin-wrapper">
            <!-- SALUDO ESPECIAL (Ahora centrado) -->
            <div class="admin-greeting-clean">
                <h2>¡Bienvenido al Panel de Control, <?php echo htmlspecialchars($admin['nombre']); ?>!</h2>
                <p>Desde aquí podés gestionar usuarios, tarjetas, liquidaciones y responder a las consultas de los clientes.</p>
            </div>

            <!-- SECCIÓN PERFIL DEL ADMIN -->
            <section class="profile-section">
                <h2 class="section-title text-center">Información de tu credencial</h2>
                
                <div class="profile-card admin-card large-card">
                    <div class="profile-info large-info">
                        <p><strong>Operador:</strong> <?php echo htmlspecialchars($admin['nombre'] . ' ' . $admin['apellido']); ?></p>
                        <p><strong>Documento:</strong> <?php echo htmlspecialchars($admin['tipo_doc'] . ' ' . $admin['documento']); ?></p>
                        <p><strong>Email oficial:</strong> <?php echo htmlspecialchars($admin['email']); ?></p>
                        <p><strong>Nivel de Acceso:</strong> <span class="status-admin"><?php echo htmlspecialchars($admin['rol']); ?></span></p>
                    </div>
                </div>
            </section>
        </div>

    </main>

    <footer class="footer">
        Sistema Interno de Gestión CardTracker. Acceso restringido.
    </footer>

</body>
</html>