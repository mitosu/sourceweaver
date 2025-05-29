<?php

namespace App\Factory;

use App\Entity\Workspace;
use App\Entity\User;
use App\Entity\ValueObject\WorkspaceName;
use ReflectionClass;

class WorkspaceFactory
{
    public static function create(WorkspaceName $name, User $owner): Workspace
    {
        $reflection = new ReflectionClass(Workspace::class);
        /** @var Workspace $workspace */
        $workspace = $reflection->newInstanceWithoutConstructor();
        $workspace->initialize($name, $owner);
        return $workspace;
    }
}
