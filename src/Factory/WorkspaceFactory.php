<?php

namespace App\Factory;

use App\Entity\Workspace;
use App\Entity\User;
use ReflectionClass;

class WorkspaceFactory
{
    public static function create(string $name, User $owner): Workspace
    {
        $reflection = new ReflectionClass(Workspace::class);
        /** @var Workspace $workspace */
        $workspace = $reflection->newInstanceWithoutConstructor();
        $workspace->initialize($name, $owner);
        return $workspace;
    }
}
