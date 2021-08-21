<?php
/**
 * This file is part of Modelo130 plugin for FacturaScripts
 * Copyright (C) 2021 Carlos Garcia Gomez            <carlos@facturascripts.com>
 *                    Jeronimo Pedro Sánchez Manzano <socger@gmail.com>
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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\Retencion;

/**
 * Description of Modelo130
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Modelo130 extends Controller
{

    /**
     *
     * @var string
     */
    public $codejercicio;

    /**
     *
     * @var string
     */
    public $codretencion;

    /**
     *
     * @var string
     */
    public $dateEnd;

    /**
     *
     * @var string
     */
    public $dateStart;

    /**
     *
     * @var int
     */
    protected $idempresa;

    /**
     *
     * @var FacturaProveedor[]
     */
    public $customerInvoices = [];

    /**
     *
     * @var FacturaCliente[]
     */
    public $supplierInvoices = [];

    /**
     *
     * @var int
     */
    public $numrecipientsFtrasProveedores = 0;

    /**
     *
     * @var int
     */
    public $numrecipientsFtrasClientes = 0;

    /**
     *
     * @var string
     */
    public $period = 'T1';

    /**
     *
     * @var float
     */
    public $result = 0.0;

    /**
     *
     * @var float
     */
    public $retentionsFtrasProveedores = 0.0;

    /**
     *
     * @var float
     */
    public $retentionsFtrasClientes = 0.0;

    /**
     *
     * @var float
     */
    public $taxbaseFtrasProveedores = 0.0;

    /**
     *
     * @var float
     */
    public $taxbaseFtrasClientes = 0.0;

    /**
     *
     * @var float
     */
    public $todeduct = 0.0;
    
    // ************************************************************************* //
    // ************************************************************************* //
    // ************************************************************************* //
    // ************************************************************************* //

    // -- ------------------------------------------------------------------- -- //
    // -- FUNCIONES USADAS POR PLANTILLA Modelo130.html.twig                  -- //
    // -- ------------------------------------------------------------------- -- //
    /**
     * 
     * @return Ejercicio[]
     */
    public function getExercisesForComboBoxHtml()
    {
        $exercise = new Ejercicio();
        return $exercise->all([], ['nombre' => 'DESC'], 0, 0);
    }

    /**
     * 
     * @return array
     */
    public function getPeriodsForComboBoxHtml()
    {
        return [
            'T1' => 'first-trimester',
            'T2' => 'second-trimester',
            'T3' => 'third-trimester',
            'T4' => 'fourth-trimester'
        ];
    }
    
    /**
     * 
     * @return array
     */
    public function getRetentionsForComboBoxHtml()
    {
        $retention = new Retencion();
        return $retention->all([], ['descripcion' => 'ASC'], 0, 0);
    }
    
    // -- ------------------------------------------------------------------- -- //
    // -- FUNCIONES USADAS POR ESTE CONTROLADOR                               -- //
    // -- ------------------------------------------------------------------- -- //
    /**
     * 
     * @return array
     */
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'model-130';
        $data['icon'] = 'fas fa-book';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions); // Comprueba, entre varias cosas, si el usuario tiene permisos para este Controlador
        
        $this->loadDates(); // Traemos del codejercicio y period elegido idempresa, dateStart y dateEnd
        $this->loadInvoices(); // jerofa vas por aqui
        $this->loadResults();
    }

    protected function loadDates()
    {
        // Preparamos fecha de Inicio y Fin, según Ejercicio/Periodo introducido en Modelo130.html.twig
        $this->codejercicio = $this->request->request->get('codejercicio', ''); // Modelo130.html.twig nos trae request.codejercicio
        $this->period = $this->request->request->get('period', $this->period);  // Modelo130.html.twig nos trae request.period

        $exercise = new Ejercicio(); // Creamos objeto/modelo Ejercicio
        $exercise->loadFromCode($this->codejercicio); // Cargamos el registro del codejercicio elegido
        $this->idempresa = $exercise->idempresa; // Guardamos en idempresa la empresa del codejercicio elegido

        // Cargamos las variables dateStart y dateEnd con los valores de inicio y fin del codeejercicio elegido
        switch ($this->period) {
            case 'T1':
                $this->dateStart = \date('01-01-Y', \strtotime($exercise->fechainicio));
                $this->dateEnd = \date('31-03-Y', \strtotime($exercise->fechainicio));
                break;

            case 'T2':
                $this->dateStart = \date('01-04-Y', \strtotime($exercise->fechainicio));
                $this->dateEnd = \date('30-06-Y', \strtotime($exercise->fechainicio));
                break;

            case 'T3':
                $this->dateStart = \date('01-07-Y', \strtotime($exercise->fechainicio));
                $this->dateEnd = \date('30-09-Y', \strtotime($exercise->fechainicio));
                break;

            default:
                $this->dateStart = \date('01-10-Y', \strtotime($exercise->fechainicio));
                $this->dateEnd = \date('31-12-Y', \strtotime($exercise->fechainicio));
                break;
        }
    }

    protected function loadInvoices()
    {
        $ftrasProveedores = new FacturaProveedor();
        $ftrasClientes = new FacturaCliente();

        $whereFtrasProveedores = [
            // Para buscar en el margen de fechas del periodo
            new DataBaseWhere('fecha', $this->dateStart, '>='),
            new DataBaseWhere('fecha', $this->dateEnd, '<='),
            
            // Para buscar ftras sólo de la empresa/Ejercicio elegido
            new DataBaseWhere('idempresa', $this->idempresa),
            
            // Para buscar ftras (de clientes o de Proveedores) que tengan IRPF
            new DataBaseWhere('totalirpf', 0.0, '!=')
        ];
        

        $whereFtrasClientes = [
            // Para buscar en el margen de fechas del periodo
            new DataBaseWhere('fecha', $this->dateStart, '>='),
            new DataBaseWhere('fecha', $this->dateEnd, '<='),
            
            // Para buscar ftras sólo de la empresa/Ejercicio elegido
            new DataBaseWhere('idempresa', $this->idempresa),
            
            // Para buscar ftras (de clientes o de Proveedores) que tengan IRPF
            new DataBaseWhere('totalirpf', 0.0, '!=')
        ];
        
        
        // Si me han elegido un tipo de retención, se lo añadimos al where
        $this->codretencion = $this->request->request->get('codretencion', '');
        
        $retention = new Retencion();
        if (!empty($this->codretencion) && $retention->loadFromCode($this->codretencion)) {
            $whereFtrasProveedores[] = new DataBaseWhere('irpf', $retention->porcentaje);
         // $whereFtrasClientes[] = new DataBaseWhere('irpf', $retention->porcentaje);
        }

        // Preparamos el orderBy de como vamos a traer las facturas (fecha + numero ftra)
        $order = ['fecha' => 'ASC', 'numero' => 'ASC'];
        
        // Cargamos primero las facturas de proveedores
        // $this->invoices = $ftrasProveedores->all($where, $order, 0, 0);
        $this->customerInvoices = $ftrasProveedores->all($whereFtrasProveedores, $order, 0, 0);
//var_dump($this->customerInvoices);

        // Cargamos ahora las facturas de clientes
        $this->supplierInvoices = $ftrasClientes->all($whereFtrasClientes, $order, 0, 0);
//var_dump($this->supplierInvoices);
//var_dump($whereFtrasClientes);
    }

    // ************************************************************************* //
    // ************************************************************************* //
    // ************************************************************************* //
    // ************************************************************************* //
    protected function loadResults()
    {
        $recipientsFtrasProveedores = [];
        $recipientsFtrasClientes = [];
        
        foreach ($this->customerInvoices as $invoice) {
            $recipientsFtrasProveedores[$invoice->codproveedor] = $invoice->codproveedor;
            $this->taxbaseFtrasProveedores += $invoice->neto;
            $this->retentionsFtrasProveedores += $invoice->totalirpf;
        }
        
        foreach ($this->supplierInvoices as $invoice) {
            $recipientsFtrasClientes[$invoice->codcliente] = $invoice->codcliente;
            $this->taxbaseFtrasClientes += $invoice->neto;
            $this->retentionsFtrasClientes += $invoice->totalirpf;
        }


        $this->numrecipientsFtrasClientes = \count($recipientsFtrasClientes);
        $this->numrecipientsFtrasProveedores = \count($recipientsFtrasProveedores);
        
        $this->todeduct = (float) $this->request->request->get('todeduct');
        
     // $this->result = $this->retentions - $this->todeduct;
        $this->result = ($this->retentionsFtrasClientes - $this->retentionsFtrasProveedores) - $this->todeduct;
    }
    
}
