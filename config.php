<?php
// =====================================================================
// ATHLYTICS - Conexión a base de datos (PDO) + catálogos por deporte
// Ajusta $host / $user / $pass solo si tu XAMPP tiene contraseña de root
// =====================================================================
$host = '127.0.0.1';
$db   = 'athlytics_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}

session_start();

// ---------------------------------------------------------------------
// CATÁLOGO POR DEPORTE
// Todo lo que cambia entre fútbol y básquet vive aquí, en un solo lugar.
// deporte_id 1 = Fútbol, 2 = Básquetbol (coincide con schema.sql / deportes)
// ---------------------------------------------------------------------
$DEPORTES = [
    1 => [
        'nombre' => 'Fútbol',
        'slug'   => 'futbol',
        'icono'  => 'FUT',
        'apodo_label'       => 'Apodo / nombre de camiseta',
        'apodo_placeholder' => 'Ej. Kun, Chicharito, Pulga',
        'posiciones' => [
            'POR' => 'Portero',
            'DEF' => 'Defensa',
            'MED' => 'Mediocampista',
            'DEL' => 'Delantero',
        ],
        'habilidades' => [
            'Tecnica'     => 'Técnica',
            'Resistencia' => 'Resistencia',
            'Velocidad'   => 'Velocidad',
            'Pase'        => 'Pase',
        ],
        'stat_labels' => [
            'goles_puntos'      => 'Goles',
            'asistencias'       => 'Asistencias',
            'pases_completados' => 'Pases completados',
            'tiros'             => 'Tiros a gol',
            'robos_rebotes'     => 'Recuperaciones',
            'faltas'            => 'Faltas',
        ],
        'formaciones' => [
            '4-3-3' => ['POR','DEF','DEF','DEF','DEF','MED','MED','MED','DEL','DEL','DEL'],
            '4-4-2' => ['POR','DEF','DEF','DEF','DEF','MED','MED','MED','MED','DEL','DEL'],
        ],
        // coordenadas relativas (%) de cada slot dentro de la cancha, por esquema
        'slots' => [
            '4-3-3' => [
                ['pos'=>'POR','x'=>50,'y'=>92],
                ['pos'=>'DEF','x'=>15,'y'=>72],['pos'=>'DEF','x'=>38,'y'=>76],['pos'=>'DEF','x'=>62,'y'=>76],['pos'=>'DEF','x'=>85,'y'=>72],
                ['pos'=>'MED','x'=>28,'y'=>50],['pos'=>'MED','x'=>50,'y'=>54],['pos'=>'MED','x'=>72,'y'=>50],
                ['pos'=>'DEL','x'=>20,'y'=>22],['pos'=>'DEL','x'=>50,'y'=>15],['pos'=>'DEL','x'=>80,'y'=>22],
            ],
            '4-4-2' => [
                ['pos'=>'POR','x'=>50,'y'=>92],
                ['pos'=>'DEF','x'=>15,'y'=>72],['pos'=>'DEF','x'=>38,'y'=>76],['pos'=>'DEF','x'=>62,'y'=>76],['pos'=>'DEF','x'=>85,'y'=>72],
                ['pos'=>'MED','x'=>15,'y'=>48],['pos'=>'MED','x'=>38,'y'=>52],['pos'=>'MED','x'=>62,'y'=>52],['pos'=>'MED','x'=>85,'y'=>48],
                ['pos'=>'DEL','x'=>35,'y'=>18],['pos'=>'DEL','x'=>65,'y'=>18],
            ],
            '4-2-3-1' => [
                ['pos'=>'POR','x'=>50,'y'=>92],
                ['pos'=>'DEF','x'=>15,'y'=>74],['pos'=>'DEF','x'=>38,'y'=>78],['pos'=>'DEF','x'=>62,'y'=>78],['pos'=>'DEF','x'=>85,'y'=>74],
                ['pos'=>'MED','x'=>35,'y'=>58],['pos'=>'MED','x'=>65,'y'=>58],
                ['pos'=>'MED','x'=>18,'y'=>36],['pos'=>'MED','x'=>50,'y'=>40],['pos'=>'MED','x'=>82,'y'=>36],
                ['pos'=>'DEL','x'=>50,'y'=>14],
            ],
            '3-5-2' => [
                ['pos'=>'POR','x'=>50,'y'=>92],
                ['pos'=>'DEF','x'=>25,'y'=>76],['pos'=>'DEF','x'=>50,'y'=>80],['pos'=>'DEF','x'=>75,'y'=>76],
                ['pos'=>'MED','x'=>10,'y'=>50],['pos'=>'MED','x'=>32,'y'=>54],['pos'=>'MED','x'=>50,'y'=>48],['pos'=>'MED','x'=>68,'y'=>54],['pos'=>'MED','x'=>90,'y'=>50],
                ['pos'=>'DEL','x'=>38,'y'=>18],['pos'=>'DEL','x'=>62,'y'=>18],
            ],
            '3-4-3' => [
                ['pos'=>'POR','x'=>50,'y'=>92],
                ['pos'=>'DEF','x'=>25,'y'=>76],['pos'=>'DEF','x'=>50,'y'=>80],['pos'=>'DEF','x'=>75,'y'=>76],
                ['pos'=>'MED','x'=>15,'y'=>52],['pos'=>'MED','x'=>38,'y'=>50],['pos'=>'MED','x'=>62,'y'=>50],['pos'=>'MED','x'=>85,'y'=>52],
                ['pos'=>'DEL','x'=>20,'y'=>20],['pos'=>'DEL','x'=>50,'y'=>14],['pos'=>'DEL','x'=>80,'y'=>20],
            ],
            '5-3-2' => [
                ['pos'=>'POR','x'=>50,'y'=>92],
                ['pos'=>'DEF','x'=>10,'y'=>70],['pos'=>'DEF','x'=>30,'y'=>76],['pos'=>'DEF','x'=>50,'y'=>78],['pos'=>'DEF','x'=>70,'y'=>76],['pos'=>'DEF','x'=>90,'y'=>70],
                ['pos'=>'MED','x'=>28,'y'=>48],['pos'=>'MED','x'=>50,'y'=>44],['pos'=>'MED','x'=>72,'y'=>48],
                ['pos'=>'DEL','x'=>38,'y'=>16],['pos'=>'DEL','x'=>62,'y'=>16],
            ],
            '4-1-4-1' => [
                ['pos'=>'POR','x'=>50,'y'=>92],
                ['pos'=>'DEF','x'=>15,'y'=>74],['pos'=>'DEF','x'=>38,'y'=>78],['pos'=>'DEF','x'=>62,'y'=>78],['pos'=>'DEF','x'=>85,'y'=>74],
                ['pos'=>'MED','x'=>50,'y'=>60],
                ['pos'=>'MED','x'=>15,'y'=>40],['pos'=>'MED','x'=>38,'y'=>36],['pos'=>'MED','x'=>62,'y'=>36],['pos'=>'MED','x'=>85,'y'=>40],
                ['pos'=>'DEL','x'=>50,'y'=>14],
            ],
        ],
    ],
    2 => [
        'nombre' => 'Básquetbol',
        'slug'   => 'basquet',
        'icono'  => 'BAL',
        'apodo_label'       => 'Apodo / nombre de cancha',
        'apodo_placeholder' => 'Ej. Air, Big Shot, Zorro',
        'posiciones' => [
            'Base'      => 'Base (PG)',
            'Escolta'   => 'Escolta (SG)',
            'Alero'     => 'Alero (SF)',
            'Ala-Pivot' => 'Ala-Pívot (PF)',
            'Pivot'     => 'Pívot (C)',
        ],
        'habilidades' => [
            'Tiro'     => 'Tiro',
            'Manejo'   => 'Manejo de balón',
            'Defensa'  => 'Defensa',
            'Rebote'   => 'Rebote',
        ],
        'stat_labels' => [
            'goles_puntos'      => 'Puntos',
            'asistencias'       => 'Asistencias',
            'pases_completados' => 'Pases clave',
            'tiros'             => 'Tiros a canasta',
            'robos_rebotes'     => 'Rebotes',
            'faltas'            => 'Faltas',
        ],
        'formaciones' => [
            '5 Titulares' => ['Base','Escolta','Alero','Ala-Pivot','Pivot'],
        ],
        'slots' => [
            '5 Titulares' => [
                ['pos'=>'Base','x'=>50,'y'=>85],
                ['pos'=>'Escolta','x'=>20,'y'=>62],
                ['pos'=>'Alero','x'=>80,'y'=>62],
                ['pos'=>'Ala-Pivot','x'=>32,'y'=>28],
                ['pos'=>'Pivot','x'=>68,'y'=>28],
            ],
            'Zona 2-3' => [
                ['pos'=>'Base','x'=>50,'y'=>78],
                ['pos'=>'Escolta','x'=>25,'y'=>60],['pos'=>'Alero','x'=>75,'y'=>60],
                ['pos'=>'Ala-Pivot','x'=>30,'y'=>30],['pos'=>'Pivot','x'=>70,'y'=>30],
            ],
            'Zona 1-3-1' => [
                ['pos'=>'Base','x'=>50,'y'=>85],
                ['pos'=>'Escolta','x'=>18,'y'=>55],['pos'=>'Alero','x'=>50,'y'=>50],['pos'=>'Ala-Pivot','x'=>82,'y'=>55],
                ['pos'=>'Pivot','x'=>50,'y'=>20],
            ],
            'Ofensiva 3-2' => [
                ['pos'=>'Base','x'=>50,'y'=>82],
                ['pos'=>'Escolta','x'=>15,'y'=>60],['pos'=>'Alero','x'=>85,'y'=>60],
                ['pos'=>'Ala-Pivot','x'=>32,'y'=>22],['pos'=>'Pivot','x'=>68,'y'=>22],
            ],
        ],
    ],
];

