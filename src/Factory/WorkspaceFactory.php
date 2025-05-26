<?php
namespace App\Factory;

use App\Entity\Workspace;
use App\Entity\User;

class WorkspaceFactory
{
    public static function create(string $name, User $owner): Workspace
    {
        $workspace = new Workspace();
        $workspace->initialize($name, $owner);
        return $workspace;
    }
}
