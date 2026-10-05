<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

return [
    // Translation resources.
    'Translation directory "{directory}" does not exist.' =>
        'El directorio de traducciones "{directory}" no existe.',
    'Translation file "{file}" does not follow the "domain.locale.format" naming convention.' =>
        'El archivo de traducciones "{file}" no sigue la convención de nombre "dominio.locale.formato".',
    'Unrecognized translation file extension "{extension}" for file "{file}". Supported extensions: {supported}.' =>
        'Extensión de archivo de traducciones no reconocida "{extension}" para el archivo "{file}". Extensiones soportadas: {supported}.',

    // Messages of the exceptions.
    'Message array cannot be empty.' =>
        'El arreglo del mensaje no puede estar vacío.',
    'First element of message array must be a string.' =>
        'El primer elemento del arreglo del mensaje debe ser un string.',

    // Lint.
    'The method {class}::{method} does not exist.' =>
        'El método {class}::{method} no existe.',
    'The method {class}::{method} has no argument {argument}.' =>
        'El método {class}::{method} no tiene el argumento {argument}.',
    'The file {file} does not exist.' =>
        'El archivo {file} no existe.',
    'The directory {directory} does not exist.' =>
        'El directorio {directory} no existe.',
    'The translatable value of the exception {class} is not a message: it is a {type}.' =>
        'El valor traducible de la excepción {class} no es un mensaje: es un {type}.',
];
