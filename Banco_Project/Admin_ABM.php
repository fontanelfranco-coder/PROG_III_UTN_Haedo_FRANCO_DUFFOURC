<?php
// admin_abm.php
session_start();

if (!isset($_SESSION['documento']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ingreso.html");
    exit();
}

$host = 'localhost';
$dbname = 'mi_banco_db';
$username = 'root';
$password_db = ''; 

// Variables para controlar la interfaz y los mensajes
$modo = 'buscar'; // Modos posibles: 'buscar', 'editar', 'crear'
$mensajeExito = "";
$mensajeError = "";
$tarjetaActual = null;
$numero_buscado = "";

try {
    $conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password_db);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // PROCESAMIENTO DEL FORMULARIO
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion'])) {
        $accion = $_POST['accion'];

        // ACCIÓN 1: BUSCAR
        if ($accion == 'buscar') {
            $numero_buscado = $_POST['numero_tarjeta'];
            
            $query = "SELECT * FROM tarjetas WHERE numero_tarjeta = :num";
            $stmt = $conexion->prepare($query);
            $stmt->execute([':num' => $numero_buscado]);
            
            if ($stmt->rowCount() > 0) {
                $tarjetaActual = $stmt->fetch(PDO::FETCH_ASSOC);
                $modo = 'editar';
            } else {
                $modo = 'crear';
                $mensajeExito = "La tarjeta no existe en el sistema. Puede darla de alta a continuación.";
            }
        }

        // ACCIÓN 2: CREAR (ALTA)
        if ($accion == 'crear') {
            $numero_tarjeta = $_POST['numero_tarjeta'];
            $banco = $_POST['banco_emisor'];
            $saldo = $_POST['saldo'];
            $dni = $_POST['dni_titular'];

            // Control PHP: Verificamos si el usuario/DNI existe antes de insertar
            $checkUser = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE documento = :dni");
            $checkUser->execute([':dni' => $dni]);
            
            if ($checkUser->fetchColumn() == 0) {
                $modo = 'crear';
                $numero_buscado = $numero_tarjeta;
                $mensajeError = "No se puede dar de alta: El DNI '$dni' no pertenece a ningún cliente registrado.";
            } else {
                $queryInsert = "INSERT INTO tarjetas (numero_tarjeta, banco_emisor, estado, saldo, dni_titular) 
                                VALUES (:num, :banco, 'Activa', :saldo, :dni)";
                $stmtInsert = $conexion->prepare($queryInsert);
                $stmtInsert->execute([':num' => $numero_tarjeta, ':banco' => $banco, ':saldo' => $saldo, ':dni' => $dni]);
                
                $mensajeExito = "¡Tarjeta dada de alta con éxito!";
                $modo = 'buscar'; // Volvemos al inicio
            }
        }

        // ACCIÓN 3: MODIFICAR
        if ($accion == 'modificar') {
            $numero_tarjeta = $_POST['numero_tarjeta'];
            $banco = $_POST['banco_emisor'];
            $estado = $_POST['estado'];
            $saldo = $_POST['saldo'];
            $dni = $_POST['dni_titular'];

            // Control PHP: Verificamos si el DNI existe por si el admin se equivocó al editarlo
            $checkUser = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE documento = :dni");
            $checkUser->execute([':dni' => $dni]);
            
            if ($checkUser->fetchColumn() == 0) {
                $modo = 'editar'; // Nos quedamos en la pantalla de edición
                // Recargamos los datos que intentó mandar para no borrárselos
                $tarjetaActual = ['numero_tarjeta' => $numero_tarjeta, 'banco_emisor' => $banco, 'estado' => $estado, 'saldo' => $saldo, 'dni_titular' => $dni];
                $mensajeError = "No se puede modificar: El DNI '$dni' no pertenece a ningún cliente registrado.";
            } else {
                $queryUpdate = "UPDATE tarjetas SET banco_emisor = :banco, estado = :estado, saldo = :saldo, dni_titular = :dni 
                                WHERE numero_tarjeta = :num";
                $stmtUpdate = $conexion->prepare($queryUpdate);
                $stmtUpdate->execute([':banco' => $banco, ':estado' => $estado, ':saldo' => $saldo, ':dni' => $dni, ':num' => $numero_tarjeta]);
                
                $mensajeExito = "¡Modificaciones guardadas correctamente!";
                $modo = 'buscar';
            }
        }

        // ACCIÓN 4: ELIMINAR (BAJA)
        if ($accion == 'eliminar') {
            $numero_tarjeta = $_POST['numero_tarjeta'];
            $queryDelete = "DELETE FROM tarjetas WHERE numero_tarjeta = :num";
            $stmtDelete = $conexion->prepare($queryDelete);
            $stmtDelete->execute([':num' => $numero_tarjeta]);
            
            $mensajeExito = "¡La tarjeta y sus liquidaciones fueron eliminadas del sistema!";
            $modo = 'buscar';
        }
        
        // ACCIÓN 5: VOLVER
        if ($accion == 'volver') {
            $modo = 'buscar';
        }
    }

} catch (PDOException $e) {
    $mensajeError = "Error de base de datos: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ABM de Tarjetas - CardTracker ADMIN</title>
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
            <a href="admin_abm.php" class="active">ABM</a>
            <a href="admin_busqueda.php">Búsqueda</a>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </nav>
    </header>

    <main class="dashboard-container admin-centered-layout" style="padding-top: 2rem;">
        
        <div class="admin-wrapper abm-wrapper">
            <h2 class="section-title text-center">Gestión Operativa de Tarjetas</h2>

            <!-- MENSAJES DE SISTEMA -->
            <?php if($mensajeExito && $modo == 'buscar'): ?>
                <div class="alert alert-success text-center"><?php echo $mensajeExito; ?></div>
            <?php endif; ?>
            <?php if($mensajeError): ?>
                <div class="alert alert-danger text-center"><?php echo $mensajeError; ?></div>
            <?php endif; ?>
            <?php if($mensajeExito && $modo == 'crear'): ?>
                <!-- Cartel especial azul (Info) cuando busca una tarjeta y no la encuentra -->
                <div class="alert alert-info text-center"><?php echo $mensajeExito; ?></div>
            <?php endif; ?>

            <!-- ==========================================
                 PANTALLA 1: BUSCADOR
                 ========================================== -->
            <?php if($modo == 'buscar'): ?>
                <div class="abm-box">
                    <h3>Buscar Tarjeta</h3>
                    <p class="text-sm text-gray mb-4">Ingrese los 16 dígitos de la tarjeta a gestionar.</p>
                    
                    <form action="admin_abm.php" method="POST" class="abm-form-inline">
                        <input type="hidden" name="accion" value="buscar">
                        <input type="text" name="numero_tarjeta" placeholder="Ej: 4512765432109876" required minlength="16" maxlength="16" class="abm-input">
                        <button type="submit" class="btn-submit">Buscar</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- ==========================================
                 PANTALLA 2: EDITAR TARJETA (MODIFICACIÓN/BAJA)
                 ========================================== -->
            <?php if($modo == 'editar'): ?>
                <div class="abm-box active-box">
                    <h3>Modificar o Eliminar Tarjeta</h3>
                    <form action="admin_abm.php" method="POST">
                        <div class="abm-grid">
                            <div class="input-group">
                                <label>Número de Tarjeta</label>
                                <input type="text" name="numero_tarjeta" value="<?php echo htmlspecialchars($tarjetaActual['numero_tarjeta']); ?>" readonly class="input-readonly">
                            </div>
                            
                            <div class="input-group">
                                <label>DNI del Titular</label>
                                <input type="text" name="dni_titular" value="<?php echo htmlspecialchars($tarjetaActual['dni_titular']); ?>" required>
                            </div>

                            <div class="input-group">
                                <label>Banco Emisor</label>
                                <select name="banco_emisor">
                                    <?php 
                                    $bancos = ['Banco Nación', 'Banco Provincia', 'Banco Galicia', 'Banco Santander', 'Banco BBVA', 'Banco Macro'];
                                    foreach($bancos as $b) {
                                        $selected = ($b == $tarjetaActual['banco_emisor']) ? "selected" : "";
                                        echo "<option value='$b' $selected>$b</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="input-group">
                                <label>Estado</label>
                                <select name="estado">
                                    <option value="Activa" <?php if($tarjetaActual['estado'] == 'Activa') echo 'selected'; ?>>Activa</option>
                                    <option value="Bloqueada" <?php if($tarjetaActual['estado'] == 'Bloqueada') echo 'selected'; ?>>Bloqueada</option>
                                </select>
                            </div>

                            <div class="input-group">
                                <label>Saldo Actual ($)</label>
                                <input type="number" step="0.01" name="saldo" value="<?php echo htmlspecialchars($tarjetaActual['saldo']); ?>" required>
                            </div>
                        </div>

                        <div class="abm-actions">
                            <button type="submit" name="accion" value="volver" class="btn-secondary">Volver Atrás</button>
                            <!-- Agrupamos los botones de peligro y guardar a la derecha -->
                            <div class="right-actions">
                                <button type="submit" name="accion" value="eliminar" class="btn-danger" onclick="return confirm('ATENCIÓN: Se eliminará la tarjeta y todas sus liquidaciones. ¿Continuar?');">Eliminar (Baja)</button>
                                <button type="submit" name="accion" value="modificar" class="btn-submit">Guardar Cambios</button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- ==========================================
                 PANTALLA 3: CREAR TARJETA (ALTA)
                 ========================================== -->
            <?php if($modo == 'crear'): ?>
                <div class="abm-box new-box">
                    <h3>Alta de Nueva Tarjeta</h3>
                    <form action="admin_abm.php" method="POST">
                        <div class="abm-grid">
                            <div class="input-group">
                                <label>Número de Tarjeta</label>
                                <input type="text" name="numero_tarjeta" value="<?php echo htmlspecialchars($numero_buscado); ?>" required minlength="16" maxlength="16">
                            </div>
                            
                            <div class="input-group">
                                <label>DNI del Titular (Debe existir)</label>
                                <input type="text" name="dni_titular" placeholder="Ej: 20123456" required>
                            </div>

                            <div class="input-group">
                                <label>Banco Emisor</label>
                                <select name="banco_emisor">
                                    <option value="Banco Nación">Banco Nación</option>
                                    <option value="Banco Provincia">Banco Provincia</option>
                                    <option value="Banco Galicia">Banco Galicia</option>
                                    <option value="Banco Santander">Banco Santander</option>
                                    <option value="Banco BBVA">Banco BBVA</option>
                                    <option value="Banco Macro">Banco Macro</option>
                                </select>
                            </div>

                            <div class="input-group">
                                <label>Saldo Inicial ($)</label>
                                <input type="number" step="0.01" name="saldo" value="0.00" required>
                            </div>
                        </div>

                        <div class="abm-actions">
                            <button type="submit" name="accion" value="volver" class="btn-secondary">Volver Atrás</button>
                            <button type="submit" name="accion" value="crear" class="btn-submit">Confirmar Alta</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <footer class="footer">
        Sistema Interno de Gestión CardTracker. Acceso restringido.
    </footer>
</body>
</html>