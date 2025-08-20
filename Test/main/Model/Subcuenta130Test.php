<?php
/**
 * This file is part of Modelo347 plugin for FacturaScripts
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Test\Plugins\Model;

use FacturaScripts\Dinamic\Model\Cuenta;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Dinamic\Model\Subcuenta130;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

/**
 * @author Abderrahim Darghal Belkacemi <abdedarghal111@gmail.com>
 */
final class ModTest extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
    }

    public function testCreateSubcuenta130(): void
    {
        // crear un ejercicio
        $exercise = $this->getRandomExercise();

        // crear una cuenta
        $account = new Cuenta();
        $account->codcuenta = '9999';
        $account->codejercicio = $exercise->codejercicio;
        $account->descripcion = 'Test';
        $this->assertTrue($account->save(), 'cant-save-account');

        // crear una subcuenta
        $subaccount = new Subcuenta();
        $subaccount->codcuenta = $account->codcuenta;
        $subaccount->codejercicio = $exercise->codejercicio;
        $subaccount->codsubcuenta = $account->codcuenta . '.1';
        $subaccount->descripcion = 'Test';
        $this->assertTrue($subaccount->save(), 'cant-save-subaccount');
        $this->assertTrue($subaccount->exists(), 'subaccount-cant-persist');


        // crear subcuenta130
        $subcuenta130 = new Subcuenta130();
        $subcuenta130->codsubcuenta = $subaccount->codsubcuenta;
        $this->assertTrue($subcuenta130->save(), 'subcuenta130-cant-save');

        // borrar subcuenta y subcuenta130
        $this->assertTrue($subcuenta130->delete(), 'subcuenta130-cant-delete');
        $this->assertTrue($subaccount->delete(), 'subaccount-cant-delete');
        $this->assertTrue($account->delete(), 'account-cant-delete');
    }

    protected function getRandomExercise(): Ejercicio
    {
        $model = new Ejercicio();
        foreach ($model->all() as $ejercicio) {
            return $ejercicio;
        }

        // no hemos encontrado ninguno, creamos uno
        $model->loadFromDate(date('d-m-Y'));
        return $model;
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
