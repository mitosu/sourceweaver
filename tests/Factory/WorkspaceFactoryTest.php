<?php

namespace App\Tests\Factory;

use App\Entity\User;
use App\Factory\WorkspaceFactory;
use App\Entity\ValueObject\WorkspaceName;
use PHPUnit\Framework\TestCase;

class WorkspaceFactoryTest extends TestCase
{
    public function testWorkspaceCreation(): void
    {
        $user = $this->createMock(User::class);
        $name = new WorkspaceName('Demo Workspace');
        $workspace = WorkspaceFactory::create($name, $user);

        $this->assertSame('Demo Workspace', $workspace->getName());
        $this->assertSame($user, $workspace->getOwner());
        $this->assertNotNull($workspace->getId());
    }
}
