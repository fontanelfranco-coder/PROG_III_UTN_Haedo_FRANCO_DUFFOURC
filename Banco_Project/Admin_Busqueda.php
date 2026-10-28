<?php
// admin_busqueda.php
session_start();

if (!isset($_SESSION['documento']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ingreso.html");
    exit();
}

$host = 'localhost';
$dbname = 'mi_banco_db';
$username = 'root';
$password_db = ''; 

// Lista blanca de tablas permitidas (Medida de seguridad vital para evitar que busquen en tablas del sistema)
$tablas_permitidas = ['usuarios', 'tarjetas', 'liquidaciones', 'consultas'];

$resultados = [];
$columnas = [];
$tabla_seleccionada = "";
$termino_busqueda = "";

try {
    $conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password_db);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['tabla'])) {
        $tabla_seleccionada = $_POST['tabla'];
        $termino_busqueda = trim($_POST['termino']);

        // Verificamos que la tabla esté en nuestra lista blanca
        if (in_array($tabla_seleccionada, $tablas_permitidas)) {
            
            // Armamos la consulta. 
            // NOTA: Como verificamos con in_array, es seguro concatenar la variable $tabla_seleccionada aquí.
            $query = "SELECT * FROM $tabla_seleccionada";
            $stmt = $conexion->prepare($query);
            $stmt->execute();
            $todas_las_filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // FILTRADO DINÁMICO EN PHP (¡El truco maestro!)
            // Si el admin escribió algo en el buscador, filtramos los resultados aquí mismo.
            if (!empty($termino_busqueda)) {
                $termino = strtolower($termino_busqueda);
                
                // array_filter recorre fila por fila
                $resultados = array_filter($todas_las_filas, function($fila) use ($termino) {
                    // Recorremos cada valor de esa fila (nombre, saldo, dni, etc.)
                    foreach ($fila as $valor) {
                        // Si el término de búsqueda está en CUALQUIERA de las columnas, nos quedamos con la fila
                        if (stripos((string)$valor, $termino) !== false) {
                            return true;
                        }
                    }
                    return false;
                });
            } else {
                // Si no buscó nada específico, mostramos toda la tabla
                $resultados = $todas_las_filas;
            }

            // Si hay resultados, extraemos los nombres de las columnas para armar los <th> del HTML
            if (count($resultados) > 0) {
                // array_keys saca los nombres (ej: 'documento', 'nombre', 'banco_emisor')
                $columnas = array_keys(reset($resultados)); 
            }
        }
    }

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscador Universal - CardTracker ADMIN</title>
    <link rel="stylesheet" href="Banco.css">
</head>
<body>

    <!-- BARRA SUPERIOR -->
    <header class="navbar admin-navbar">
        <div class="navbar-brand">
            <h1>CardTracker <span class="badge-admin">ADMIN</span></h1>
        </div>
        <nav class="navbar-menu">
            <a href="admin.php">Perfil</a>
            <a href="admin_consultas.php">Consultas</a>
            <a href="admin_abm.php">ABM</a>
            <a href="admin_busqueda.php" class="active">Búsqueda</a>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </nav>
    </header>

    <main class="dashboard-container">
        
        <h2 class="section-title">Auditoría y Búsqueda Global</h2>

        <!-- FORMULARIO DE BÚSQUEDA -->
        <div class="search-panel">
            <form action="admin_busqueda.php" method="POST" class="search-form">
                
                <div class="input-group">
                    <label>Seleccione la Tabla a consultar</label>
                    <select name="tabla" required>
                        <option value="" disabled <?php if(!$tabla_seleccionada) echo 'selected'; ?>>-- Seleccione una base --</option>
                        <option value="usuarios" <?php if($tabla_seleccionada == 'usuarios') echo 'selected'; ?>>Base de Usuarios</option>
                        <option value="tarjetas" <?php if($tabla_seleccionada == 'tarjetas') echo 'selected'; ?>>Base de Tarjetas</option>
                        <option value="liquidaciones" <?php if($tabla_seleccionada == 'liquidaciones') echo 'selected'; ?>>Base de Liquidaciones</option>
                        <option value="consultas" <?php if($tabla_seleccionada == 'consultas') echo 'selected'; ?>>Base de Consultas</option>
                    </select>
                </div>

                <div class="input-group search-input-group">
                    <label>Buscar elemento puntual (Opcional)</label>
                    <input type="text" name="termino" value="<?php echo htmlspecialchars($termino_busqueda); ?>" placeholder="Ej: Galicia, Carlos, 1001...">
                </div>

                <div class="search-actions">
                    <button type="submit" class="btn-submit">Ejecutar Búsqueda</button>
                    <?php if($tabla_seleccionada): ?>
                        <a href="admin_busqueda.php" class="btn-secondary text-center" style="display:inline-block; text-decoration:none;">Limpiar / Volver</a>
                    <?php endif; ?>
                </div>

            </form>
        </div>

        <!-- RESULTADOS DINÁMICOS -->
        <?php if ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
            <div class="results-panel">
                <h3 class="results-title">
                    Resultados en: <span style="text-transform: uppercase; color: var(--primary-color);"><?php echo $tabla_seleccionada; ?></span>
                    <span class="badge-counter"><?php echo count($resultados); ?> registros</span>
                </h3>

                <?php if (count($resultados) > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <!-- Dibujamos las columnas dinámicamente -->
                                    <?php foreach ($columnas as $col): ?>
                                        <th><?php echo htmlspecialchars(str_replace('_', ' ', $col)); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dibujamos las filas y sus valores dinámicamente -->
                                <?php foreach ($resultados as $fila): ?>
                                    <tr>
                                        <?php foreach ($fila as $valor): ?>
                                            <td><?php echo htmlspecialchars((string)$valor); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mt-1 text-center">
                        No se encontraron registros que coincidan con la búsqueda.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </main>

    <footer class="footer">
        Sistema Interno de Gestión CardTracker. Acceso restringido.
    </footer>
</body>
</html>