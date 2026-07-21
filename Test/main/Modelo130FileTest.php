<?php
/**
 * This file is part of Modelo130 plugin for FacturaScripts
 * Copyright (C) 2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
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

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\Modelo130\Lib\Modelo130Export;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests del fichero de presentación AEAT del Modelo 130.
 *
 * Verifican que el fichero generado por Modelo130Export respeta el diseño
 * de registro oficial publicado por la AEAT (DR130e15v12, Orden HAP/258/2015,
 * ejercicios 2019 y siguientes): sobre <T1300AAAAPP0000> + zona <AUX>
 * (328 posiciones), página 1 <T13001000> de 600 posiciones y cierre
 * </T1300AAAAPP0000>.
 *
 * @author Abderrahim Darghal Belkacemi
 */
final class Modelo130FileTest extends TestCase
{
    /** Posición (base 0) donde empieza la página 1 dentro del fichero. */
    const PAGE_OFFSET = 328;

    /** Posición (base 0) de la primera casilla dentro de la página. */
    const BOXES_OFFSET = 108;

    public function testCompleteFilePositiveResult(): void
    {
        $expected = '<T130020262T0000>'
            . '<AUX>' . str_repeat(' ', 300) . '</AUX>'
            . '<T13001000>'
            . ' '                                       // página complementaria: en blanco
            . 'I'                                       // tipo de declaración: ingreso
            . '12345678Z'                               // NIF
            . 'PEREZ GARCIA' . str_repeat(' ', 48)      // apellidos (60)
            . 'MARIA' . str_repeat(' ', 15)             // nombre (20)
            . '2026'                                    // ejercicio
            . '2T'                                      // período
            . '00000000002500050'                       // [01] ingresos 25.000,50
            . '00000000001105027'                       // [02] gastos 10.000,25 + 1.050,02
            . '00000000001395023'                       // [03] rendimiento neto 13.950,23
            . '00000000000279005'                       // [04] 20% de [03] = 2.790,05
            . '00000000000050000'                       // [05] trimestres anteriores 500,00
            . '00000000000120075'                       // [06] retenciones 1.200,75
            . '00000000000108930'                       // [07] = [04]-[05]-[06] = 1.089,30
            . '00000000000000000'                       // [08] agrícolas: no soportado
            . '00000000000000000'                       // [09]
            . '00000000000000000'                       // [10]
            . '00000000000000000'                       // [11]
            . '00000000000108930'                       // [12] = [07]+[11]
            . '00000000000000000'                       // [13]
            . '00000000000108930'                       // [14] = [12]-[13]
            . '00000000000000000'                       // [15]
            . '00000000000000000'                       // [16]
            . '00000000000108930'                       // [17] = [14]-[15]-[16]
            . '00000000000000000'                       // [18]
            . '00000000000108930'                       // [19] resultado
            . ' '                                       // declaración complementaria
            . str_repeat(' ', 13)                       // justificante anterior
            . str_repeat(' ', 34)                       // IBAN
            . str_repeat(' ', 96)                       // reservado AEAT
            . str_repeat(' ', 13)                       // sello electrónico
            . '</T13001000>'
            . '</T130020262T0000>';

        $content = Modelo130Export::generate($this->sampleResult(), $this->sampleCompany(), 'T2', '2026');
        $this->assertSame($expected, $content);
    }

