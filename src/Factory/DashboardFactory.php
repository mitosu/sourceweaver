<?php
namespace App\Factory;

use App\Entity\Dashboard;
use App\Entity\Workspace;

class DashboardFactory
{
    public static function create(string $name, Workspace $workspace): Dashboard
    {
        $dashboard = new Dashboard();
        $dashboard->initialize($name, $workspace);
        return $dashboard;
    }
}
