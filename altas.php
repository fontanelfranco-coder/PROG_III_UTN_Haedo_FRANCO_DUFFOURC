<?php
// altas.php

// 1. Configuración de la conexión a la base de datos
$host = 'localhost';
$dbname = 'mi_banco_db';
$username = 'root';
$password_db = ''; // Dejar vacío si usas XAMPP/WAMP por defecto

$mensaje = "";
$exito = false;

try {
    // Iniciamos la conexión PDO (equivalente a MySqlConnection en C#)
    $conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password_db);
    // Configuramos PDO para que lance excepciones si hay errores
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Verificamos que los datos vengan por el método POST (al hacer clic en submit)
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        
        // Recolectamos los datos del formulario
        $tipo_doc = $_POST['tipo_doc'];
        $documento = $_POST['documento'];
        $nombre = $_POST['nombre'];
        $apellido = $_POST['apellido'];
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $email = $_POST['email'];
        
        $usuario = $_POST['usuario'];
        $passwordA = $_POST['passwordA'];
        $passwordB = $_POST['passwordB'];

        // 3. Validación básica: Las contraseñas deben coincidir
        if ($passwordA !== $passwordB) {
            $mensaje = "Las contraseñas no coinciden. Por favor, intente nuevamente.";
        } else {
            // 4. Verificamos que el cliente exista, que los datos coincidan y que aún no tenga usuario web (usuario IS NULL)
            $queryValidacion = "SELECT documento FROM usuarios 
                                WHERE documento = :documento 
                                AND tipo_doc = :tipo_doc 
                                AND nombre = :nombre 
                                AND apellido = :apellido 
                                AND fecha_nacimiento = :fecha_nacimiento 
                                AND email = :email 
                                AND usuario IS NULL";
                                
            // Preparamos la consulta (equivalente a MySqlCommand)
            $stmt = $conexion->prepare($queryValidacion);
            
            // Pasamos los parámetros (equivalente a Parameters.AddWithValue)
            $stmt->execute([
                ':documento' => $documento,
                ':tipo_doc' => $tipo_doc,
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':fecha_nacimiento' => $fecha_nacimiento,
                ':email' => $email
            ]);

            // Si rowCount() es mayor a 0, significa que encontramos al cliente y está pendiente de activación
            if ($stmt->rowCount() > 0) {
                
                // 5. Verificamos que el nombre de usuario web elegido no esté en uso por otra persona
                $queryUsuarioUnico = "SELECT documento FROM usuarios WHERE usuario = :usuario";
                $stmtUnico = $conexion->prepare($queryUsuarioUnico);
                $stmtUnico->execute([':usuario' => $usuario]);

                if ($stmtUnico->rowCount() > 0) {
                    $mensaje = "El nombre de usuario '$usuario' ya está en uso. Por favor elija otro.";
                } else {
                    // 6. ¡Todo correcto! Hacemos el UPDATE para guardar el usuario y contraseña
                    // Nota: Respetamos guardar la contraseña en texto plano según el script SQL original.
                    $queryUpdate = "UPDATE usuarios SET usuario = :usuario, password = :password WHERE documento = :documento";
                    $stmtUpdate = $conexion->prepare($queryUpdate);
                    $stmtUpdate->execute([
                        ':usuario' => $usuario,
                        ':password' => $passwordA,
                        ':documento' => $documento
                    ]);

                    $exito = true;
                    $mensaje = "¡Cuenta web activada con éxito! Ya puedes iniciar sesión.";
                }
            } else {
                $mensaje = "Los datos ingresados no coinciden con nuestros registros o la cuenta ya se encuentra activada.";
            }
        }
    }
} catch (PDOException $e) {
    // Si hay un error de base de datos, lo capturamos aquí
    $mensaje = "Error de sistema: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado de Activación</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans min-h-screen flex flex-col justify-between">
    <header class="bg-[#004691] text-white text-center py-4 shadow-md">
        <h1 class="text-xl font-semibold">Mis <span class="font-bold">Tarjetas</span></h1>
    </header>

    <main class="flex-grow flex items-center justify-center p-6">
        <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-8 text-center">
            
            <?php if ($exito): ?>
                <!-- Mensaje de Éxito -->
                <div class="text-green-500 mb-4">
                    <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">¡Activación Exitosa!</h2>
                <p class="text-gray-600 mb-6"><?php echo $mensaje; ?></p>
                <a href="ingreso.html" class="inline-block w-full bg-[#004691] hover:bg-blue-800 text-white font-medium py-3 rounded-full transition duration-200">
                    Ir al inicio de sesión
                </a>
            <?php else: ?>
                <!-- Mensaje de Error -->
                <div class="text-red-500 mb-4">
                    <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Error en la Activación</h2>
                <p class="text-gray-600 mb-6"><?php echo $mensaje; ?></p>
                <a href="registro.html" class="inline-block w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-3 rounded-full transition duration-200">
                    Volver a intentarlo
                </a>
            <?php endif; ?>

        </div>
    </main>

    <footer class="bg-gray-50 text-[10px] text-gray-500 text-center p-4 border-t border-gray-200">
        El Servicio Mis Tarjetas está disponible para todos aquellos socios que posean al menos una tarjeta Progra3card válida.
    </footer>
</body>
</html>