<?php
// logout.php
session_start();
session_unset();    // Limpia todas las variables de sesión
session_destroy();  // Destruye la sesión por completo

// Redirige a la pantalla de ingreso
header("Location: ingreso.html");
exit();
?>