<?php
namespace App\Tests\Entity;

use App\Entity\WorkspaceMembership;
use App\Entity\User;
use App\Entity\Workspace;
use PHPUnit\Framework\TestCase;

class WorkspaceMembershipTest extends TestCase
{
    public function testMembershipInitialization(): void
    {
        $user = $this->createMock(User::class);
        $workspace = $this->createMock(Workspace::class);

        $reflection = new \ReflectionClass(WorkspaceMembership::class);
        /** @var WorkspaceMembership $membership */
        $membership = $reflection->newInstanceWithoutConstructor();
        $membership->initialize($workspace, $user, 'ADMIN');

        $this->assertSame($user, $membership->getUser());
        $this->assertSame($workspace, $membership->getWorkspace());
        $this->assertEquals('ADMIN', $membership->getRole());
        $this->assertNotNull($membership->getJoinedAt());
    }
}
