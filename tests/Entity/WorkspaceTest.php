<?php

namespace App\Tests\Entity;

use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\ValueObject\WorkspaceName;
use PHPUnit\Framework\TestCase;

class WorkspaceTest extends TestCase
{
    public function testWorkspaceInitialization(): void
    {
        $owner = $this->createMock(User::class);
        $name = new WorkspaceName('Secure Zone');

        $reflection = new \ReflectionClass(Workspace::class);
        /** @var Workspace $workspace */
        $workspace = $reflection->newInstanceWithoutConstructor();
        $workspace->initialize($name, $owner);

        $this->assertEquals('Secure Zone', $workspace->getName());
        $this->assertSame($owner, $workspace->getOwner());
        $this->assertNotNull($workspace->getId());
        $this->assertNotNull($workspace->getCreatedAt());
    }

    public function testRenameWorkspace(): void
    {
        $owner = $this->createMock(User::class);
        $name = new WorkspaceName('Initial Name');

        $reflection = new \ReflectionClass(Workspace::class);
        $workspace = $reflection->newInstanceWithoutConstructor();
        $workspace->initialize($name, $owner);

        $newName = new WorkspaceName('Renamed Name');
        $workspace->renameTo($newName);

        $this->assertEquals('Renamed Name', $workspace->getName());
    }
}