// ---------------------------------------------------------------------
// CUESTIONARIOS DE EVALUACIÓN — la calificación de cada habilidad se
// calcula automáticamente a partir de varias preguntas (escala 1-5),
// en vez de capturarse una habilidad manual a la vez.
// ---------------------------------------------------------------------
$CUESTIONARIOS = [
    1 => [ // Fútbol
        'Tecnica' => [
            'Control orientado del balón bajo presión',
            'Precisión en el primer toque',
            'Regate en espacios reducidos',
        ],
        'Resistencia' => [
            'Mantiene el ritmo en los últimos 15 minutos',
            'Recuperación entre esfuerzos (sprints repetidos)',
            'Capacidad de trabajo en sesiones largas',
        ],
        'Velocidad' => [
            'Aceleración en los primeros metros',
            'Velocidad punta en carrera libre',
            'Cambios de ritmo y de dirección',
        ],
        'Pase' => [
            'Precisión en pases cortos',
            'Visión y precisión en pases largos',
            'Decisión al pasar bajo presión',
        ],
    ],
    2 => [ // Básquetbol
        'Tiro' => [
            'Acierto en tiro libre',
            'Tiro de media distancia',
            'Tiro de tres puntos',
        ],
        'Manejo' => [
            'Control de balón con ambas manos',
            'Manejo bajo presión defensiva',
            'Cambios de dirección con balón',
        ],
        'Defensa' => [
            'Defensa uno contra uno',
            'Lectura y anticipación defensiva',
            'Comunicación y ayuda defensiva',
        ],
        'Rebote' => [
            'Posicionamiento para el rebote',
            'Salto y timing',
            'Rebote ofensivo (segunda oportunidad)',
        ],
    ],
];

