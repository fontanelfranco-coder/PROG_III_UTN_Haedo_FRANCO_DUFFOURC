<?php
// resumen.php
session_start();

// 1. CONTROL DE SEGURIDAD: Si no tiene la pulserita, lo echamos al login
if (!isset($_SESSION['documento'])) {
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

    // 2. EL SÚPER JOIN: 
    // Unimos Usuarios -> Tarjetas -> Liquidaciones. 
    // Usamos LEFT JOIN para que, si el cliente tiene tarjeta pero aún no tiene liquidaciones, igual nos traiga sus datos.
    // Ordenamos por período DESC para que la primera fila sea la liquidación más reciente.
    $query = "SELECT u.nombre, u.apellido, t.numero_tarjeta, t.banco_emisor, 
                     l.periodo, l.fecha_vencimiento, l.total_a_pagar, l.pago_minimo
              FROM usuarios u
              INNER JOIN tarjetas t ON u.documento = t.dni_titular
              LEFT JOIN liquidaciones l ON t.num_cuenta = l.num_cuenta
              WHERE u.documento = :doc
              ORDER BY l.periodo DESC";

    $stmt = $conexion->prepare($query);
    $stmt->execute([':doc' => $documento_sesion]);
    
    // Obtenemos todos los resultados en un array (una lista)
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Separamos la información general (que se repite en todas las filas)
    $cliente = $resultados[0]; 
    
    // Separamos la última liquidación (la primera de la lista)
    $ultima_liq = $resultados[0]['periodo'] != null ? $resultados[0] : null;

    // Guardamos el resto en otra lista para el historial
    $historial = array_slice($resultados, 1);

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Resumen - Mis Tarjetas</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans min-h-screen">

    <header class="bg-[#004691] text-white p-4 shadow-md flex justify-between items-center px-8">
        <h1 class="text-xl font-semibold">Mis <span class="font-bold">Tarjetas</span></h1>
        <!-- Botón para cerrar sesión (destruye la pulserita) -->
        <a href="ingreso.html" onclick="<?php session_destroy(); ?>" class="text-sm bg-blue-800 px-4 py-2 rounded hover:bg-blue-900 transition">Cerrar Sesión</a>
    </header>

    <main class="max-w-4xl mx-auto mt-8 p-4">
        
        <!-- SALUDO E INFORMACIÓN DE TARJETA -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-800">Hola, <?php echo $cliente['nombre'] . ' ' . $cliente['apellido']; ?>!</h2>
            <p class="text-gray-600 mt-2">
                Tarjeta: <span class="font-semibold text-[#004691]"><?php echo $cliente['banco_emisor']; ?></span> 
                (Terminada en <?php echo substr($cliente['numero_tarjeta'], -4); ?>)
            </p>
        </div>

        <?php if($ultima_liq): ?>
            <!-- LIQUIDACIÓN MÁS RECIENTE (RESALTADA) -->
            <div class="bg-white rounded-xl shadow-lg border-t-4 border-[#004691] p-6 mb-8">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">Última Liquidación: <?php echo $ultima_liq['periodo']; ?></h3>
                
                <div class="flex flex-col md:flex-row justify-between items-center md:items-end">
                    <div>
                        <p class="text-sm text-gray-500">Total a pagar</p>
                        <p class="text-4xl font-bold text-gray-800">$<?php echo number_format($ultima_liq['total_a_pagar'], 2, ',', '.'); ?></p>
                    </div>
                    <div class="mt-4 md:mt-0 text-right">
                        <p class="text-sm text-gray-500">Vencimiento</p>
                        <p class="text-xl font-semibold text-red-600"><?php echo date("d/m/Y", strtotime($ultima_liq['fecha_vencimiento'])); ?></p>
                    </div>
                </div>
                
                <hr class="my-4 border-gray-100">
                <p class="text-sm text-gray-600">Pago Mínimo: <span class="font-bold">$<?php echo number_format($ultima_liq['pago_minimo'], 2, ',', '.'); ?></span></p>
            </div>

            <!-- HISTORIAL INTERACTIVO (ACORDEÓN) -->
            <?php if(count($historial) > 0): ?>
                <h3 class="text-lg font-bold text-gray-700 mb-4">Consultar Liquidaciones Anteriores</h3>
                <div class="space-y-3">
                    <?php foreach($historial as $liq): ?>
                        <!-- La etiqueta <details> crea un menú interactivo nativo -->
                        <details class="bg-white rounded-lg shadow-sm border border-gray-200 group cursor-pointer">
                            <summary class="p-4 font-semibold text-[#004691] flex justify-between outline-none">
                                Período <?php echo $liq['periodo']; ?>
                                <span class="text-gray-400 group-open:rotate-180 transition-transform">▼</span>
                            </summary>
                            <div class="p-4 border-t border-gray-100 bg-gray-50 text-gray-700 grid grid-cols-3 gap-4 text-sm">
                                <div><span class="block text-xs text-gray-500">Total</span>$<?php echo number_format($liq['total_a_pagar'], 2, ',', '.'); ?></div>
                                <div><span class="block text-xs text-gray-500">Mínimo</span>$<?php echo number_format($liq['pago_minimo'], 2, ',', '.'); ?></div>
                                <div><span class="block text-xs text-gray-500">Venció el</span><?php echo date("d/m/Y", strtotime($liq['fecha_vencimiento'])); ?></div>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="bg-blue-50 text-[#004691] p-6 rounded-lg text-center border border-blue-100">
                ¡Genial! Aún no tenés liquidaciones emitidas para esta tarjeta.
            </div>
        <?php endif; ?>

    </main>
</body>
</html>