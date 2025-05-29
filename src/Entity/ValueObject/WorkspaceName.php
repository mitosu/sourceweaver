<?php

namespace App\Entity\ValueObject;

class WorkspaceName
{
    public function __construct(private readonly string $value)
    {
        $this->validate($value);
    }

    private function validate(string $value): void
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new \InvalidArgumentException("El nombre del workspace no puede estar vacío.");
        }

        if (mb_strlen($trimmed) < 3) {
            throw new \InvalidArgumentException("El nombre del workspace debe tener al menos 3 caracteres.");
        }

        if (mb_strlen($trimmed) > 100) {
            throw new \InvalidArgumentException("El nombre del workspace no puede superar los 100 caracteres.");
        }

        if (preg_match('/(--|;|\/\*|\*\/|DROP|SELECT|INSERT|DELETE|UPDATE)/i', $trimmed)) {
            throw new \InvalidArgumentException("El nombre del workspace contiene patrones no permitidos.");
        }

        if (!preg_match('/^[\p{L}\p{N}\s\-_.,()]+$/u', $trimmed)) {
            throw new \InvalidArgumentException("El nombre del workspace contiene caracteres no válidos.");
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
