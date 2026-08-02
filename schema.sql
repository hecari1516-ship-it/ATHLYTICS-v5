-- =====================================================================
--  ATHLYTICS — Sistema de Monitoreo de Carga y Rendimiento
--  Script completo de base de datos v2 (MySQL / MariaDB)
--  Multi-deporte · Multi-entrenador · LTAD · Formaciones · Partidos
--  Ejecutar completo en phpMyAdmin (pestaña SQL) o Workbench
-- =====================================================================

-- ---------------------------------------------------------------------
-- MIGRACIÓN (v4): si ya tienes la base de datos de una versión anterior
-- y NO quieres borrar tus datos, ejecuta estas líneas (y luego vuelve a
-- ejecutar solo el bloque "VISTA: ficha_jugador" de más abajo para que
-- incluya la columna nueva) en vez de correr todo el script:
--
--   ALTER TABLE jugadores ADD COLUMN apodo VARCHAR(60) DEFAULT NULL AFTER nombre;
--   DROP VIEW IF EXISTS ficha_jugador;
--   -- luego copia y ejecuta el bloque CREATE VIEW ficha_jugador AS ... (más abajo)
--
-- Si prefieres empezar de cero, ejecuta el script completo de abajo
-- (esto borra toda la base de datos existente).
-- ---------------------------------------------------------------------

DROP DATABASE IF EXISTS athlytics_db;
CREATE DATABASE athlytics_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE athlytics_db;

