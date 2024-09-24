<?php

// Cargar la configuración necesaria, archivos de librería y namespaces.
require_once dirname(__FILE__) . '/../../config.php';
require_once dirname(__FILE__) . '/lib.php';
use mod_sqlab\schema_manager;
include 'snippets.php'; // Incluir archivo con los snippets.

try {

    // Obtener y validar los parámetros necesarios desde la solicitud (request).
    $cmid = optional_param('cmid', null, PARAM_INT);
    $attemptid = optional_param('attempt', null, PARAM_INT);
    $page = optional_param('page', 0, PARAM_INT);

    // Validar módulo del curso y datos adicionales.
    if ($cmid === null) {
        throw new moodle_exception('nocmid', 'sqlab');
    }

    $cm = get_coursemodule_from_id('sqlab', $cmid);
    if (!$cm) {
        throw new moodle_exception('invalidcoursemodule', 'sqlab');
    }

    $course = get_course($cm->course);
    if (!$course) {
        throw new moodle_exception('invalidcourseid', 'sqlab');
    }

    // Verificar si existe el ID del intento.
    if ($attemptid === null) {
        throw new moodle_exception('noattemptid', 'sqlab');
    }

    // Obtener intento desde la base de datos.
    $attempt = $DB->get_record('sqlab_attempts', array('id' => $attemptid), '*', IGNORE_MISSING);

    // Verificar propiedad y existencia del intento.
    if (!$attempt) {
        throw new moodle_exception('invalidattemptid', 'sqlab');
    }

    if ($attempt->userid != $USER->id) {
        throw new moodle_exception('notyourattempt', 'sqlab');
    }

    // Obtener y verificar la existencia de la instancia de SQLab.
    $sqlab = $DB->get_record('sqlab', array('id' => $cm->instance), '*', IGNORE_MISSING);
    if (!$sqlab) {
        throw new moodle_exception('invalidsqlabid', 'sqlab');
    }

} catch (moodle_exception $e) {

    // Redirigir según la excepción y los datos disponibles.
    if ($e->errorcode === 'invalidcoursemodule' && $cmid !== null) {
        $redirectUrl = new moodle_url('/my/');
    } else if ($e->errorcode === 'invalidsqlabid') {
        $redirectUrl = new moodle_url('/my/');
    } else {
        if (!empty($cmid)) {
            $redirectUrl = new moodle_url('/mod/sqlab/view.php', ['id' => $cmid]); // Redirigir a la página del módulo si el cmid es válido.
        } else {
            $redirectUrl = (!empty($course->id)) ? new moodle_url('/course/view.php', ['id' => $course->id]) : new moodle_url('/my/');
        }
    }

    // Mostrar error y redirigir.
    \core\notification::error(get_string($e->errorcode, $e->module));
    redirect($redirectUrl);
    exit;
}

// Forzar inicio de sesión del usuario y verificar capacidades.
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/sqlab:attempt', $context);

