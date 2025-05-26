<?php
namespace App\Factory;

use App\Entity\Dashboard;
use App\Entity\MainTableTab;
use App\Entity\KanbanTab;
use App\Entity\CalendarTab;
use App\Entity\Tab;

class TabFactory
{
    public static function mainTable(string $name, Dashboard $dashboard, int $position): MainTableTab
    {
        $tab = new MainTableTab();
        $tab->initialize($name, $dashboard, $position);
        return $tab;
    }

    public static function kanban(string $name, Dashboard $dashboard, int $position): KanbanTab
    {
        $tab = new KanbanTab();
        $tab->initialize($name, $dashboard, $position);
        return $tab;
    }

    public static function calendar(string $name, Dashboard $dashboard, int $position): CalendarTab
    {
        $tab = new CalendarTab();
        $tab->initialize($name, $dashboard, $position);
        return $tab;
    }
}
