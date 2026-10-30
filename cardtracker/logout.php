<?php
// logout.php — Cierra la sesión por completo
require 'includes/config.php';

// Borramos todos los datos y descartamos el ID de sesión anterior
$_SESSION = [];
session_regenerate_id(true);

flash('exito', 'Cerraste sesión. ¡Hasta pronto!');
redirigir('index.php#ingresar');
