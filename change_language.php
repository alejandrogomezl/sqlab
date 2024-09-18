<?php
require_once('../../config.php');

$selectedLang = required_param('lang', PARAM_ALPHANUM);

// Cambiar el idioma del usuario
global $USER;
$USER->lang = $selectedLang;
$_SESSION['lang'] = $selectedLang;

// Respuesta de éxito
echo json_encode(['status' => 'success']);