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

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Plugins\Modelo130\Lib\Modelo130;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

/**
 * Tests para la clase estática Modelo130\Lib\Modelo130.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
final class Modelo130Test extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
    }

    public function testGenerate(): void
    {
        $ejercicios = (new Ejercicio())->all([], ['codejercicio' => 'DESC'], 0, 1);
        $this->assertNotEmpty($ejercicios, 'No hay ningún ejercicio en la base de datos');

        $ejercicio = $ejercicios[0];
        $codejercicio = $ejercicio->codejercicio;
        $resultado = Modelo130::generate($codejercicio, 'T1');

        $this->assertIsArray($resultado, 'generate() debe devolver un array');
        $this->assertNotEmpty($resultado, 'generate() no debe devolver un array vacío para un ejercicio válido');

        $clavesEsperadas = [
            'exercise',
            'period',
            'idempresa',
            'sales',
            'purchases',
            'accountingEntries',
            'applyGastosJustificacion',
            'todeduct',
            'gastosJustificacionPct',
            'taxbaseIngresos',
            'taxbaseRetenciones',
            'taxbaseGastos',
            'taxbase',
            'gastosJustificacion',
            'afterdeduct',
            'positivosTrimestres',
            'result',
        ];

        foreach ($clavesEsperadas as $clave) {
            $this->assertArrayHasKey($clave, $resultado, "El resultado debe contener la clave '$clave'");
        }

        $this->assertInstanceOf(Ejercicio::class, $resultado['exercise']);
        $this->assertSame('T1', $resultado['period']);
        $this->assertSame($codejercicio, $resultado['exercise']->codejercicio);
        $this->assertIsArray($resultado['sales']);
        $this->assertIsArray($resultado['purchases']);
        $this->assertIsArray($resultado['accountingEntries']);

        $resultadoVacio = Modelo130::generate('EJERCICIO_INEXISTENTE_XYZ', 'T1');
        $this->assertIsArray($resultadoVacio);
        $this->assertEmpty($resultadoVacio);
    }

    public function testGenerateEntries(): void
    {
        $ejercicios = (new Ejercicio())->all([], ['codejercicio' => 'DESC'], 0, 1);
        $this->assertNotEmpty($ejercicios, 'No hay ningún ejercicio en la base de datos');

        $ejercicio = $ejercicios[0];
        $codejercicio = $ejercicio->codejercicio;
        $idempresa = $ejercicio->idempresa;

        $formasPago = (new FormaPago())->all([], [], 0, 1);
        $paymentMethodId = empty($formasPago) ? null : $formasPago[0]->id();

        $periodo = 'T1';
        $importe = 100.0;
        $fecha = date('Y-m-d');

        $creado = Modelo130::generateEntries(
            $idempresa,
            $codejercicio,
            $periodo,
            $fecha,
            $importe,
            $paymentMethodId
        );
        $this->assertTrue($creado, 'generateEntries() debe devolver true al crear el asiento por primera vez');

        $concepto = Tools::trans('acc-concept-irpf-130', ['%period%' => $periodo]);
        $asiento = new Asiento();
        $encontrado = $asiento->loadWhere([
            Where::eq('codejercicio', $codejercicio),
            Where::eq('concepto', $concepto),
        ]);

        $this->assertTrue($encontrado, 'El asiento debe existir en la base de datos tras generateEntries()');
        $this->assertEquals($importe, $asiento->importe, 'El importe del asiento debe ser 100.0');

        $partidas = (new Partida())->all([
            Where::eq('idasiento', $asiento->idasiento),
        ], ['orden' => 'ASC']);

        $this->assertCount(2, $partidas, 'El asiento debe contener dos partidas');
        $this->assertSame('4730000000', $partidas[0]->codsubcuenta);
        $this->assertEquals($importe, $partidas[0]->debe);
        $this->assertEquals(0.0, (float)$partidas[0]->haber);
        $this->assertEquals($importe, $partidas[1]->haber);

        $duplicado = Modelo130::generateEntries(
            $idempresa,
            $codejercicio,
            $periodo,
            $fecha,
            $importe,
            $paymentMethodId
        );
        $this->assertFalse($duplicado, 'generateEntries() debe devolver false si ya existe el asiento');

        $this->assertTrue($asiento->delete(), 'No se pudo eliminar el asiento de prueba');
    }

    public function testCalcGastosJustificacion(): void
    {
        $this->assertSame(0.0, Modelo130::calcGastosJustificacion(10000.0, false, 7.0));
        $this->assertSame(0.0, Modelo130::calcGastosJustificacion(0.0, true, 7.0));
        $this->assertSame(0.0, Modelo130::calcGastosJustificacion(-500.0, true, 7.0));
        $this->assertSame(700.0, Modelo130::calcGastosJustificacion(10000.0, true, 7.0));
        $this->assertSame(500.0, Modelo130::calcGastosJustificacion(10000.0, true, 5.0));
        $this->assertSame(70.35, Modelo130::calcGastosJustificacion(1005.0, true, 7.0));
        $this->assertSame(2000.0, Modelo130::calcGastosJustificacion(40000.0, true, 7.0));
        $this->assertSame(2000.0, Modelo130::calcGastosJustificacion(100000.0, true, 7.0));
        $this->assertSame(1995.0, Modelo130::calcGastosJustificacion(28500.0, true, 7.0));
        $this->assertSame(2000.0, Modelo130::LIMITE_GASTOS_JUSTIFICACION);
    }

    public function testCalcAfterDeduct(): void
    {
        $this->assertSame(2000.0, Modelo130::calcAfterDeduct(10000.0, 0.0, 20.0));
        $this->assertSame(0.0, Modelo130::calcAfterDeduct(-500.0, 0.0, 20.0));
        $this->assertSame(0.0, Modelo130::calcAfterDeduct(0.0, 0.0, 20.0));
        $this->assertSame(1860.0, Modelo130::calcAfterDeduct(10000.0, 700.0, 20.0));
    }

    public function testCalcResult(): void
    {
        $this->assertSame(1200.0, Modelo130::calcResult(2000.0, 500.0, 300.0));
        $this->assertSame(0.0, Modelo130::calcResult(500.0, 400.0, 300.0));
        $this->assertSame(0.0, Modelo130::calcResult(100.0, 50.0, 50.0));
        $this->assertSame(33.33, Modelo130::calcResult(100.005, 33.33, 33.345));
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
