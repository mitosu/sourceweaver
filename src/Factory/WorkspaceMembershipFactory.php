<?php
namespace App\Factory;

use App\Entity\Workspace;
use App\Entity\User;
use App\Entity\WorkspaceMembership;

class WorkspaceMembershipFactory
{
    public static function create(Workspace $workspace, User $user, string $role): WorkspaceMembership
    {
        $membership = new WorkspaceMembership();
        $membership->initialize($workspace, $user, $role);
        return $membership;
    }
}
