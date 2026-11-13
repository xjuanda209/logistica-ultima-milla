<?php

namespace App\Modules\Asistente\IA;

use RuntimeException;

/**
 * El proveedor de IA no respondió: falta la clave, error de red, más de 30 s, límite de
 * peticiones (429) o respuesta sin texto. El mensaje es para el log, no para la persona:
 * a ella siempre se le dice "El asistente no está disponible".
 */
class IaNoDisponible extends RuntimeException {}