// ---------------------------------------------------------------------
// Etapas LTAD (Long-Term Athlete Development, modelo de Balyi)
// Devuelve etapa + enfoque sugerido según edad
// ---------------------------------------------------------------------
function ltad_stage(int $edad): array {
    if ($edad <= 8)  return ['etapa' => 'Iniciación activa', 'rango' => 'hasta 8 años', 'enfoque' => 'Movimiento fundamental, juego libre, multideporte. Evitar especialización.'];
    if ($edad <= 11) return ['etapa' => 'Fundamentos (ABC del movimiento)', 'rango' => '6-11 años', 'enfoque' => 'Agilidad, balance, coordinación. Cargas muy bajas, técnica antes que resultado.'];
    if ($edad <= 15) return ['etapa' => 'Entrenar para entrenar', 'rango' => '12-15 años', 'enfoque' => 'Ventana óptima de entrenabilidad aeróbica y fuerza. Construir capacidad de trabajo sin sobrecargar.'];
    if ($edad <= 17) return ['etapa' => 'Entrenar para competir', 'rango' => '16-17 años', 'enfoque' => 'Especialización por posición, alta intensidad controlada, vigilar ACWR de cerca.'];
    if ($edad <= 22) return ['etapa' => 'Entrenar para ganar', 'rango' => '18-22 años', 'enfoque' => 'Rendimiento máximo, periodización avanzada, prevención de lesiones por acumulación.'];
    return ['etapa' => 'Vida activa / alto rendimiento', 'rango' => '23+ años', 'enfoque' => 'Mantenimiento de forma física y longevidad deportiva.'];
}

// Categoría de formación por edad (usada para comparar contra el promedio del grupo correcto)
function categoria_edad(int $edad): string {
    if ($edad <= 13) return 'Sub-13';
    if ($edad <= 15) return 'Sub-15';
    if ($edad <= 17) return 'Sub-17';
    if ($edad <= 20) return 'Sub-20';
    return 'Mayor';
}

function categoria_rango(string $categoria): array {
    return match ($categoria) {
        'Sub-13' => [0, 13], 'Sub-15' => [14, 15], 'Sub-17' => [16, 17],
        'Sub-20' => [18, 20], default => [21, 99],
    };
}

function deporte_info(array $DEPORTES, int $deporteId): array {
    return $DEPORTES[$deporteId] ?? $DEPORTES[1];
}
