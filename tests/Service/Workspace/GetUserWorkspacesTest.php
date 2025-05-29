<?php

namespace App\Tests\Service\Workspace;

use App\Service\Workspace\GetUserWorkspaces;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMembership;
use App\Repository\WorkspaceMembershipRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class GetUserWorkspacesTest extends TestCase
{
    public function testReturnsWorkspacesForUser(): void
    {
        $user = $this->createMock(User::class);

        $workspace1 = $this->createConfiguredMock(Workspace::class, [
            'getId' => Uuid::v4(),
            'getName' => 'Workspace One',
        ]);

        $workspace2 = $this->createConfiguredMock(Workspace::class, [
            'getId' => Uuid::v4(),
            'getName' => 'Workspace Two',
        ]);

        $membership1 = $this->createConfiguredMock(WorkspaceMembership::class, [
            'getWorkspace' => $workspace1,
        ]);

        $membership2 = $this->createConfiguredMock(WorkspaceMembership::class, [
            'getWorkspace' => $workspace2,
        ]);

        $repo = $this->createMock(WorkspaceMembershipRepository::class);
        $repo->expects($this->once())
            ->method('findBy')
            ->with(['user' => $user])
            ->willReturn([$membership1, $membership2]);

        $service = new GetUserWorkspaces($repo);
        $result = $service($user);

        $this->assertCount(2, $result);
        $this->assertEquals('Workspace One', $result[0]['name']);
        $this->assertEquals('Workspace Two', $result[1]['name']);
    }
}
