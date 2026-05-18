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

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Partida;

class Modelo130
{
    public static function generate(): array {}

    public static function generateEntries(int $idempresa, string $codejercicio, string $period, string $date, float $amount, int $paymentMethodId): bool
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
        $partida1->codsubcuenta = '4730000000'; // Código de subcuenta de IRPF
        if (false === $partida1->save()) {
            $asiento->delete();
            return false;
        }

        // Buscamos si la forma de pago tiene una subcuenta de cara a asignarla o dejar el valor por defecto en la partida
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
}
