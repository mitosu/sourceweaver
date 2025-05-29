<?php
namespace App\Tests\Entity\ValueObject;

use App\Entity\ValueObject\TabName;
use PHPUnit\Framework\TestCase;

class TabNameTest extends TestCase
{
    public function testValidName(): void
    {
        $name = new TabName('Kanban Board');
        $this->assertSame('Kanban Board', (string) $name);
    }

    public function testEmptyNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TabName('');
    }

    public function testTooShortNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TabName('A');
    }

    public function testTooLongNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TabName(str_repeat('B', 101));
    }

    public function testSqlInjectionPatternThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TabName('SELECT * FROM users;');
    }
}
