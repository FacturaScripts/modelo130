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

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Dinamic\Model\Subcuenta130;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class Modelo130
{
    /** @var Partida[] */
    protected static $accountingEntries = [];

    /** @var FacturaCliente[] */
    protected static $customerInvoices = [];

    /** @var DataBase */
    protected static $dataBase;

    /** @var string */
    protected static $dateEnd = '';

    /** @var string */
    protected static $dateStart = '';

    /** @var Ejercicio */
    protected static $exercise;

    /** @var int|null */
    protected static $idempresa;

    /** @var Partida[] */
    protected static $incomeEntries = [];

    /** @var string */
    protected static $period = '';

    /** @var FacturaProveedor[] */
    protected static $supplierInvoices = [];

    public static function generate(
        string $codejercicio,
        string $period,
        bool $applyGastosJustificacion = false,
        float $todeduct = 20.0
    ): array {
        static::$exercise = new Ejercicio();
        if (false === static::$exercise->load($codejercicio)) {
            return [];
        }

        static::$dataBase = new DataBase();
        static::$period = strtoupper($period);
        static::$accountingEntries = [];
        static::$customerInvoices = [];
        static::$incomeEntries = [];
        static::$supplierInvoices = [];

        static::loadDates();
        static::loadInvoices();
        static::loadAsientos();
        static::loadIncomeAsientos();

        $results = static::loadResults($applyGastosJustificacion, $todeduct);

        return array_merge([
            'exercise' => static::$exercise,
            'period' => static::$period,
            'idempresa' => static::$idempresa,
            'customerInvoices' => static::$customerInvoices,
            'supplierInvoices' => static::$supplierInvoices,
            'accountingEntries' => static::$accountingEntries,
            'incomeEntries' => static::$incomeEntries,
            'applyGastosJustificacion' => $applyGastosJustificacion,
            'todeduct' => $todeduct,
        ], $results);
    }

    public static function generateEntries(int $idempresa, string $codejercicio, string $period, string $date, float $amount, ?string $paymentMethodId): bool
    {
        $asiento = new Asiento();
        $concepto = Tools::trans('acc-concept-irpf-130', ['%period%' => $period]);

        // si ya existe un asiento igual, no lo creamos
        if ($asiento->loadWhere(
            [
                Where::eq('codejercicio', $codejercicio),
                Where::eq('concepto', $concepto),
            ]
        )) {
            Tools::log()->warning('exists-accounting-130', ['%codejercicio%' => $codejercicio, '%concepto%' => $concepto]);
            return false;
        }

        $asiento->idempresa = $idempresa;
        $asiento->codejercicio = $codejercicio;
        $asiento->concepto = $concepto;
        $asiento->fecha = $date;
        $asiento->importe = $amount;
        if (false === $asiento->save()) {
            return false;
        }

        $partida1 = new Partida();
        $partida1->idasiento = $asiento->idasiento;
        $partida1->concepto = $asiento->concepto;
        $partida1->debe = $amount;
        $partida1->codsubcuenta = '4730000000';
        if (false === $partida1->save()) {
            $asiento->delete();
            return false;
        }

        $paymentMethod = new FormaPago();
        if ($paymentMethod->load($paymentMethodId)) {
            $bankAccount = $paymentMethod->getBankAccount();
        }

        $partida2 = new Partida();
        $partida2->idasiento = $asiento->idasiento;
        $partida2->concepto = $asiento->concepto;
        $partida2->haber = $amount;
        $partida2->codsubcuenta = !empty($bankAccount->codsubcuenta) ? $bankAccount->codsubcuenta : '5720000000';
        if (false === $partida2->save()) {
            $asiento->delete();
            return false;
        }

        Tools::log()->notice('accounting-created-130', ['%codejercicio%' => $codejercicio, '%concepto%' => $concepto]);
        return true;
    }

    protected static function getAccountingEntrySubaccounts(): array
    {
        $codsubs = [];
        $where = [Where::eq('tipo', Subcuenta130::TIPO_DEDUCIBLE)];
        foreach ((new Subcuenta130())->all($where, [], 0, 0) as $subaccount) {
            $codsubs[] = $subaccount->codsubcuenta;
        }

        return static::sanitizeSubaccountCodes($codsubs);
    }

    protected static function getSqlValueCondition(string $field, $value): string
    {
        if (null === $value) {
            return $field . ' IS NULL';
        }

        return $field . ' = ' . static::$dataBase->var2str($value);
    }

    protected static function getSqlValueList(array $values): string
    {
        $list = [];
        foreach ($values as $value) {
            $list[] = static::$dataBase->var2str($value);
        }

        return implode(',', $list);
    }

    protected static function loadAsientos(): void
    {
        $codsubs = static::getAccountingEntrySubaccounts();

        if (empty($codsubs)) {
            return;
        }

        $sql = 'SELECT * FROM ' . Partida::tableName() . ' as p'
            . ' LEFT JOIN ' . Asiento::tableName() . ' as a ON p.idasiento = a.idasiento'
            . ' WHERE ' . static::getSqlValueCondition('a.idempresa', static::$idempresa)
            . ' AND a.fecha BETWEEN ' . static::$dataBase->var2str(date('Y-m-d', strtotime(static::$dateStart)))
            . ' AND ' . static::$dataBase->var2str(date('Y-m-d', strtotime(static::$dateEnd)))
            . ' AND p.codsubcuenta IN (' . static::getSqlValueList($codsubs) . ')'
            . ' AND a.operacion IS ' . static::$dataBase->var2str(Asiento::OPERATION_GENERAL)
            . ' ORDER BY numero DESC';

        foreach (static::$dataBase->select($sql) as $row) {
            static::$accountingEntries[] = new Partida($row);
        }
    }

    protected static function loadDates(): void
    {
        if (!in_array(static::$period, ['T1', 'T2', 'T3', 'T4'])) {
            static::$period = 'T1';
        }

        switch (static::$period) {
            case 'T1':
                static::$dateStart = date('01-01-Y', strtotime(static::$exercise->fechainicio));
                static::$dateEnd = date('31-03-Y', strtotime(static::$exercise->fechainicio));
                break;

            case 'T2':
                static::$dateStart = date('01-01-Y', strtotime(static::$exercise->fechainicio));
                static::$dateEnd = date('30-06-Y', strtotime(static::$exercise->fechainicio));
                break;

            case 'T3':
                static::$dateStart = date('01-01-Y', strtotime(static::$exercise->fechainicio));
                static::$dateEnd = date('30-09-Y', strtotime(static::$exercise->fechainicio));
                break;

            default:
                static::$dateStart = date('01-01-Y', strtotime(static::$exercise->fechainicio));
                static::$dateEnd = date('31-12-Y', strtotime(static::$exercise->fechainicio));
                break;
        }

        static::$idempresa = static::$exercise->idempresa;
    }

    protected static function loadIncomeAsientos(): void
    {
        $codsubs = [];
        $where = [Where::eq('tipo', Subcuenta130::TIPO_INGRESO)];
        foreach ((new Subcuenta130())->all($where, [], 0, 0) as $subaccount) {
            $codsubs[] = $subaccount->codsubcuenta;
        }
        $codsubs = static::sanitizeSubaccountCodes($codsubs);

        if (empty($codsubs)) {
            return;
        }

        $sql = 'SELECT * FROM ' . Partida::tableName() . ' as p'
            . ' LEFT JOIN ' . Asiento::tableName() . ' as a ON p.idasiento = a.idasiento'
            . ' WHERE ' . static::getSqlValueCondition('a.idempresa', static::$idempresa)
            . ' AND a.fecha BETWEEN ' . static::$dataBase->var2str(date('Y-m-d', strtotime(static::$dateStart)))
            . ' AND ' . static::$dataBase->var2str(date('Y-m-d', strtotime(static::$dateEnd)))
            . ' AND p.codsubcuenta IN (' . static::getSqlValueList($codsubs) . ')'
            . ' AND a.operacion IS ' . static::$dataBase->var2str(Asiento::OPERATION_GENERAL)
            . ' ORDER BY numero DESC';

        foreach (static::$dataBase->select($sql) as $row) {
            static::$incomeEntries[] = new Partida($row);
        }
    }

    protected static function loadInvoices(): void
    {
        $whereFtrasProveedores = [
            Where::gte('fecha', date('Y-m-d', strtotime(static::$dateStart))),
            Where::lte('fecha', date('Y-m-d', strtotime(static::$dateEnd))),
            Where::eq('idempresa', static::$idempresa),
        ];

        $whereFtrasClientes = [
            Where::gte('fecha', date('Y-m-d', strtotime(static::$dateStart))),
            Where::lte('fecha', date('Y-m-d', strtotime(static::$dateEnd))),
            Where::eq('idempresa', static::$idempresa),
        ];

        $order = ['fecha' => 'DESC', 'numero' => 'DESC'];

        static::$supplierInvoices = (new FacturaProveedor())->all($whereFtrasProveedores, $order, 0, 0);
        static::$customerInvoices = (new FacturaCliente())->all($whereFtrasClientes, $order, 0, 0);
    }

    protected static function loadResults(bool $applyGastosJustificacion, float $todeduct): array
    {
        $taxbaseIngresos = 0.0;
        $taxbaseRetenciones = 0.0;
        $taxbaseGastos = 0.0;
        $segSocial = 0.0;
        $otrasDeducciones = 0.0;
        $positivosTrimestres = 0.0;

        foreach (static::$customerInvoices as $invoice) {
            $taxbaseIngresos += $invoice->neto;
            $taxbaseRetenciones += $invoice->totalirpf;
        }

        foreach (static::$incomeEntries as $partida) {
            $taxbaseIngresos += $partida->haber;
        }

        foreach (static::$supplierInvoices as $invoice) {
            $taxbaseGastos += $invoice->neto;
        }

        foreach (static::$accountingEntries as $asiento) {
            switch ($asiento->codsubcuenta) {
                case '6420000000':
                    $segSocial += $asiento->debe;
                    break;
                case '4730000000':
                    $positivosTrimestres += $asiento->debe;
                    break;
                default:
                    $otrasDeducciones += $asiento->debe;
                    break;
            }
        }

        // la seguridad social y el resto de deducciones se cuentan como gasto deducible
        $taxbaseGastos += ($segSocial + $otrasDeducciones);

        // la cuenta 473 incluye trimestres anteriores y retenciones de facturas
        $positivosTrimestres = round($positivosTrimestres - $taxbaseRetenciones, 2);

        $taxbase = round($taxbaseIngresos - $taxbaseGastos, 2);

        $gastosJustificacion = 0.0;
        if ($applyGastosJustificacion && $taxbase > 0) {
            $gastosJustificacion = round($taxbase * 0.05, 2);
        }

        $afterdeduct = round((($taxbase - $gastosJustificacion) * $todeduct) / 100, 2);

        $result = round($afterdeduct - $taxbaseRetenciones - $positivosTrimestres, 2);
        if ($result < 0) {
            $result = 0.0;
        }

        return [
            'taxbaseIngresos' => $taxbaseIngresos,
            'taxbaseRetenciones' => $taxbaseRetenciones,
            'taxbaseGastos' => $taxbaseGastos,
            'taxbase' => $taxbase,
            'gastosJustificacion' => $gastosJustificacion,
            'afterdeduct' => $afterdeduct,
            'positivosTrimestres' => $positivosTrimestres,
            'result' => $result,
        ];
    }

    protected static function sanitizeSubaccountCodes(array $codes): array
    {
        $result = [];
        foreach ($codes as $code) {
            $code = trim((string)$code);
            if ($code === '') {
                continue;
            }

            $result[] = $code;
        }

        return array_values(array_unique($result));
    }
}
