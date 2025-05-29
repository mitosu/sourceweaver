<?php

namespace App\Factory;

use App\Entity\Dashboard;
use App\Entity\MainTableTab;
use App\Entity\KanbanTab;
use App\Entity\CalendarTab;
use App\Entity\Tab;
use App\Entity\ValueObject\TabName;
use ReflectionClass;

class TabFactory
{
    public static function mainTable(TabName $name, Dashboard $dashboard, int $position): MainTableTab
    {
        $reflection = new ReflectionClass(MainTableTab::class);
        /** @var MainTableTab $tab */
        $tab = $reflection->newInstanceWithoutConstructor();
        $tab->initialize($name, $dashboard, $position);
        return $tab;
    }

    public static function kanban(TabName $name, Dashboard $dashboard, int $position): KanbanTab
    {
        $reflection = new ReflectionClass(KanbanTab::class);
        /** @var KanbanTab $tab */
        $tab = $reflection->newInstanceWithoutConstructor();
        $tab->initialize($name, $dashboard, $position);
        return $tab;
    }

    public static function calendar(TabName $name, Dashboard $dashboard, int $position): CalendarTab
    {
        $reflection = new ReflectionClass(CalendarTab::class);
        /** @var CalendarTab $tab */
        $tab = $reflection->newInstanceWithoutConstructor();
        $tab->initialize($name, $dashboard, $position);
        return $tab;
    }
}
