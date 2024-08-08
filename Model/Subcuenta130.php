<?php
namespace FacturaScripts\Plugins\Modelo130\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Session;
use FacturaScripts\Dinamic\Model\Subcuenta;

class Subcuenta130 extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $codsubcuenta;

    /** @var string */
    public $creation_date;

    /** @var int */
    public $id;

    /** @var string */
    public $last_nick;

    /** @var string */
    public $last_update;

    /** @var string */
    public $name;

    /** @var string */
    public $nick;

    public function getSubcuenta(): Subcuenta
    {
        $subcuenta = new Subcuenta();
        $where = [new DataBaseWhere('codsubcuenta', $this->codsubcuenta)];
        $subcuenta->loadFromCode('', $where);
        return $subcuenta;
    }

    public static function primaryColumn(): string
    {
        return "id";
    }

    public static function tableName(): string
    {
        return "subcuentas_130";
    }

    public function test(): bool
    {
        if (empty($this->primaryColumnValue())) {
            $this->creation_date = Tools::dateTime();
            $this->last_nick = null;
            $this->last_update = null;
            $this->nick = Session::user()->nick;
        } else {
            $this->creation_date = $this->creationdate ?? Tools::dateTime();
            $this->last_nick = Session::user()->nick;
            $this->last_update = Tools::dateTime();
            $this->nick = $this->nick ?? Session::user()->nick;
        }

        $this->codsubcuenta = Tools::noHtml($this->codsubcuenta);
        $this->name = Tools::noHtml($this->name);
        return parent::test();
    }
}
