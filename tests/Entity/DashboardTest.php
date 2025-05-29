<?php

namespace App\Tests\Entity;

use App\Entity\Dashboard;
use App\Entity\Workspace;
use PHPUnit\Framework\TestCase;
use App\Entity\ValueObject\DashboardName;

class DashboardTest extends TestCase
{
    public function testDashboardInitialization(): void
    {
        $workspace = $this->createMock(Workspace::class);

        $reflection = new \ReflectionClass(Dashboard::class);
        /** @var Dashboard $dashboard */
        $dashboard = $reflection->newInstanceWithoutConstructor();
        $dashboardName = new DashboardName('Main Dashboard');
        $dashboard->initialize($dashboardName, $workspace);

        $this->assertEquals('Main Dashboard', $dashboard->getName());
        $this->assertSame($workspace, $dashboard->getWorkspace());
        $this->assertNotNull($dashboard->getId());
        $this->assertNotNull($dashboard->getCreatedAt());
    }

    public function testRenameDashboard(): void
    {
        $workspace = $this->createMock(Workspace::class);

        $reflection = new \ReflectionClass(Dashboard::class);
        $dashboard = $reflection->newInstanceWithoutConstructor();
        $dashboardName = new DashboardName('Initial Dashboard');
        $dashboard->initialize($dashboardName, $workspace);

        $dashboardName = new DashboardName('Renamed Dashboard');
        $dashboard->renameTo($dashboardName);
        $this->assertEquals('Renamed Dashboard', $dashboard->getName());
    }
}