-- ---------------------------------------------------------------------
-- 1. ENTRENADORES (usuarios del sistema) — ahora multi-entrenador
-- ---------------------------------------------------------------------
CREATE TABLE entrenadores (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(100) NOT NULL,
    correo        VARCHAR(100) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,   -- password_hash() de PHP
    rol           ENUM('admin','entrenador') NOT NULL DEFAULT 'entrenador',
    creado_en     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. DEPORTES (catálogo — habilita el sistema multi-deporte)
-- ---------------------------------------------------------------------
CREATE TABLE deportes (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    nombre   VARCHAR(40) NOT NULL UNIQUE,
    slug     VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO deportes (nombre, slug) VALUES ('Fútbol','futbol'), ('Básquetbol','basquet');

-- ---------------------------------------------------------------------
-- 3. JUGADORES — datos ampliados de rendimiento y desarrollo
-- ---------------------------------------------------------------------
CREATE TABLE jugadores (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    nombre            VARCHAR(100) NOT NULL,
    apodo             VARCHAR(60) DEFAULT NULL,
    numero            INT DEFAULT NULL,
    deporte_id        INT NOT NULL,
    posicion          VARCHAR(20) NOT NULL,
    fecha_nacimiento  DATE NOT NULL,
    foto              VARCHAR(255) DEFAULT NULL,
    talla_cm          DECIMAL(5,1) DEFAULT NULL,
    peso_kg           DECIMAL(5,1) DEFAULT NULL,
    condiciones_medicas TEXT DEFAULT NULL,
    entrenador_id     INT NOT NULL,
    creado_en         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_jugador_entrenador FOREIGN KEY (entrenador_id)
        REFERENCES entrenadores(id) ON DELETE CASCADE,
    CONSTRAINT fk_jugador_deporte FOREIGN KEY (deporte_id)
        REFERENCES deportes(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. MEDIDAS FÍSICAS — historial de talla/peso (curva de crecimiento)
-- ---------------------------------------------------------------------
CREATE TABLE medidas_fisicas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    jugador_id  INT NOT NULL,
    fecha       DATE NOT NULL,
    talla_cm    DECIMAL(5,1) DEFAULT NULL,
    peso_kg     DECIMAL(5,1) DEFAULT NULL,
    CONSTRAINT fk_medida_jugador FOREIGN KEY (jugador_id)
        REFERENCES jugadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. SESIONES DE ENTRENAMIENTO
-- ---------------------------------------------------------------------
CREATE TABLE sesiones (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    entrenador_id  INT NOT NULL,
    fecha          DATE NOT NULL,
    tipo           VARCHAR(50) NOT NULL,
    duracion_min   INT NOT NULL,
    creado_en      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sesion_entrenador FOREIGN KEY (entrenador_id)
        REFERENCES entrenadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. ASISTENCIA + RPE (percepción de esfuerzo, escala de Foster 1-10)
-- ---------------------------------------------------------------------
CREATE TABLE asistencia (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    sesion_id    INT NOT NULL,
    jugador_id   INT NOT NULL,
    asistio      TINYINT(1) NOT NULL DEFAULT 1,
    rpe          TINYINT DEFAULT NULL,
    UNIQUE KEY uq_sesion_jugador (sesion_id, jugador_id),
    CONSTRAINT fk_asistencia_sesion FOREIGN KEY (sesion_id)
        REFERENCES sesiones(id) ON DELETE CASCADE,
    CONSTRAINT fk_asistencia_jugador FOREIGN KEY (jugador_id)
        REFERENCES jugadores(id) ON DELETE CASCADE,
    CONSTRAINT chk_rpe CHECK (rpe IS NULL OR (rpe BETWEEN 1 AND 10))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. EVALUACIONES TÉCNICAS (habilidad dinámica según deporte)
-- ---------------------------------------------------------------------
CREATE TABLE evaluaciones (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    jugador_id    INT NOT NULL,
    fecha         DATE NOT NULL,
    habilidad     VARCHAR(30) NOT NULL,
    calificacion  DECIMAL(3,1) NOT NULL,
    CONSTRAINT fk_evaluacion_jugador FOREIGN KEY (jugador_id)
        REFERENCES jugadores(id) ON DELETE CASCADE,
    CONSTRAINT chk_calificacion CHECK (calificacion BETWEEN 1 AND 10)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. CARGA_JUGADOR (tabla calculada — ACWR, Gabbett 2016)
-- ---------------------------------------------------------------------
CREATE TABLE carga_jugador (
    jugador_id       INT PRIMARY KEY,
    carga_aguda      DECIMAL(8,2) DEFAULT 0,
    carga_cronica    DECIMAL(8,2) DEFAULT 0,
    acwr             DECIMAL(5,2) DEFAULT NULL,
    nivel_riesgo     VARCHAR(20) DEFAULT 'Sin datos',
    actualizado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_carga_jugador FOREIGN KEY (jugador_id)
        REFERENCES jugadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. FORMACIONES — plantillas tácticas tipo FIFA/NBA (drag & drop)
-- ---------------------------------------------------------------------
CREATE TABLE formaciones (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    entrenador_id  INT NOT NULL,
    deporte_id     INT NOT NULL,
    nombre         VARCHAR(80) NOT NULL,
    esquema        VARCHAR(20) NOT NULL,
    layout_json    TEXT NOT NULL,
    creado_en      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_formacion_entrenador FOREIGN KEY (entrenador_id)
        REFERENCES entrenadores(id) ON DELETE CASCADE,
    CONSTRAINT fk_formacion_deporte FOREIGN KEY (deporte_id)
        REFERENCES deportes(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. PARTIDOS — asignación de partidos/encuentros
-- ---------------------------------------------------------------------
CREATE TABLE partidos (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    entrenador_id  INT NOT NULL,
    deporte_id     INT NOT NULL,
    formacion_id   INT DEFAULT NULL,
    rival          VARCHAR(100) NOT NULL,
    fecha          DATE NOT NULL,
    sede           ENUM('Local','Visitante') NOT NULL DEFAULT 'Local',
    resultado_favor    INT DEFAULT NULL,
    resultado_contra   INT DEFAULT NULL,
    notas          TEXT DEFAULT NULL,
    creado_en      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_partido_entrenador FOREIGN KEY (entrenador_id)
        REFERENCES entrenadores(id) ON DELETE CASCADE,
    CONSTRAINT fk_partido_deporte FOREIGN KEY (deporte_id)
        REFERENCES deportes(id),
    CONSTRAINT fk_partido_formacion FOREIGN KEY (formacion_id)
        REFERENCES formaciones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. PARTIDO_JUGADORES — rendimiento individual por partido
--     (mínimo 6 métricas, etiquetas dinámicas por deporte en config.php)
-- ---------------------------------------------------------------------
CREATE TABLE partido_jugadores (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    partido_id          INT NOT NULL,
    jugador_id          INT NOT NULL,
    titular             TINYINT(1) NOT NULL DEFAULT 1,
    minutos             INT DEFAULT 0,
    goles_puntos        INT DEFAULT 0,
    asistencias         INT DEFAULT 0,
    pases_completados   INT DEFAULT 0,
    tiros               INT DEFAULT 0,
    robos_rebotes       INT DEFAULT 0,
    faltas              INT DEFAULT 0,
    calificacion_partido DECIMAL(3,1) DEFAULT NULL,
    UNIQUE KEY uq_partido_jugador (partido_id, jugador_id),
    CONSTRAINT fk_pj_partido FOREIGN KEY (partido_id)
        REFERENCES partidos(id) ON DELETE CASCADE,
    CONSTRAINT fk_pj_jugador FOREIGN KEY (jugador_id)
        REFERENCES jugadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
--  PROCEDIMIENTO ALMACENADO: recalcular_carga
-- =====================================================================
DELIMITER $$

CREATE PROCEDURE recalcular_carga(IN p_jugador_id INT)
BEGIN
    DECLARE v_aguda DECIMAL(10,2);
    DECLARE v_cronica DECIMAL(10,2);
    DECLARE v_acwr DECIMAL(5,2);
    DECLARE v_riesgo VARCHAR(20);
    DECLARE v_primera_fecha DATE;
    DECLARE v_num_sesiones INT;

    SELECT COALESCE(SUM(s.duracion_min * a.rpe), 0) / 7
      INTO v_aguda
      FROM asistencia a
      JOIN sesiones s ON s.id = a.sesion_id
     WHERE a.jugador_id = p_jugador_id
       AND a.asistio = 1
       AND a.rpe IS NOT NULL
       AND s.fecha >= CURDATE() - INTERVAL 7 DAY;

    SELECT COALESCE(SUM(s.duracion_min * a.rpe), 0) / 28,
           MIN(s.fecha), COUNT(DISTINCT s.fecha)
      INTO v_cronica, v_primera_fecha, v_num_sesiones
      FROM asistencia a
      JOIN sesiones s ON s.id = a.sesion_id
     WHERE a.jugador_id = p_jugador_id
       AND a.asistio = 1
       AND a.rpe IS NOT NULL
       AND s.fecha >= CURDATE() - INTERVAL 28 DAY;

    -- Con muy poco historial, el promedio de 28 días se subestima y el ACWR
    -- se dispara artificialmente (falso "alto riesgo"). Mientras se acumula
    -- un mínimo de datos, se marca como 'Acumulando datos' en vez de arriesgar
    -- una alerta falsa.
    IF v_primera_fecha IS NULL THEN
        SET v_acwr = NULL;
        SET v_riesgo = 'Sin datos';
    ELSEIF v_num_sesiones < 3 OR DATEDIFF(CURDATE(), v_primera_fecha) < 10 THEN
        SET v_acwr = NULL;
        SET v_riesgo = 'Acumulando datos';
    ELSE
        SET v_acwr = ROUND(v_aguda / NULLIF(v_cronica, 0), 2);
        SET v_riesgo = CASE
            WHEN v_acwr IS NULL THEN 'Acumulando datos'
            WHEN v_acwr > 1.5 THEN 'Alto riesgo'
            WHEN v_acwr > 1.3 THEN 'Precaucion'
            WHEN v_acwr < 0.8 THEN 'Baja carga'
            ELSE 'Optimo'
        END;
    END IF;

    INSERT INTO carga_jugador (jugador_id, carga_aguda, carga_cronica, acwr, nivel_riesgo)
    VALUES (p_jugador_id, v_aguda, v_cronica, v_acwr, v_riesgo)
    ON DUPLICATE KEY UPDATE
        carga_aguda   = v_aguda,
        carga_cronica = v_cronica,
        acwr          = v_acwr,
        nivel_riesgo  = v_riesgo;
END$$

DELIMITER ;

-- =====================================================================
--  TRIGGERS
-- =====================================================================
DELIMITER $$

CREATE TRIGGER trg_asistencia_insert
AFTER INSERT ON asistencia
FOR EACH ROW
BEGIN
    CALL recalcular_carga(NEW.jugador_id);
END$$

CREATE TRIGGER trg_asistencia_update
AFTER UPDATE ON asistencia
FOR EACH ROW
BEGIN
    CALL recalcular_carga(NEW.jugador_id);
END$$

DELIMITER ;

-- =====================================================================
--  VISTA: ficha_jugador
-- =====================================================================
CREATE VIEW ficha_jugador AS
SELECT
    j.id,
    j.nombre,
    j.apodo,
    j.numero,
    j.posicion,
    j.foto,
    j.fecha_nacimiento,
    j.talla_cm,
    j.peso_kg,
    j.condiciones_medicas,
    TIMESTAMPDIFF(YEAR, j.fecha_nacimiento, CURDATE()) AS edad,
    j.entrenador_id,
    j.deporte_id,
    d.nombre AS deporte_nombre,
    d.slug   AS deporte_slug,
    ROUND(COALESCE(ev.promedio_tecnico, 5), 1)                AS promedio_tecnico,
    c.carga_aguda                                              AS carga_aguda,
    c.carga_cronica                                            AS carga_cronica,
    COALESCE(c.acwr, NULL)                                     AS acwr,
    COALESCE(c.nivel_riesgo, 'Sin datos')                      AS nivel_riesgo,
    ROUND(
        (COALESCE(ev.promedio_tecnico, 5) * 10) * 0.6 +
        (CASE
            WHEN c.acwr IS NULL THEN 65
            WHEN c.acwr BETWEEN 0.8 AND 1.3 THEN 100
            WHEN c.acwr BETWEEN 1.3 AND 1.5 THEN 75
            ELSE 55
         END) * 0.4
    , 0) AS ovr
FROM jugadores j
JOIN deportes d ON d.id = j.deporte_id
LEFT JOIN (
    SELECT jugador_id, AVG(calificacion) AS promedio_tecnico
    FROM evaluaciones
    GROUP BY jugador_id
) ev ON ev.jugador_id = j.id
LEFT JOIN carga_jugador c ON c.jugador_id = j.id;

-- =====================================================================
--  DATOS DE PRUEBA (entrenador_id = 1 se crea desde seed.php)
-- =====================================================================
INSERT INTO jugadores (nombre, numero, deporte_id, posicion, fecha_nacimiento, talla_cm, peso_kg, entrenador_id) VALUES
('Kevin Torres',     9,  1, 'DEL', '2011-03-12', 168.0, 58.0, 1),
('Diego Ramírez',    11, 1, 'DEL', '2010-07-22', 172.5, 63.0, 1),
('Sofía Álvarez',    8,  1, 'MED', '2011-01-30', 160.0, 52.0, 1),
('Marco Herrera',    5,  1, 'MED', '2009-11-05', 175.0, 66.0, 1),
('Luis Cabrera',     4,  1, 'DEF', '2010-05-18', 170.0, 61.0, 1),
('Andrés Molina',    3,  1, 'DEF', '2011-09-09', 165.0, 55.0, 1),
('Iker Domínguez',   2,  1, 'DEF', '2009-02-14', 178.0, 68.0, 1),
('Renata Ochoa',     6,  1, 'MED', '2010-12-01', 162.0, 54.0, 1),
('Pablo Guerrero',   7,  1, 'DEL', '2011-06-27', 166.0, 57.0, 1),
('Emilio Vidal',     1,  1, 'POR', '2009-08-19', 180.0, 70.0, 1),
('Jordan Reyes',     23, 2, 'Base', '2010-04-02', 175.0, 62.0, 1),
('Bruno Salcedo',    32, 2, 'Alero', '2009-10-11', 188.0, 78.0, 1);

INSERT INTO medidas_fisicas (jugador_id, fecha, talla_cm, peso_kg) VALUES
(1, CURDATE() - INTERVAL 180 DAY, 163.0, 53.0), (1, CURDATE(), 168.0, 58.0),
(2, CURDATE() - INTERVAL 180 DAY, 169.0, 60.0), (2, CURDATE(), 172.5, 63.0);

INSERT INTO sesiones (entrenador_id, fecha, tipo, duracion_min) VALUES
(1, CURDATE() - INTERVAL 27 DAY, 'Físico',   60),
(1, CURDATE() - INTERVAL 20 DAY, 'Táctico',  75),
(1, CURDATE() - INTERVAL 13 DAY, 'Técnico',  70),
(1, CURDATE() - INTERVAL 6  DAY, 'Físico',   90),
(1, CURDATE() - INTERVAL 2  DAY, 'Táctico',  80);

INSERT INTO asistencia (sesion_id, jugador_id, asistio, rpe) VALUES
(1, 1, 1, 6), (1, 2, 1, 7), (1, 3, 1, 5), (1, 4, 1, 6), (1, 5, 1, 7),
(2, 1, 1, 7), (2, 2, 1, 8), (2, 3, 1, 6), (2, 4, 1, 7), (2, 5, 1, 6),
(3, 1, 1, 8), (3, 2, 1, 6), (3, 3, 1, 7), (3, 4, 1, 5), (3, 5, 1, 8),
(4, 1, 1, 9), (4, 2, 1, 9), (4, 3, 1, 6), (4, 4, 1, 7), (4, 5, 1, 9),
(5, 1, 1, 9), (5, 2, 1, 8), (5, 3, 1, 7), (5, 4, 1, 6), (5, 5, 1, 8),
(5, 6, 1, 6);

INSERT INTO evaluaciones (jugador_id, fecha, habilidad, calificacion) VALUES
(1, CURDATE()-INTERVAL 20 DAY, 'Tecnica', 7.5), (1, CURDATE()-INTERVAL 5 DAY, 'Tecnica', 8.0),
(1, CURDATE()-INTERVAL 20 DAY, 'Resistencia', 7.0), (1, CURDATE()-INTERVAL 5 DAY, 'Resistencia', 7.5),
(1, CURDATE()-INTERVAL 20 DAY, 'Velocidad', 8.5), (1, CURDATE()-INTERVAL 5 DAY, 'Velocidad', 8.5),
(1, CURDATE()-INTERVAL 20 DAY, 'Pase', 6.5), (1, CURDATE()-INTERVAL 5 DAY, 'Pase', 7.0),
(2, CURDATE()-INTERVAL 10 DAY, 'Tecnica', 8.5), (2, CURDATE()-INTERVAL 10 DAY, 'Resistencia', 6.0),
(2, CURDATE()-INTERVAL 10 DAY, 'Velocidad', 9.0), (2, CURDATE()-INTERVAL 10 DAY, 'Pase', 7.0),
(3, CURDATE()-INTERVAL 8  DAY, 'Tecnica', 7.0), (3, CURDATE()-INTERVAL 8  DAY, 'Resistencia', 8.0),
(3, CURDATE()-INTERVAL 8  DAY, 'Velocidad', 6.5), (3, CURDATE()-INTERVAL 8  DAY, 'Pase', 8.5),
(11, CURDATE()-INTERVAL 8 DAY, 'Tiro', 7.5), (11, CURDATE()-INTERVAL 8 DAY, 'Manejo', 8.0),
(11, CURDATE()-INTERVAL 8 DAY, 'Defensa', 6.5), (11, CURDATE()-INTERVAL 8 DAY, 'Rebote', 5.5);

INSERT INTO partidos (entrenador_id, deporte_id, rival, fecha, sede, resultado_favor, resultado_contra) VALUES
(1, 1, 'Deportivo Norte', CURDATE() - INTERVAL 14 DAY, 'Local', 3, 1),
(1, 1, 'Atlético Sur',    CURDATE() - INTERVAL 7  DAY, 'Visitante', 1, 1);

INSERT INTO partido_jugadores (partido_id, jugador_id, titular, minutos, goles_puntos, asistencias, pases_completados, tiros, robos_rebotes, faltas, calificacion_partido) VALUES
(1, 1, 1, 90, 2, 1, 34, 5, 2, 1, 8.5),
(1, 2, 1, 90, 1, 0, 28, 4, 1, 2, 7.8),
(1, 3, 1, 85, 0, 2, 41, 1, 4, 0, 7.5),
(2, 1, 1, 90, 1, 0, 30, 3, 1, 2, 7.2),
(2, 2, 1, 75, 0, 1, 25, 2, 0, 1, 6.8),
(2, 3, 1, 90, 0, 0, 38, 0, 5, 1, 7.0);

-- Fin del script
