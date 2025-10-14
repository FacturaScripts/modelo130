<?php
/**
 * This file is part of Modelo130 plugin for FacturaScripts
 * Copyright (C) 2021-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\Modelo130;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Template\InitClass;

final class Init extends InitClass
{
    public function init(): void
    {
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        $this->cleanInvalidUsers();
    }

    private function cleanInvalidUsers(): void
    {
        // ver si existe la tabla subcuentas o usuario
        $db = new DataBase();
        if (false === $db->tableExists('subcuentas_130') ||
            false === $db->tableExists('users')) {
            return;
        }

        // consulta compatible con mysql y postgresql
        // elimina el registro foráneo si no existe en la tabla original
        $templateSql = "UPDATE subcuentas_130
        SET REPLACE_COLUMN = NULL
        WHERE REPLACE_COLUMN IS NOT NULL
        AND NOT EXISTS (
            SELECT 1
            FROM users
            WHERE users.nick = subcuentas_130.REPLACE_COLUMN
        );";

        foreach (['nick', 'last_nick'] as $column) {
            // reemplazar la columna de user en la tabla subcuentas_130 y ejecutar
            $sql = str_replace("REPLACE_COLUMN", $column, $templateSql);
            $db->exec($sql);
        }
    }
}