    public function testCompleteFileNegativeResult(): void
    {
        $result = [
            'taxbaseIngresos' => 1000.00,
            'taxbaseGastos' => 5000.00,
            'gastosJustificacion' => 0.0,
            'afterdeduct' => 0.0,
            'positivosTrimestres' => 0.0,
            'taxbaseRetenciones' => 100.00,
        ];

        $expected = '<T130020263T0000>'
            . '<AUX>' . str_repeat(' ', 300) . '</AUX>'
            . '<T13001000>'
            . ' '                                       // página complementaria: en blanco
            . 'N'                                       // tipo de declaración: negativa
            . '12345678Z'                               // NIF
            . 'PEREZ GARCIA' . str_repeat(' ', 48)      // apellidos (60)
            . 'MARIA' . str_repeat(' ', 15)             // nombre (20)
            . '2026'                                    // ejercicio
            . '3T'                                      // período
            . '00000000000100000'                       // [01] ingresos 1.000,00
            . '00000000000500000'                       // [02] gastos 5.000,00
            . 'N0000000000400000'                       // [03] rendimiento neto -4.000,00
            . '00000000000000000'                       // [04] no puede ser negativa
            . '00000000000000000'                       // [05]
            . '00000000000010000'                       // [06] retenciones 100,00
            . 'N0000000000010000'                       // [07] = -100,00
            . '00000000000000000'                       // [08]
            . '00000000000000000'                       // [09]
            . '00000000000000000'                       // [10]
            . '00000000000000000'                       // [11]
            . '00000000000000000'                       // [12] si [07]+[11] es negativa: cero
            . '00000000000000000'                       // [13]
            . '00000000000000000'                       // [14]
            . '00000000000000000'                       // [15]
            . '00000000000000000'                       // [16]
            . '00000000000000000'                       // [17]
            . '00000000000000000'                       // [18]
            . '00000000000000000'                       // [19] resultado cero
            . ' '                                       // declaración complementaria
            . str_repeat(' ', 13)                       // justificante anterior
            . str_repeat(' ', 34)                       // IBAN
            . str_repeat(' ', 96)                       // reservado AEAT
            . str_repeat(' ', 13)                       // sello electrónico
            . '</T13001000>'
            . '</T130020263T0000>';

        $content = Modelo130Export::generate($result, $this->sampleCompany(), 'T3', '2026');
        $this->assertSame($expected, $content);
    }

    public function testFileStructure(): void
    {
        $content = Modelo130Export::generate($this->sampleResult(), $this->sampleCompany(), 'T2', '2026');

        // longitud total exacta y sin saltos de línea
        $this->assertSame(Modelo130Export::FILE_LENGTH, strlen($content));
        $this->assertStringNotContainsString("\n", $content);
        $this->assertStringNotContainsString("\r", $content);

        // sobre: apertura, zona <AUX> reservada en blanco y cierre
        $this->assertSame('<T130020262T0000>', substr($content, 0, 17));
        $this->assertSame('<AUX>', substr($content, 17, 5));
        $this->assertSame(str_repeat(' ', 300), substr($content, 22, 300));
        $this->assertSame('</AUX>', substr($content, 322, 6));
        $this->assertSame('</T130020262T0000>', substr($content, -18));

        // página 1: 600 posiciones con sus constantes de inicio y fin
        $page = substr($content, self::PAGE_OFFSET, 600);
        $this->assertSame(600, strlen($page));
        $this->assertSame('<T13001000>', substr($page, 0, 11));
        $this->assertSame('</T13001000>', substr($page, 588, 12));

        // posiciones 432-588 de la página (complementaria, justificante,
        // IBAN, reservado AEAT y sello electrónico) en blanco
        $this->assertSame(str_repeat(' ', 157), substr($page, 431, 157));
    }

    public function testFileDeclarantAndAccrual(): void
    {
        $content = Modelo130Export::generate($this->sampleResult(), $this->sampleCompany(), 'T2', '2026');
        $page = substr($content, self::PAGE_OFFSET, 600);

        // indicador de página complementaria en blanco
        $this->assertSame(' ', $page[11]);

        // tipo de declaración I: el resultado de la muestra es positivo
        $this->assertSame('I', $page[12]);

        // declarante: NIF (9), apellidos (60) y nombre (20), en mayúsculas
        // sin acentos; la primera palabra del nombre de la empresa se toma
        // como nombre y el resto como apellidos
        $this->assertSame('12345678Z', substr($page, 13, 9));
        $this->assertSame(str_pad('PEREZ GARCIA', 60), substr($page, 22, 60));
        $this->assertSame(str_pad('MARIA', 20), substr($page, 82, 20));

        // devengo: ejercicio (4) y período (2)
        $this->assertSame('2026', substr($page, 102, 4));
        $this->assertSame('2T', substr($page, 106, 2));
    }

    public function testFileBoxesNegativeResult(): void
    {
        $result = [
            'taxbaseIngresos' => 1000.00,
            'taxbaseGastos' => 5000.00,
            'gastosJustificacion' => 0.0,
            'afterdeduct' => 0.0,
            'positivosTrimestres' => 0.0,
            'taxbaseRetenciones' => 100.00,
        ];

        $content = Modelo130Export::generate($result, $this->sampleCompany(), 'T3', '2026');
        $page = substr($content, self::PAGE_OFFSET, 600);

        // los importes negativos llevan una N en la primera posición del campo
        $this->assertSame('N0000000000400000', $this->box($page, 3));
        $this->assertSame('N0000000000010000', $this->box($page, 7));

        // [04] no puede ser negativa
        $this->assertSame(str_repeat('0', 17), $this->box($page, 4));

        // si la suma de [07] y [11] es negativa, en [12] se consigna cero
        $this->assertSame(str_repeat('0', 17), $this->box($page, 12));
        $this->assertSame(str_repeat('0', 17), $this->box($page, 19));

        // resultado cero: tipo de declaración N (negativa)
        $this->assertSame('N', $page[12]);
    }

