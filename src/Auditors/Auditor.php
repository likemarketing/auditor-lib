<?php

namespace Mnoskov\Auditor\Auditors;

use Mnoskov\Auditor\Manager;
use Mnoskov\Auditor\Models\Auditor as Model;
use Slim\Container;

class Auditor
{
    protected $manager;
    protected $ci;
    protected $view;
    protected $model;
    protected $totalErrors = 0;
    protected $errors = [];
    protected $result = [];

    public function __construct(Manager $manager, Model $model)
    {
        $this->manager = $manager;
        $this->model   = $model;
        $this->ci      = $manager->getContainer();

        $manager->getView()->addGlobal('client', $manager->getClient());
    }

    public function match() : bool
    {
        return false;
    }

    public function getResult()
    {
        return $this->result;
    }
}
