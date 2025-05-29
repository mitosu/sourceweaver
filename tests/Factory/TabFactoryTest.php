<?php

namespace App\Tests\Factory;

use App\Entity\Dashboard;
use App\Factory\TabFactory;
use App\Entity\ValueObject\TabName;
use PHPUnit\Framework\TestCase;

class TabFactoryTest extends TestCase
{
    public function testCreateMainTableTab(): void
    {
        $dashboard = $this->createMock(Dashboard::class);
        $tabName = new TabName('Main Table');
        $tab = TabFactory::mainTable($tabName, $dashboard, 1);

        $this->assertSame('Main Table', $tab->getName());
        $this->assertSame(1, $tab->getPosition());
        $this->assertSame($dashboard, $tab->getDashboard());
    }

    public function testCreateKanbanTab(): void
    {
        $dashboard = $this->createMock(Dashboard::class);
        $tabName = new TabName('Kanban View');
        $tab = TabFactory::kanban($tabName, $dashboard, 2);

        $this->assertSame('Kanban View', $tab->getName());
    }

    public function testCreateCalendarTab(): void
    {
        $dashboard = $this->createMock(Dashboard::class);
        $tabName = new TabName('Calendar');
        $tab = TabFactory::calendar($tabName, $dashboard, 3);

        $this->assertSame('Calendar', $tab->getName());
    }
}
