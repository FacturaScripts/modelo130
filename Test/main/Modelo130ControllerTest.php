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
}