// Configurar los parámetros de la página.
$PAGE->set_url('/mod/sqlab/attempt.php', array('attempt' => $attemptid, 'cmid' => $cmid, 'page' => $page));
$PAGE->set_title(format_string($sqlab->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$formatoptions = new stdClass;
$formatoptions->noclean = true; // Permitir que el HTML no sea limpiado.
$formatoptions->overflowdiv = true; // Asegura que el contenido largo se maneje correctamente.
$formatoptions->context = $context; // Contexto actual para aplicar los permisos apropiados.
$formatoptions->filter = false; // Desactivar procesamiento de filtros para esta llamada.

// Enlace al archivo CSS específico de SQLab.
$PAGE->requires->css(new moodle_url('/mod/sqlab/styles/style.css'));

// Cargar el JS de CodeMirror desde CDN.
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/codemirror.min.js'), true);

// Cargar el CSS de CodeMirror desde CDN.
$PAGE->requires->css(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/codemirror.min.css'));

// Cargar el modo PostgreSQL para CodeMirror desde CDN.
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/mode/sql/sql.min.js'), true);

// Lista de temas de CodeMirror.
$themes = [
    '3024-day', '3024-night', 'abbott', 'abcdef', 'ambiance', 'ayu-dark', 'ayu-mirage', 
    'base16-dark', 'base16-light', 'bespin', 'blackboard', 'cobalt', 'colorforth', 
    'darcula', 'dracula', 'duotone-dark', 'duotone-light', 'eclipse', 'elegant', 
    'erlang-dark', 'gruvbox-dark', 'hopscotch', 'icecoder', 'idea', 'isotope', 
    'lesser-dark', 'liquibyte', 'lucario', 'material', 'material-darker', 
    'material-palenight', 'material-ocean', 'mbo', 'mdn-like', 'midnight', 'monokai', 
    'moxer', 'neat', 'neo', 'night', 'nord', 'oceanic-next', 'panda-syntax', 
    'paraiso-dark', 'paraiso-light', 'pastel-on-dark', 'railscasts', 'rubyblue', 
    'seti', 'shadowfox', 'solarized', 'ssms', 'the-matrix', 'tomorrow-night-bright', 
    'tomorrow-night-eighties', 'ttcn', 'twilight', 'vibrant-ink', 'xq-dark', 'xq-light', 
    'yeti', 'yonce', 'zenburn'
];

// Enlazar cada archivo CSS de tema de CodeMirror desde CDN.
foreach ($themes as $theme) {
    $PAGE->requires->css(new moodle_url("https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/theme/$theme.min.css"));
}

// Incluir CSS y JS de CodeMirror para el modo de pantalla completa.
$PAGE->requires->css(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/display/fullscreen.min.css'));
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/display/fullscreen.min.js'), true);

// Incluir CSS y JS de CodeMirror para las sugerencias (hints).
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/hint/anyword-hint.min.js'), true);
$PAGE->requires->css(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/hint/show-hint.min.css'));
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/hint/show-hint.min.js'), true);
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/hint/sql-hint.min.js'), true);

// Incluir JS de CodeMirror para el emparejamiento de corchetes.
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/edit/matchbrackets.min.js'), true);

// Incluir JS de CodeMirror para el cierre automático de corchetes.
$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/edit/closebrackets.min.js'), true);

// Obtener la lista de preguntas del quiz desde la base de datos (pregunta SQL).
$quiz_questions = sqlab_get_quiz_questions($sqlab->quizid);

// Redirigir y mostrar notificación de error si no se encuentran preguntas.
if (empty($quiz_questions)) {
    \core\notification::error(get_string('noquestionsfound', 'sqlab'));
    redirect(new moodle_url('/mod/sqlab/view.php', ['id' => $cmid]));
    exit;
}

// Validar y ajustar el número de página actual.
$page = max(0, min($page, count($quiz_questions) - 1));

// Mostrar el encabezado de la página de Moodle.
echo $OUTPUT->header();

// Obtener y mostrar la pregunta actual.
$current_question = $quiz_questions[$page];
$question_id = 'question-' . $current_question['questionid'];

// Formatear la calificación de la pregunta a dos decimales.
$formatted_grade = number_format((float) $current_question['questiongrade'], 2);

// Iniciar un contenedor div con estilo flex.
echo ' <div style="display: flex;">';

// Crear barra de navegación vertical con íconos
// Barra de navegación vertical con las mismas funciones que la barra horizontal
echo "
<div class='navbar-v-container' style='width: 50px; display: flex; flex-direction: column; align-items: center; padding: 10px 0;'>
    <nav class='navbar navbar-expand-lg navbar-light bg-light'>
         <div class='collapse navbar-collapse' id='navbarNav' >
            <ul class='navbar-nav ml-auto' style='width: 50px; display: flex; flex-direction: column; align-items: center; padding: 10px 0;'>";

            //Tools
            echo "
            <li class='nav-item'>
            <a class='nav-link' href='#' style='margin-right: 15px;'><i class='fas fa-tools'></i> </a>
            </li>";
            
            // Menú desplegable: Snippets
            echo "
            <li class='nav-item dropdown'>
            <a class='nav-link dropdown-toggle' href='#' id='snippetsDropdown' role='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
                <i class='fas fa-code'></i>
            </a>
            <div class='dropdown-menu' aria-labelledby='snippetsDropdown'>";
            // Recorrer los snippets y generar opciones en el menú desplegable
            foreach ($snippets as $snippetName => $snippetCode) {
                echo "<a class='dropdown-item' href='#' onclick='insertSnippet(event, `" . addslashes($snippetCode) . "`)'>" . $snippetName . "</a>";
            }
            echo "</div> </li>";

            // Menú desplegable: Configuración (temas, tamaño de fuente, idioma)
            echo "
            <li class='nav-item dropdown'>
                <a class='nav-link dropdown-toggle' href='#' id='settingsDropdown' role='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
                    <i class='fas fa-cog'></i> 
                </a>
                <div class='dropdown-menu' aria-labelledby='settingsDropdown'>
                
                    <!-- Selección de temas para el editor -->
                    <div class='dropdown-item'>
                        <a class='dropdown-item' href='#'>" . get_string('editorthemes', 'sqlab') . "</a>
                        <select id='themeSelector' class='form-control' onmouseover='openSelect(this)' onmouseout='closeSelect(this)' onchange='changeEditorTheme(this.value)'>
                            <option value='' disabled selected>" . get_string('editorthemes', 'sqlab') . "</option>";
                            foreach ($themes as $theme) {
                                echo "<option value='" . $theme . "'>" . $theme . "</option>";
                            }
            echo "
                        </select>
                    </div>

                    <!-- Selector para cambiar el tamaño de fuente -->
                    <div class='dropdown-item'>
                        <a class='dropdown-item' href='#'>" . get_string('fontsize', 'sqlab') . "</a>
                        <select id='fontSizeSelector' onmouseover='openSelect(this)' onmouseout='closeSelect(this)' onchange='changeFontSize(this.value)'>
                            <option value='' disabled selected>" . get_string('fontsize', 'sqlab') . "</option>";
                            for ($i = 10; $i <= 30; $i += 2) {
                                echo "<option value='" . $i . "px'>" . $i . "</option>";
                            }
            echo "
                        </select>
                    </div>

                    <!-- Selector de idioma -->
                    <div class='dropdown-item'>
                        <a class='dropdown-item' href='#'>" . get_string('selectlanguage', 'sqlab') . "</a>
                        <select id='language-selector' onmouseover='openSelect(this)' onmouseout='closeSelect(this)' onchange='changeLanguage()'>
                            <option value='' disabled selected>" . get_string('selectlanguage', 'sqlab') . "</option>";
                            foreach ($languages as $langcode => $langname) {
                                echo "<option value='" . $langcode . "'>" . $langname . "</option>";
                            }
            echo "
                        </select>
                    </div>
                </div>
            </li>";

            // Elemento de ayuda en la barra de navegación
            echo "
            <li class='nav-item'>
                <a class='nav-link' href='#' style='margin-right: 15px;'><i class='fas fa-question-circle'></i></a>
            </li>";

            echo "</ul>
            </div>
    </nav>
</div>
";

echo'
<!-- JavaScript Functions -->
<script>
    // Función para abrir las herramientas
    function openTools() {
        console.log("Herramientas abiertas");
    }

    // Función para insertar snippet
    function insertSnippet(event, snippetCode) {
        event.preventDefault();
        var editor = document.querySelector(".CodeMirror").CodeMirror;
        if (editor) {
            editor.replaceSelection(snippetCode);
        }
    }

    // Función para cambiar el tema del editor
    function changeEditorTheme(theme) {
        var editor = document.querySelector(".CodeMirror").CodeMirror;
        if (editor) {
            editor.setOption("theme", theme);
        }
    }

    // Función para cambiar el tamaño de fuente
    function changeFontSize(size) {
        var editor = document.querySelector(".CodeMirror").CodeMirror;
        if (editor) {
            editor.getWrapperElement().style.fontSize = size;
            editor.refresh();
        }
    }

    // Función para cambiar el idioma
    function changeLanguage() {
        var selectedLanguage = document.getElementById("languageSelector").value;
        console.log("Idioma cambiado a: " + selectedLanguage);
    }

    // Función para abrir ayuda
    function openHelp() {
        console.log("Ayuda abierta");
    }

    // Mostrar/ocultar los menús desplegables de manera independiente
    document.getElementById("snippetsDropdownToggle").addEventListener("click", function() {
        document.getElementById("snippetsDropdownMenu").classList.toggle("show");
        document.getElementById("settingsDropdownMenu").classList.remove("show"); // Asegurarse de que solo uno esté abierto
    });

    document.getElementById("settingsDropdownToggle").addEventListener("click", function() {
        document.getElementById("settingsDropdownMenu").classList.toggle("show");
        document.getElementById("snippetsDropdownMenu").classList.remove("show"); // Asegurarse de que solo uno esté abierto
    });

</script>
';


// Inicio del contenedor principal
echo '<link rel="stylesheet" type="text/css" href="styles/style.css">';
echo '<div class="main-container">';

    
// Subcontenedor izquierdo (donde puedes poner, por ejemplo, el editor de SQL)
echo '<div class="left-container">';
echo '<h2>Editor de SQL</h2>';
// Editor de CodeMirror
echo "
<div class='code-editor-container'>
    <textarea id='myCodeMirror' class='form-control' data-question-id='" . $question_id . "' style='width: 100%; height: 300px;'></textarea>
</div>";

// Botones de acciones del editor (Ejecutar y Evaluar)
echo "
<div class='code-editor-actions'>
    <button id='executeSqlButton' type='button' class='btn btn-primary'>" . get_string('runcode', 'sqlab') . "</button>
    <button id='evaluateSqlButton' type='button' class='btn btn-success'>" . get_string('evaluatecode', 'sqlab') . "</button>
    <button id='infoButton' type='button' class='btn btn-secondary'><i class='fa fa-question'></i></button>
    <div id='infoText'>" . get_string('beforefinish', 'sqlab') . "</div>
</div>";


// Div para mostrar los resultados de la ejecución de SQL
echo "
<div id='sqlQueryResults' class='sql-query-results'></div>
";
echo '</div>';

// Separador
echo '<div class="separator"></div>';

// Subcontenedor derecho (donde puedes mostrar los resultados o ayudas)
echo '<div class="right-container">';

// Crear las pestañas de navegación
echo '
<ul class="nav nav-tabs" id="myTab" role="tablist">
  <li class="nav-item">
    <a class="nav-link active" id="description-tab" data-toggle="tab" href="#description" role="tab" aria-controls="description" aria-selected="true">Descripción</a>
  </li>

  <li class="nav-item">
    <a class="nav-link" id="help-tab" data-toggle="tab" href="#help" role="tab" aria-controls="help" aria-selected="false">Ayuda</a>
  </li>
</ul>
';

// Crear el contenido de las pestañas
echo '
<div class="tab-content" id="myTabContent">
  <!-- Contenido de la pestaña Descripción -->
  <div class="tab-pane fade show active" id="description" role="tabpanel" aria-labelledby="description-tab">
    ' . format_text($current_question['statement'], FORMAT_MOODLE, $formatoptions) . '
  </div>
  
  <!-- Contenido de la pestaña Ayuda, que incluye los tres desplegables -->
  <div class="tab-pane fade" id="help" role="tabpanel" aria-labelledby="help-tab">
    <h3>Ayuda</h3>
    <!-- Sección de Expected Results -->
    <div class="accordion-container">
      <h2 class="accordion-title">' . get_string('sqlresults', 'sqlab') . '</h2>
      <div class="accordion-content">
        <div id="resultDataContainer" class="sql-query-results"></div>
      </div>
    </div>

    <!-- Sección de Related Concepts -->
    <div class="accordion-container">
      <h2 class="accordion-title">' . get_string('relatedconcepts', 'sqlab') . '</h2>
      <div class="accordion-content">
        ' . format_text($current_question['relatedconcepts'], FORMAT_HTML, $formatoptions) . '
      </div>
    </div>

    <!-- Sección de Hints -->
    <div class="accordion-container">
      <h2 class="accordion-title">' . get_string('hints', 'sqlab') . '</h2>
      <div class="accordion-content">
        ' . format_text($current_question['code'], FORMAT_HTML, $formatoptions) . '
      </div>
    </div>
  </div>
</div>
';

// Separador
echo '<div class="separator"></div>';

echo '</div>';


// Cerrar el contenedor principal
echo '</div>';







// Divs ocultos para uso en JS.
echo " <div id='resultDataSql' style='display:none;'>" . htmlspecialchars($current_question['resultdata'], ENT_QUOTES, 'UTF-8') . "</div>";
echo " <div id='resultDataUserId' style='display:none;'>" . $USER->id . "</div>";
echo " <div id='resultDataSchema' style='display:none;'>" . htmlspecialchars(schema_manager::format_activity_name($sqlab->name), ENT_QUOTES, 'UTF-8') . "</div>";
echo ' <div id="attemptIdContainer" style="display:none;">' . json_encode($attemptid) . '</div>';
echo ' <div id="questionIdContainer" style="display:none;">' . json_encode($current_question['questionid']) . '</div>';
echo ' <div id="cmidContainer" style="display:none;">' . json_encode($cmid) . '</div>';

// Scripts JS para funcionalidad adicional.
echo '<script src="' . new moodle_url('/mod/sqlab/js/codemirror_config.js') . '"></script>';
echo '<script src="' . new moodle_url('/mod/sqlab/js/localstorage.js') . '"></script>';
echo '<script src="' . new moodle_url('/mod/sqlab/js/sql_executor.js') . '"></script>';
echo '<script src="' . new moodle_url('/mod/sqlab/js/accordion_display.js') . '"></script>';
echo '<script src="' . new moodle_url('/mod/sqlab/js/grade_manager.js') . '"></script>';
echo '<script src="' . new moodle_url('/mod/sqlab/js/context_resultdata_executor.js') . '"></script>';
echo '<script src="' . new moodle_url('/mod/sqlab/js/info_button.js') . '"></script>';

// Mostrar el pie de página de Moodle.
echo $OUTPUT->footer();
?>