    public function testDeclarationType(): void
    {
        // I (ingreso), N (negativa) y B (a deducir)
        $this->assertSame('I', Modelo130Export::getDeclarationType(100.0));
        $this->assertSame('N', Modelo130Export::getDeclarationType(0.0));
        $this->assertSame('B', Modelo130Export::getDeclarationType(-100.0));
    }

    public function testFormatNumeric(): void
    {
        // 17 posiciones: 15 enteros + 2 decimales, ceros a la izquierda
        $this->assertSame('00000000000000000', Modelo130Export::formatNumeric(0.0));
        $this->assertSame('00000000000012345', Modelo130Export::formatNumeric(123.45));
        $this->assertSame('00000000000000001', Modelo130Export::formatNumeric(0.01));

        // redondeo a céntimos
        $this->assertSame('00000000000010000', Modelo130Export::formatNumeric(99.999));

        // los campos sin signo nunca llevan N aunque el valor sea negativo
        $this->assertSame('00000000000012345', Modelo130Export::formatNumeric(-123.45));

        // los campos con signo llevan N en la primera posición si son negativos
        $this->assertSame('N0000000000012345', Modelo130Export::formatNumeric(-123.45, true));
        $this->assertSame('00000000000012345', Modelo130Export::formatNumeric(123.45, true));
        $this->assertSame(17, strlen(Modelo130Export::formatNumeric(-123.45, true)));
    }

    public function testFormatAlphanumeric(): void
    {
        // mayúsculas, sin acentos, alineado a la izquierda con blancos
        $this->assertSame('MARIA PEREZ         ', Modelo130Export::formatAlphanumeric('María Pérez', 20));

        // solo se admiten letras, números y blancos
        $this->assertSame('ACME SL   ', Modelo130Export::formatAlphanumeric('Acme, S.L.', 10));

        // truncado a la longitud del campo
        $this->assertSame('ABCDE', Modelo130Export::formatAlphanumeric('abcdefghij', 5));
    }

    public function testFormatNif(): void
    {
        $this->assertSame('12345678Z', Modelo130Export::formatNif('12345678-z'));
        $this->assertSame('B12345678', Modelo130Export::formatNif(' b 12.345.678 '));
        $this->assertSame('', Modelo130Export::formatNif(''));
    }

    public function testSplitDeclarantName(): void
    {
        // la primera palabra es el nombre y el resto los apellidos
        $this->assertSame(['Pérez García', 'María'], Modelo130Export::splitDeclarantName('María Pérez García'));
        $this->assertSame(['Pérez', 'María'], Modelo130Export::splitDeclarantName('María Pérez'));

        // con una sola palabra, todo va a apellidos
        $this->assertSame(['ACME', ''], Modelo130Export::splitDeclarantName('ACME'));
        $this->assertSame(['', ''], Modelo130Export::splitDeclarantName('   '));
    }

    public function testGetPeriodNumber(): void
    {
        foreach (['T1' => '1T', 'T2' => '2T', 'T3' => '3T', 'T4' => '4T'] as $period => $expected) {
            $this->assertSame($expected, Modelo130Export::getPeriodNumber($period));
        }
    }

    /**
     * Devuelve la casilla [n] de la página: 17 posiciones desde la 109.
     */
    private function box(string $page, int $num): string
    {
        return substr($page, self::BOXES_OFFSET + ($num - 1) * 17, 17);
    }

    private function sampleCompany(): Empresa
    {
        // se instancia sin constructor para no requerir conexión a base de datos
        $empresa = (new ReflectionClass(Empresa::class))->newInstanceWithoutConstructor();
        $empresa->cifnif = '12345678Z';
        $empresa->nombre = 'María Pérez García';

        return $empresa;
    }

    private function sampleResult(): array
    {
        return [
            'taxbaseIngresos' => 25000.50,
            'taxbaseGastos' => 10000.25,
            'gastosJustificacion' => 1050.02,
            'afterdeduct' => 2790.05,
            'positivosTrimestres' => 500.00,
            'taxbaseRetenciones' => 1200.75,
        ];
    }
}
