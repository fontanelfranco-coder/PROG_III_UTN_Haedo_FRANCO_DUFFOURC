<?php
/**
 * includes/datos.php
 * Listas permitidas y funciones de negocio compartidas (avisos, formatos).
 */

// Listas cerradas: todo lo que llega de un formulario se valida contra ellas
const BANCOS = [
    'Banco Nación', 'Banco Provincia', 'Banco Ciudad', 'Banco Galicia',
    'Banco Santander', 'Banco BBVA', 'Banco Macro', 'Banco Patagonia',
    'ICBC', 'Brubank', 'Naranja X', 'Otro',
];
const REDES = ['Visa', 'Mastercard', 'American Express', 'Cabal', 'Naranja'];
const TIPOS_DOC = ['DNI', 'PASAPORTE'];

// Días de anticipación para avisar un vencimiento
const DIAS_AVISO = 7;

const MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
               'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

// --- Formatos ----------------------------------------------------------------
function pesos($monto): string
{
    return '$ ' . number_format((float)$monto, 2, ',', '.');
}

function fecha_corta(?string $fecha): string
{
    return $fecha ? date('d/m/Y', strtotime($fecha)) : '';
}

/** '2026-05' -> 'Mayo 2026' */
function periodo_legible(string $periodo, bool $corto = false): string
{
    [$anio, $mes] = array_map('intval', explode('-', $periodo));
    $nombre = MESES[$mes] ?? $periodo;
    return $corto ? mb_substr($nombre, 0, 3) . ' ' . substr((string)$anio, 2) : "$nombre $anio";
}

/** Clase CSS según la red, para el color de la tarjeta */
function clase_red(string $red): string
{
    return [
        'Visa' => 'visa', 'Mastercard' => 'mastercard', 'American Express' => 'amex',
        'Cabal' => 'cabal', 'Naranja' => 'naranja',
    ][$red] ?? 'visa';
}

/** Nombre corto para mostrar una tarjeta en listas */
function nombre_tarjeta(array $t): string
{
    $base = $t['alias'] !== null && $t['alias'] !== '' ? $t['alias'] : $t['banco_emisor'];
    return $base . ' •••• ' . $t['ultimos4'];
}

// --- Estado de un resumen ----------------------------------------------------
function dias_hasta(string $fecha): int
{
    $hoy = new DateTimeImmutable('today');
    $objetivo = new DateTimeImmutable($fecha);
    return (int)$hoy->diff($objetivo)->format('%r%a');
}

/** Devuelve: pagado | vencido | proximo | pendiente */
function estado_resumen(array $r): string
{
    if ($r['estado_pago'] === 'Pagado') {
        return 'pagado';
    }
    $dias = dias_hasta($r['fecha_vencimiento']);
    if ($dias < 0) {
        return 'vencido';
    }
    return $dias <= DIAS_AVISO ? 'proximo' : 'pendiente';
}

function texto_estado(string $estado): string
{
    return ['pagado' => 'Pagado', 'vencido' => 'Vencido',
            'proximo' => 'Vence pronto', 'pendiente' => 'Pendiente'][$estado];
}

function texto_vencimiento(string $fecha): string
{
    $dias = dias_hasta($fecha);
    if ($dias < -1)  return 'Venció hace ' . abs($dias) . ' días';
    if ($dias === -1) return 'Venció ayer';
    if ($dias === 0)  return 'Vence hoy';
    if ($dias === 1)  return 'Vence mañana';
    return "Vence en $dias días";
}

// --- Avisos (recordatorios y notificaciones) --------------------------------
/**
 * Resúmenes impagos del usuario que ya vencieron o vencen dentro de DIAS_AVISO días.
 * Los vencidos aparecen primero.
 */
function obtener_avisos(string $documento): array
{
    $sql = "SELECT l.*, t.banco_emisor, t.red, t.ultimos4, t.alias
            FROM liquidaciones l
            INNER JOIN tarjetas t ON t.id_tarjeta = l.id_tarjeta
            WHERE t.dni_titular = :doc
              AND l.estado_pago = 'Pendiente'
              AND l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL " . DIAS_AVISO . " DAY)
            ORDER BY l.fecha_vencimiento ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute([':doc' => $documento]);
    return $stmt->fetchAll();
}
