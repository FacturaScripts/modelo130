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

namespace FacturaScripts\Plugins\Modelo130\Lib;

/**
 * Centraliza las cuentas contables utilizadas para calcular el Modelo 130.
 *
 * Los códigos indicados son prefijos. Por ejemplo, el código 64 incluye
 * cualquier subcuenta que comience por 64.
 */
final class Modelo130Accounts
{
    /**
     * Hacienda pública, retenciones y pagos a cuenta.
     *
     * La cuenta 473 puede contener:
     * - retenciones practicadas en facturas;
     * - pagos fraccionados de trimestres anteriores.
     *
     * La diferencia se determina comprobando si el asiento tiene una factura
     * asociada.
     */
    public const WITHHOLDING_ACCOUNT = '473';

    /**
     * Cuentas de gastos computables.
     *
     * @return array<string, string>
     */
    public static function expenses(): array
    {
        return [
            '60' => 'Compras',
            '61' => 'Variación de existencias',
            '62' => 'Servicios exteriores',
            '63' => 'Tributos',
            '64' => 'Gastos de personal',
            '65' => 'Otros gastos de gestión',
            '66' => 'Gastos financieros',
            '67' => 'Pérdidas procedentes de activos no corrientes y gastos excepcionales',
            '68' => 'Dotaciones para amortizaciones',
            '69' => 'Pérdidas por deterioro y otras dotaciones',
        ];
    }

    /**
     * Cuentas excluidas aunque pertenezcan a uno de los grupos de gastos.
     *
     * La cuenta 678 suele utilizarse para multas, sanciones y otros gastos
     * excepcionales no deducibles.
     *
     * @return array<string, string>
     */
    public static function excludedExpenses(): array
    {
        return [
            '678' => 'Gastos excepcionales (subcuenta habitual para no deducibles',
        ];
    }

    /**
     * Cuentas de ingresos computables.
     *
     * @return array<string, string>
     */
    public static function incomes(): array
    {
        return [
            '70' => 'Ventas de mercaderías, de producción propia, de servicios, etc.',
            '71' => 'Variación de existencias',
            '73' => 'Trabajos realizados para la empresa',
            '74' => 'Subvenciones, donaciones y legados',
            '75' => 'Otros ingresos de gestión',
            '76' => 'Ingresos financieros',
            '77' => 'Beneficios procedentes de activos no corrientes e ingresos excepcionales',
            '79' => 'Excesos y aplicaciones de provisiones y de pérdidas por deterioro',
        ];
    }

    /**
     * Comprueba si una subcuenta debe considerarse gasto.
     */
    public static function isExpense(string $code): bool
    {
        $code = trim($code);

        if ($code === '') {
            return false;
        }

        if (static::matches($code, array_keys(static::excludedExpenses()))) {
            return false;
        }

        return static::matches($code, array_keys(static::expenses()));
    }

    /**
     * Comprueba si una subcuenta debe considerarse ingreso.
     */
    public static function isIncome(string $code): bool
    {
        return static::matches(
            trim($code),
            array_keys(static::incomes())
        );
    }

    /**
     * Comprueba si una subcuenta pertenece a la cuenta 473.
     */
    public static function isWithholding(string $code): bool
    {
        return str_starts_with(
            trim($code),
            static::WITHHOLDING_ACCOUNT
        );
    }

    /**
     * Devuelve todos los prefijos que deben buscarse en contabilidad.
     *
     * Se usa para construir la consulta SQL inicial y evitar cargar partidas
     * que nunca van a intervenir en el Modelo 130.
     *
     * @return string[]
     */
    public static function queryPrefixes(): array
    {
        return array_values(array_unique(array_merge(
            array_keys(static::expenses()),
            array_keys(static::incomes()),
            [static::WITHHOLDING_ACCOUNT]
        )));
    }

    /**
     * Comprueba si un código comienza por alguno de los prefijos indicados.
     *
     * @param string[] $prefixes
     */
    private static function matches(string $code, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return true;
            }
        }

        return false;
    }
}