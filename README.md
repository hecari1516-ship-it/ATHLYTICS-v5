# ATHLYTICS v3 — Sistema de Monitoreo de Carga y Rendimiento

Sistema multi-deporte (Fútbol / Básquetbol) y multi-entrenador para clubes
formativos: registra entrenamientos, captura RPE, calcula ACWR de forma
confiable (sin falsas alarmas por falta de historial), gestiona fichas tipo
"carta FIFA/NBA" con foto y número, arma formaciones con arrastrar-y-soltar
(con autoasignación por rendimiento), asigna partidos y genera reportes —
todo con respaldo en el modelo LTAD (Balyi) para comparar jugadores solo
contra su categoría de edad correcta.

## 1. Requisitos
- XAMPP (Apache + MySQL + PHP 8+) — https://www.apachefriends.org
- Navegador con conexión a internet (Chart.js y las fuentes se cargan desde CDN)
- La carpeta `uploads/` debe tener permisos de escritura (para las fotos)

## 2. Instalación (5 minutos)

1. Instala XAMPP y abre el Panel de Control. Inicia **Apache** y **MySQL**.
2. Copia toda esta carpeta `athlytics/` dentro de:
   - Windows: `C:\xampp\htdocs\athlytics\`
   - Mac: `/Applications/XAMPP/htdocs/athlytics/`
3. Abre `http://localhost/phpmyadmin`, pestaña **SQL**, pega TODO el
   contenido de `schema.sql` y ejecuta.
4. Ve a `http://localhost/athlytics/seed.php` para crear el usuario
   administrador:
   - Correo: `admin@athlytics.com`
   - Contraseña: `admin123`
5. **Borra `seed.php`** después de usarlo.
6. Cualquier otro entrenador puede crear su propia cuenta desde
   `registro.php` / el enlace en el login.

## 3. Novedades de esta versión

### Flujo de trabajo
- **Menú centrado**: la barra de navegación ahora tiene los enlaces
  centrados; el único botón alineado a la derecha es **Salir**.
- **Saludo limpio**: ya no dice "Hola, Entrenador 👋"; solo muestra el
  nombre, sin emojis.
- **Iconos limpios**: los emojis de deporte (⚽🏀) y de acciones (⚡) se
  reemplazaron por badges de texto minimalistas y consistentes.
- **Secciones conectadas**: Partidos ⇄ Entrenamiento, Formaciones ⇄ Mapa de
  riesgo, y Plantel ⇄ Agregar jugador tienen enlaces directos entre sí — ya
  no son islas separadas.
- **Guías sin texto**: se removieron los párrafos de instrucciones paso a
  paso; el flujo ahora se explica solo con la interfaz (animaciones,
  botones de una acción, confirmaciones tipo notificación).

### ACWR y mapa de riesgo — corregido
- **Bug corregido**: antes, con poco historial de entrenamientos, el ACWR
  se disparaba y marcaba "Alto riesgo" en casi todos los jugadores (el
  promedio de 28 días se subestimaba con pocos datos). Ahora el sistema
  exige un mínimo de historial (al menos 3 sesiones distintas en al menos
  10 días) antes de calcular un ACWR real; mientras tanto, muestra
  **"Acumulando datos"** — un estado neutral, no una alarma falsa.
- El **mapa de riesgo** se reordenó por prioridad real de riesgo, con una
  leyenda de chips de color (sin párrafos largos) y el nuevo estado
  "Acumulando datos" en un color propio (azul), distinto de los colores de
  riesgo (rojo/ámbar/verde).
- El color de la escala de esfuerzo (RPE) en **Registrar entrenamiento**
  también se cambió a azul, para no confundirse visualmente con el verde
  de "riesgo óptimo".

### Jugadores
- **Editar jugador** (`editar_jugador.php`): botón ✎ en la ficha — cambia
  nombre, número, posición, fecha de nacimiento, talla, peso, condiciones
  médicas y foto.
- **Número de camiseta**: campo `numero` en el registro y edición, ahora
  mostrado en grande y de forma prominente en la ficha (ya no como texto
  pequeño pegado a la posición, que generaba confusión).
- **Foto del jugador**: subida de imagen (JPG/PNG/WEBP, máx. 4MB) en el
  registro y la edición; se muestra en la tarjeta del plantel, la ficha y
  la banca de formaciones.
- **Evaluación por cuestionario**: `evaluar_jugador.php` ya no captura una
  habilidad manual a la vez. Presenta un cuestionario completo (3
  preguntas por habilidad, escala 1-5) y calcula automáticamente la
  calificación de cada habilidad al guardar — una sola acción, no varias
  repeticiones manuales.

### Formaciones
- **Más esquemas**: fútbol pasó de 2 a 7 (4-3-3, 4-4-2, 4-2-3-1, 3-5-2,
  3-4-3, 5-3-2, 4-1-4-1) y básquet de 1 a 4 (5 Titulares, Zona 2-3, Zona
  1-3-1, Ofensiva 3-2).
- **Autoasignación por rendimiento**: botón "Autoasignar por rendimiento"
  en el constructor — llena la cancha con los jugadores de mayor OVR que
  coincidan con cada posición, con un botón de "Limpiar cancha" aparte.

### Interfaz
- Animaciones de entrada en tarjetas y paneles, línea activa animada en el
  menú, efecto hover en botones/tarjetas, animación al colocar un jugador
  en la cancha.
- Entrenador de ejemplo con nombre normal (ya no dice "Demo").

## 4. Estructura de archivos

```
athlytics/
├── schema.sql              ← script completo de base de datos (v3, ACWR corregido)
├── config.php               ← catálogo por deporte, LTAD, cuestionarios
├── nav.php                  ← barra de navegación (centrada, Salir a la derecha)
├── seed.php / registro.php  ← alta del primer admin / alta de entrenadores
├── index.php / logout.php / auth_check.php
├── plantel.php                ← lista de jugadores (foto, número, filtro)
├── agregar_jugador.php        ← alta de jugador (foto, número, datos físicos)
├── editar_jugador.php         ← edición de un jugador existente
├── ficha.php                  ← ficha: OVR, radar, ACWR, LTAD, comparativa,
│                                 curva de crecimiento, foto, dorsal grande
├── evaluar_jugador.php        ← cuestionario de evaluación (auto-calificado)
├── registrar_sesion.php       ← entrenamiento + RPE (color propio)
├── equipo_riesgo.php           ← mapa de riesgo (leyenda de chips, sin texto largo)
├── formacion.php               ← constructor drag & drop + autoasignación
├── partidos.php / resultado_partido.php  ← partidos y estadísticas
├── reportes.php                  ← reportes de equipo e individual
├── uploads/                       ← fotos de jugadores subidas
└── css/style.css               ← identidad visual + animaciones
```

## 5. Para el documento técnico

- **Diagrama Relacional:** en phpMyAdmin, "Diseñador" → exportar imagen.
- **Código SQL:** usa `schema.sql` completo.
- **Bibliografía sugerida:**
  - Gabbett, T. J. (2016). *British Journal of Sports Medicine*.
  - Foster, C. (1998). *Medicine & Science in Sports & Exercise*.
  - Balyi, I., Way, R., & Higgs, C. (2013). *Long-Term Athlete Development*.
    Human Kinetics.

## 6. Subir a GitHub

```bash
git init
git add .
git commit -m "ATHLYTICS v3 - flujo optimizado, ACWR corregido, edicion de jugador, foto, cuestionario, autoasignacion"
git branch -M main
git remote add origin <URL-de-tu-repo>
git push -u origin main
```

No subas contraseñas reales ni el archivo `seed.php` si ya lo usaste.
