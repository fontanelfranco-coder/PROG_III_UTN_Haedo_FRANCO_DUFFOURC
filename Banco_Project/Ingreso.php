<?php
// ingreso.php
session_start();

$host = 'localhost';
$dbname = 'Mi_banco_db';
$username = 'root';
$password_db = ''; 

$error = false;
$mensajeError = "";

try {
    $conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password_db);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $tipo_doc = $_POST['tipo_doc'];
        $documento = $_POST['documento'];
        $usuario = $_POST['usuario'];
        $password = $_POST['password'];

        // 1. Agregamos 'rol' a la lista de columnas que pedimos a la base de datos
        $query = "SELECT documento, nombre, apellido, rol FROM usuarios 
                  WHERE tipo_doc = :tipo_doc 
                  AND documento = :documento 
                  AND usuario = :usuario 
                  AND password = :password";
                  
        $stmt = $conexion->prepare($query);
        $stmt->execute([
            ':tipo_doc' => $tipo_doc,
            ':documento' => $documento,
            ':usuario' => $usuario,
            ':password' => $password
        ]);

        if ($stmt->rowCount() == 1) {
            // ¡LOGIN EXITOSO! 
            $usuarioLogueado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 2. SEGURIDAD: Regeneramos el ID de la sesión para evitar secuestros de sesión
            session_regenerate_id(true);
            
            // Guardamos los datos en la Sesión
            $_SESSION['documento'] = $usuarioLogueado['documento'];
            $_SESSION['nombre'] = $usuarioLogueado['nombre'];
            $_SESSION['apellido'] = $usuarioLogueado['apellido'];
            $_SESSION['rol'] = $usuarioLogueado['rol']; // Guardamos el rol

            // 3. RUTEO: Discriminamos según el rol
            if ($usuarioLogueado['rol'] === 'Administrador') {
                header("Location: admin.php"); // Al panel de control del jefe
            } else {
                header("Location: Generales.php"); // Al dashboard de generales.
            }
            exit(); 
        } else {
            $error = true;
            $mensajeError = "Datos incorrectos. Revisá tu usuario, contraseña o documento.";
        }
    }
} catch (PDOException $e) {
    $error = true;
    $mensajeError = "Error de sistema, intente más tarde."; // Mensaje más genérico por seguridad
    // En la vida real, el $e->getMessage() se guarda en un log de texto, no se le muestra al usuario.
}
?>

<?php if($error): ?>
<!-- 4. PANTALLA DE ERROR (Sin Tailwind, usando tu styles.css) -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error de Ingreso - CardTracker</title>
    <link rel="stylesheet" href="Banco.css">
</head>
<body class="error-page">
    <div class="error-container">
        <!-- Un simple ícono SVG de advertencia -->
        <svg class="error-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        
        <h2 class="error-title">Acceso Denegado</h2>
        <!-- htmlspecialchars() previene inyección de código si por alguna razón el mensaje incluye caracteres raros -->
        <p class="error-message"><?php echo htmlspecialchars($mensajeError); ?></p>
        
        <a href="ingreso.html" class="btn-submit text-center block-link">Volver a intentar</a>
    </div>
</body>
</html>
<?php endif; ?>