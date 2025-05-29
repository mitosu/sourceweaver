<?php

namespace App\Factory;

use App\Entity\Workspace;
use App\Entity\User;
use App\Entity\WorkspaceMembership;
use ReflectionClass;

class WorkspaceMembershipFactory
{
    public static function create(Workspace $workspace, User $user, string $role): WorkspaceMembership
    {
        $reflection = new ReflectionClass(WorkspaceMembership::class);
        /** @var WorkspaceMembership $membership */
        $membership = $reflection->newInstanceWithoutConstructor();
        $membership->initialize($workspace, $user, $role);
        return $membership;
    }
}
