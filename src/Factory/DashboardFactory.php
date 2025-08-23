<?php

namespace App\Factory;

use App\Entity\Dashboard;
use App\Entity\Workspace;
use App\Entity\ValueObject\DashboardName;
use ReflectionClass;

class DashboardFactory
{
    public static function create(DashboardName $name, Workspace $workspace): Dashboard
    {
        $reflection = new ReflectionClass(Dashboard::class);
        /** @var Dashboard $dashboard */
        $dashboard = $reflection->newInstanceWithoutConstructor();
        $dashboard->initialize($name, $workspace);
        return $dashboard;
    }
}
