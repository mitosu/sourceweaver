<?php

namespace App\Tests\Entity;

use App\Entity\Dashboard;
use App\Entity\MainTableTab;
use App\Entity\KanbanTab;
use App\Entity\CalendarTab;
use App\Entity\ValueObject\TabName;
use PHPUnit\Framework\TestCase;

class TabEntitiesTest extends TestCase
{
    public function testMainTableTabInitialization(): void
    {
        $dashboard = $this->createMock(Dashboard::class);

        $reflection = new \ReflectionClass(MainTableTab::class);
        $tab = $reflection->newInstanceWithoutConstructor();
        $tabName = new TabName('Main Table');
        $tab->initialize($tabName, $dashboard, 1);

        $this->assertEquals('Main Table', $tab->getName());
        $this->assertSame($dashboard, $tab->getDashboard());
        $this->assertEquals(1, $tab->getPosition());
    }

    public function testKanbanTabInitialization(): void
    {
        $dashboard = $this->createMock(Dashboard::class);

        $reflection = new \ReflectionClass(KanbanTab::class);
        $tab = $reflection->newInstanceWithoutConstructor();
        $tabName = new TabName('Kanban View');
        $tab->initialize($tabName, $dashboard, 2);

        $this->assertEquals('Kanban View', $tab->getName());
        $this->assertSame($dashboard, $tab->getDashboard());
        $this->assertEquals(2, $tab->getPosition());
    }

    public function testCalendarTabInitialization(): void
    {
        $dashboard = $this->createMock(Dashboard::class);

        $reflection = new \ReflectionClass(CalendarTab::class);
        $tab = $reflection->newInstanceWithoutConstructor();
        $tabName = new TabName('Calendar');
        $tab->initialize($tabName, $dashboard, 3);

        $this->assertEquals('Calendar', $tab->getName());
        $this->assertSame($dashboard, $tab->getDashboard());
        $this->assertEquals(3, $tab->getPosition());
    }

    public function testRenameTab(): void
    {
        $dashboard = $this->createMock(Dashboard::class);

        $reflection = new \ReflectionClass(MainTableTab::class);
        $tab = $reflection->newInstanceWithoutConstructor();
        $tabName = new TabName('Initial Tab');
        $tab->initialize($tabName, $dashboard, 1);

        $tabName = new TabName('Renamed Tab');
        $tab->renameTo($tabName);
        $this->assertEquals('Renamed Tab', $tab->getName());
    }
}
