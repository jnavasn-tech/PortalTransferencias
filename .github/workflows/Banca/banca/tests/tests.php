<?php
use PHPUnit\Framework\TestCase;

class EjemploTest extends TestCase
{
    public function testSumaBasica()
    {
        $resultado = 2 + 2;
        $this->assertSame(4, $resultado);
    }
}
