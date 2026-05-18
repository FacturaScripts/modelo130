<?php
/**
 * This file is part of Modelo130 plugin for FacturaScripts
 * Copyright (C) 2021-2026 Carlos Garcia Gomez            <carlos@facturascripts.com>
 *                         Jeronimo Pedro Sánchez Manzano <socger@gmail.com>
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

namespace FacturaScripts\Plugins\Modelo130\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\DataSrc\Ejercicios;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Subcuenta130;
use FacturaScripts\Plugins\Modelo130\Lib\Modelo130 as LibModelo130;

/**
 * Description of Modelo130
 *
 * @author Carlos Garcia Gomez            <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez       <contacto@danielfg.es>
 * @author Jerónimo Pedro Sánchez Manzano <socger@gmail.com>
 * @author Javier Martín González         <javier@javiermarting.es>
 */
class Modelo130 extends Controller
{
    /** @var string */
    public $activeTab = '';

    /** bool */
    public $applyGastosJustificacion = false;

    /** @var string */
    public $codejercicio;

    /** @var Subcuenta130 */
    public $deductibleSubaccount;

    /** @var Subcuenta130 */
    public $incomeSubaccount;

    /** @var string */
    public $period = 'T1';

    /** @var array */
    public $result = [];

    /** float */
    public $todeduct = 20;

    /**
     * @param int|null $idempresa
     * @return Ejercicio[]
     */
    public function getAllExercises(?int $idempresa): array
    {
        if (empty($idempresa)) {
            return Ejercicios::all();
        }

        $list = [];
        foreach (Ejercicios::all() as $exercise) {
            if ($exercise->idempresa === $idempresa) {
                $list[] = $exercise;
            }
        }
        return $list;
    }

    public function getDeductibleSubaccounts(): array
    {
        $where = [Where::eq('tipo', Subcuenta130::TIPO_DEDUCIBLE)];
        return (new Subcuenta130())->all($where, ['codsubcuenta' => 'ASC'], 0, 0);
    }

    /**
     * @param string|null $codejercicio
     * @return Ejercicio
     */
    public function getExercise(?string $codejercicio): Ejercicio
    {
        $exercise = new Ejercicio();
        $exercise->load($this->codejercicio);
        return $exercise;
    }

    public function getIncomeSubaccounts(): array
    {
        $where = [Where::eq('tipo', Subcuenta130::TIPO_INGRESO)];
        return (new Subcuenta130())->all($where, ['codsubcuenta' => 'ASC'], 0, 0);
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'model-130';
        $data['icon'] = 'fa-solid fa-book';
        return $data;
    }

    public function getPaymentMethods(): array
    {
        return FormaPago::all();
    }

    public function getPeriodsForComboBoxHtml(): array
    {
        return [
            'T1' => 'first-trimester',
            'T2' => 'second-trimester',
            'T3' => 'third-trimester',
            'T4' => 'fourth-trimester'
        ];
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->deductibleSubaccount = new Subcuenta130();
        $this->incomeSubaccount = new Subcuenta130();

        $action = $this->request->request->get('action', $this->request->input('action'));
        switch ($action) {
            case 'autocomplete-subaccount':
                $this->autocompleteSubaccount();
                return;

            case 'add-deductible-subaccount':
                $this->addDeductibleSubaccount();
                return;

            case 'delete-deductible-subaccount':
                $this->deleteDeductibleSubaccount();
                return;

            case 'add-income-subaccount':
                $this->addIncomeSubaccount();
                return;

            case 'delete-income-subaccount':
                $this->deleteIncomeSubaccount();
                return;

            case 'gen-accounting':
                $this->createAccountingEntry();
                return;
        }

        $this->codejercicio = $this->request->request->get('codejercicio', '');
        $this->period = $this->request->request->get('period', $this->period);
        $this->applyGastosJustificacion = (bool)$this->request->request->get('applyGastosJustificacion', false);
        $this->todeduct = (float)$this->request->request->get('todeduct', 20.0);

        $this->result = LibModelo130::generate($this->codejercicio, $this->period, $this->applyGastosJustificacion, $this->todeduct);
    }

    protected function addDeductibleSubaccount(): void
    {
        $this->activeTab = 'deductible-subaccount';

        if (false === $this->validateFormToken()) {
            return;
        }

        $subaccount130 = new Subcuenta130();
        $subaccount130->codsubcuenta = $this->request->request->get('codsubcuenta');
        if (false === $subaccount130->save()) {
            Tools::log()->error('record-save-error');
            return;
        }

        Tools::log()->notice('record-updated-correctly');
    }

    protected function addIncomeSubaccount(): void
    {
        $this->activeTab = 'income-subaccount';

        if (false === $this->validateFormToken()) {
            return;
        }

        $subaccount = new Subcuenta130();
        $subaccount->codsubcuenta = $this->request->request->get('codsubcuenta');
        $subaccount->tipo = Subcuenta130::TIPO_INGRESO;
        if (false === $subaccount->save()) {
            Tools::log()->error('record-save-error');
            return;
        }

        Tools::log()->notice('record-updated-correctly');
    }

    protected function autocompleteSubaccount(): void
    {
        $this->setTemplate(false);

        $list = [];
        $term = $this->request->get('term');
        $sql = 'SELECT DISTINCT codsubcuenta, descripcion FROM subcuentas WHERE codsubcuenta LIKE "' . $term . '%";';

        foreach ($this->dataBase->select($sql) as $value) {
            $list[] = [
                'key' => Tools::fixHtml($value['codsubcuenta']),
                'value' => Tools::fixHtml($value['descripcion'])
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => Tools::lang()->trans('no-data')];
        }

        $this->response->setContent(json_encode($list));
    }

    protected function createAccountingEntry(): void
    {
        if (false === $this->validateFormToken()) {
            return;
        }

        $idempresa = (int)$this->request->request->get('idempresa');
        $codejercicio = (string)$this->request->request->get('codejercicio');
        $period = (string)$this->request->request->get('period');
        $date = (string)$this->request->request->get('date');
        $amount = (float)$this->request->request->get('amount');
        $paymentMethodId = (int)$this->request->request->get('paymentMethod');

        if (LibModelo130::generateEntries($idempresa, $codejercicio, $period, $date, $amount, $paymentMethodId)) {
            Tools::log()->notice('record-updated-correctly');
        }
    }

    protected function deleteDeductibleSubaccount(): void
    {
        $this->activeTab = 'deductible-subaccount';

        if (false === $this->validateFormToken()) {
            return;
        }

        $subaccount130 = new Subcuenta130();
        if (false === $subaccount130->load($this->request->request->get('id'))) {
            Tools::log()->error('record-not-found');
            return;
        }

        if (false === $subaccount130->delete()) {
            Tools::log()->error('record-deleted-error');
            return;
        }

        Tools::log()->notice('record-deleted-correctly');
    }

    protected function deleteIncomeSubaccount(): void
    {
        $this->activeTab = 'income-subaccount';

        if (false === $this->validateFormToken()) {
            return;
        }

        $subaccount = new Subcuenta130();
        if (false === $subaccount->load($this->request->request->get('id'))) {
            Tools::log()->error('record-not-found');
            return;
        }

        if (false === $subaccount->delete()) {
            Tools::log()->error('record-deleted-error');
            return;
        }

        Tools::log()->notice('record-deleted-correctly');
    }
}
