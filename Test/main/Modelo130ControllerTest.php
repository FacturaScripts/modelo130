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

use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Cuenta;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Dinamic\Model\Subcuenta130;
use FacturaScripts\Plugins\Modelo130\Controller\Modelo130;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

final class Modelo130ControllerTest extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
    }

    public function testSanitizeSubaccountCodesRemovesEmptyAndDuplicateValues(): void
    {
        $codes = Modelo130TestAccess::sanitizeCodes([
            '6400000000',
            '',
            '  ',
            '6420000000',
            '6400000000',
            '9999.1',
        ]);

        $this->assertSame(['6400000000', '6420000000', '9999.1'], $codes);
    }

    public function testGetSqlValueConditionUsesIsNullForNullValues(): void
    {
        $controller = new Modelo130TestAccess(Modelo130TestAccess::class);

        $this->assertSame('a.idempresa IS NULL', $controller->sqlValueCondition('a.idempresa', null));
    }

    public function testGetSqlValueListQuotesValues(): void
    {
        $controller = new Modelo130TestAccess(Modelo130TestAccess::class);

        $this->assertSame("'6400000000','9999.1'", $controller->sqlValueList(['6400000000', '9999.1']));
    }

    public function testGetDeductibleSubaccountsFiltraPorTipo(): void
    {
        $sub130Deducible = new Subcuenta130();
        $sub130Deducible->codsubcuenta = 'test_deducible';
        $sub130Deducible->tipo = Subcuenta130::TIPO_DEDUCIBLE;
        $this->assertTrue($sub130Deducible->save(), 'cant-save-deducible');

        $sub130Ingreso = new Subcuenta130();
        $sub130Ingreso->codsubcuenta = 'test_ingreso';
        $sub130Ingreso->tipo = Subcuenta130::TIPO_INGRESO;
        $this->assertTrue($sub130Ingreso->save(), 'cant-save-ingreso');

        $controller = new Modelo130TestAccess(Modelo130TestAccess::class);
        $codes = array_map(fn($s) => $s->codsubcuenta, $controller->getDeductibleSubaccounts());

        $this->assertContains('test_deducible', $codes, 'deducible-should-be-in-list');
        $this->assertNotContains('test_ingreso', $codes, 'ingreso-should-not-be-in-deductible-list');

        $this->assertTrue($sub130Deducible->delete());
        $this->assertTrue($sub130Ingreso->delete());
    }

    public function testGetIncomeSubaccountsFiltraPorTipo(): void
    {
        $sub130Deducible = new Subcuenta130();
        $sub130Deducible->codsubcuenta = 'test2_deducible';
        $sub130Deducible->tipo = Subcuenta130::TIPO_DEDUCIBLE;
        $this->assertTrue($sub130Deducible->save(), 'cant-save-deducible');

        $sub130Ingreso = new Subcuenta130();
        $sub130Ingreso->codsubcuenta = 'test2_ingreso';
        $sub130Ingreso->tipo = Subcuenta130::TIPO_INGRESO;
        $this->assertTrue($sub130Ingreso->save(), 'cant-save-ingreso');

        $controller = new Modelo130TestAccess(Modelo130TestAccess::class);
        $codes = array_map(fn($s) => $s->codsubcuenta, $controller->getIncomeSubaccounts());

        $this->assertContains('test2_ingreso', $codes, 'ingreso-should-be-in-list');
        $this->assertNotContains('test2_deducible', $codes, 'deducible-should-not-be-in-income-list');

        $this->assertTrue($sub130Deducible->delete());
        $this->assertTrue($sub130Ingreso->delete());
    }

    public function testIncomeEntriesSeAcumulanEnTaxbaseIngresos(): void
    {
        // conseguir un ejercicio existente (all() garantiza que el codejercicio está en BD)
        $ejercicios = (new Ejercicio())->all([], ['codejercicio' => 'DESC'], 0, 1);
        $this->assertNotEmpty($ejercicios, 'no-exercise-found');
        $ejercicio = $ejercicios[0];

        // crear cuenta y subcuenta de ingreso (9996 para no colisionar con el plan contable)
        $account = new Cuenta();
        $account->codcuenta = '9996';
        $account->codejercicio = $ejercicio->codejercicio;
        $account->descripcion = 'Test subvención ingreso modelo130';
        $this->assertTrue($account->save(), 'cant-save-account');

        $subcuenta = new Subcuenta();
        $subcuenta->codcuenta = '9996';
        $subcuenta->codejercicio = $ejercicio->codejercicio;
        $subcuenta->codsubcuenta = '9996000000';
        $subcuenta->descripcion = 'Test subvención ingreso modelo130';
        $this->assertTrue($subcuenta->save(), 'cant-save-subcuenta');

        // crear asiento con partida haber=1500 (simula una subvención de explotación)
        $asiento = new Asiento();
        $asiento->concepto = 'Test subvención Kit Digital modelo130';
        $asiento->fecha = date('Y') . '-02-15';
        $asiento->importe = 1500;
        $asiento->idempresa = $ejercicio->idempresa;
        $asiento->codejercicio = $ejercicio->codejercicio;
        $this->assertTrue($asiento->save(), 'cant-save-asiento');

        $partida = new Partida();
        $partida->idasiento = $asiento->idasiento;
        $partida->codsubcuenta = '9996000000';
        $partida->concepto = 'Test subvención Kit Digital modelo130';
        $partida->haber = 1500.0;
        $this->assertTrue($partida->save(), 'cant-save-partida');

        // registrar la subcuenta como ingreso en el modelo 130
        $sub130 = new Subcuenta130();
        $sub130->codsubcuenta = '9996000000';
        $sub130->tipo = Subcuenta130::TIPO_INGRESO;
        $this->assertTrue($sub130->save(), 'cant-save-subcuenta130');

        // configurar el controlador con el periodo T1 del año actual
        $controller = new Modelo130TestAccess(Modelo130TestAccess::class);
        $controller->setTestContext(
            $ejercicio->idempresa,
            date('01-01-Y'),
            date('31-03-Y')
        );

        // ejecutar la carga de asientos de ingresos
        $controller->callLoadIncomeAsientos();

        // verificar que la partida aparece en incomeEntries
        $this->assertNotEmpty($controller->incomeEntries, 'income-entries-should-not-be-empty');

        $haberTotal = array_sum(array_map(fn($p) => (float)$p->haber, $controller->incomeEntries));
        $this->assertEquals(1500.0, $haberTotal, 'income-haber-total-should-be-1500');

        // cleanup
        $this->assertTrue($sub130->delete());
        $this->assertTrue($partida->delete());
        $this->assertTrue($asiento->delete());
        $this->assertTrue($subcuenta->delete());
        $this->assertTrue($account->delete());
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}

class Modelo130TestAccess extends Modelo130
{
    public static function sanitizeCodes(array $codes): array
    {
        return parent::sanitizeSubaccountCodes($codes);
    }

    public function sqlValueCondition(string $field, $value): string
    {
        return $this->getSqlValueCondition($field, $value);
    }

    public function sqlValueList(array $values): string
    {
        return $this->getSqlValueList($values);
    }

    public function setTestContext(int $idempresa, string $dateStart, string $dateEnd): void
    {
        $this->idempresa = $idempresa;
        $this->dateStart = $dateStart;
        $this->dateEnd = $dateEnd;
    }

    public function callLoadIncomeAsientos(): void
    {
        $this->loadIncomeAsientos();
    }
}
