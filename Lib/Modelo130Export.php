<?php
/**
 * This file is part of Modelo130 plugin for FacturaScripts
 * Copyright (C) 2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\Modelo130\Lib;

use FacturaScripts\Dinamic\Model\Empresa;

/**
 * Genera el fichero de presentación del Modelo 130 según el diseño de
 * registro oficial de la AEAT (DR130e15v12, Orden HAP/258/2015, ejercicios
 * 2019 y siguientes): sobre <T1300AAAAPP0000> + zona <AUX> (328 posiciones),
 * página 1 <T13001000> de 600 posiciones y cierre </T1300AAAAPP0000>.
 *
 * @author Abderrahim Darghal Belkacemi
 */
class Modelo130Export
{
    /** Longitud total del fichero: 328 de sobre + 600 de página + 18 de cierre. */
    const FILE_LENGTH = 946;

    /**
     * Genera el contenido completo del fichero a partir del resultado
     * calculado por Modelo130::generate().
     */
    public static function generate(array $result, Empresa $empresa, string $period, string $year): string
    {
        $periodo = static::getPeriodNumber($period);
        $nif = static::formatNif($empresa->cifnif ?? '');

        // registro inicial: <T1300 + ejercicio + período + 0000>
        $content = '<T1300' . $year . $periodo . '0000>';

        // zona <AUX>: 70 blancos reservados + versión del programa (4) + 4 blancos
        // + NIF de la empresa desarrolladora (9) + 213 blancos reservados
        $content .= '<AUX>' . str_repeat(' ', 300) . '</AUX>';

        // contenido de la página 1 (600 posiciones)
        $content .= static::generatePage1($result, $empresa, $nif, $year, $periodo);

        // registro de cierre
        $content .= '</T1300' . $year . $periodo . '0000>';

        return $content;
    }

    /**
     * Tipo de declaración: I (ingreso), N (negativa) o B (resultado a deducir).
     */
    public static function getDeclarationType(float $result): string
    {
        if ($result > 0) {
            return 'I';
        }

        return $result < 0 ? 'B' : 'N';
    }

    public static function getPeriodNumber(string $period): string
    {
        return match ($period) {
            'T2' => '2T',
            'T3' => '3T',
            'T4' => '4T',
            default => '1T',
        };
    }

    public static function formatAlphanumeric(string $value, int $length): string
    {
        // solo se admiten letras, números y blancos, alineados a la izquierda
        $value = strtoupper(static::removeAccents($value));
        $value = preg_replace('/[^A-Z0-9 ]/', '', $value);

        return str_pad(substr($value, 0, $length), $length, ' ', STR_PAD_RIGHT);
    }

    public static function formatNif(string $value): string
    {
        $value = strtoupper(static::removeAccents($value));

        return preg_replace('/[^A-Z0-9]/', '', $value);
    }

    public static function formatNumeric(float $value, bool $signed = false): string
    {
        // 17 posiciones: 15 enteros + 2 decimales, sin separador, con ceros a la izquierda;
        // los importes negativos llevan una N en la primera posición
        $cents = (int)round(abs($value) * 100);
        if ($signed && $value < 0) {
            return 'N' . str_pad((string)$cents, 16, '0', STR_PAD_LEFT);
        }

        return str_pad((string)$cents, 17, '0', STR_PAD_LEFT);
    }

    public static function splitDeclarantName(string $fullName): array
    {
        // el diseño de registro separa apellidos (60) y nombre (20); si el nombre de la
        // empresa tiene varias palabras, la primera se toma como nombre y el resto como apellidos
        $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) < 2) {
            return [trim($fullName), ''];
        }

        $nombre = array_shift($parts);
        return [implode(' ', $parts), $nombre];
    }

    protected static function generatePage1(array $result, Empresa $empresa, string $nif, string $year, string $periodo): string
    {
        // I. Actividades económicas en estimación directa
        $box01 = (float)$result['taxbaseIngresos'];
        $box02 = round((float)$result['taxbaseGastos'] + (float)$result['gastosJustificacion'], 2);
        $box03 = round($box01 - $box02, 2);
        $box04 = (float)$result['afterdeduct'];
        $box05 = max(0.0, (float)$result['positivosTrimestres']);
        $box06 = (float)$result['taxbaseRetenciones'];
        $box07 = round($box04 - $box05 - $box06, 2);

        // II. Actividades agrícolas, ganaderas, forestales y pesqueras (no soportadas)
        $box08 = $box09 = $box10 = $box11 = 0.0;

        // III. Total liquidación: si la suma de [07] y [11] es negativa, se consigna cero
        $box12 = max(0.0, round($box07 + $box11, 2));
        $box13 = 0.0;
        $box14 = round($box12 - $box13, 2);
        $box15 = $box16 = 0.0;
        $box17 = round($box14 - $box15 - $box16, 2);
        $box18 = 0.0;
        $box19 = round($box17 - $box18, 2);

        [$apellidos, $nombre] = static::splitDeclarantName($empresa->nombre ?? '');

        $page = '<T13001000>';

        // indicador de página complementaria (en blanco)
        $page .= ' ';

        // tipo de declaración: I (ingreso), N (negativa) o B (resultado a deducir)
        $page .= static::getDeclarationType($box19);

        // declarante
        $page .= static::formatAlphanumeric($nif, 9);
        $page .= static::formatAlphanumeric($apellidos, 60);
        $page .= static::formatAlphanumeric($nombre, 20);

        // devengo
        $page .= $year;
        $page .= $periodo;

        // liquidación: casillas [01] a [19], 17 posiciones cada una
        $page .= static::formatNumeric($box01);
        $page .= static::formatNumeric($box02);
        $page .= static::formatNumeric($box03, true);
        $page .= static::formatNumeric($box04);
        $page .= static::formatNumeric($box05);
        $page .= static::formatNumeric($box06);
        $page .= static::formatNumeric($box07, true);
        $page .= static::formatNumeric($box08);
        $page .= static::formatNumeric($box09);
        $page .= static::formatNumeric($box10);
        $page .= static::formatNumeric($box11, true);
        $page .= static::formatNumeric($box12);
        $page .= static::formatNumeric($box13);
        $page .= static::formatNumeric($box14, true);
        $page .= static::formatNumeric($box15);
        $page .= static::formatNumeric($box16);
        $page .= static::formatNumeric($box17, true);
        $page .= static::formatNumeric($box18);
        $page .= static::formatNumeric($box19, true);

        // declaración complementaria (blanco), justificante anterior (13) e IBAN (34)
        $page .= ' ';
        $page .= str_repeat(' ', 13);
        $page .= str_repeat(' ', 34);

        // reservado AEAT (96) y sello electrónico (13)
        $page .= str_repeat(' ', 109);

        // indicador de fin de registro
        $page .= '</T13001000>';

        return $page;
    }

    protected static function removeAccents(string $text): string
    {
        $unwanted = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ñ' => 'N', 'Ñ' => 'N', 'ü' => 'u', 'Ü' => 'U', 'ç' => 'C', 'Ç' => 'C'
        ];
        return strtr($text, $unwanted);
    }
}
