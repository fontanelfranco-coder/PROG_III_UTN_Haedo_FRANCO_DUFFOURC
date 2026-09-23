<?php
// ingreso.php
session_start(); // ¡Fundamental! Arrancamos el motor de sesiones de PHP

$host = 'localhost';
$dbname = 'mi_banco_db';
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

        // Buscamos si existe ese combo exacto en la base de datos
        $query = "SELECT documento, nombre, apellido FROM usuarios 
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
            
            // Le damos la "pulserita VIP" guardando su documento en la Sesión de PHP
            $_SESSION['documento'] = $usuarioLogueado['documento'];
            $_SESSION['nombre'] = $usuarioLogueado['nombre'];
            $_SESSION['apellido'] = $usuarioLogueado['apellido'];

            // Lo teletransportamos al resumen
            header("Location: resumen.php");
            exit(); // Detenemos la ejecución de este script
        } else {
            $error = true;
            $mensajeError = "Datos incorrectos. Revisá tu usuario, contraseña o documento.";
        }
    }
} catch (PDOException $e) {
    $error = true;
    $mensajeError = "Error de conexión: " . $e->getMessage();
}
?>

<?php if($error): ?>
<!-- Si llegamos a esta parte del código, significa que hubo un error y no lo teletransportamos -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Error de Ingreso</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md max-w-sm w-full text-center">
        <h2 class="text-red-600 text-xl font-bold mb-4">Acceso Denegado</h2>
        <p class="text-gray-700 mb-6"><?php echo $mensajeError; ?></p>
        <a href="ingreso.html" class="block w-full bg-[#004691] text-white py-2 rounded-full hover:bg-blue-800">Volver a intentar</a>
    </div>
</body>
</html>
<?php endif; ?>