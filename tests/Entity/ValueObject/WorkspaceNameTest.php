<?php
namespace App\Tests\Entity\ValueObject;

use App\Entity\ValueObject\WorkspaceName;
use PHPUnit\Framework\TestCase;

class WorkspaceNameTest extends TestCase
{
    public function testValidName(): void
    {
        $name = new WorkspaceName('Project X');
        $this->assertSame('Project X', (string) $name);
    }

    public function testEmptyNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WorkspaceName('');
    }

    public function testTooShortNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WorkspaceName('A');
    }

    public function testTooLongNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WorkspaceName(str_repeat('A', 101));
    }

    public function testSqlInjectionPatternThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WorkspaceName('DROP TABLE workspace;');
    }
}
