<?php

namespace Wexample\SymfonyTunnels\Tests\Unit;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Wexample\SymfonyTunnels\WexampleSymfonyTunnelsBundle;

class KernelBootTest extends KernelTestCase
{
    public function testKernelBootsWithBundle(): void
    {
        self::bootKernel();

        $this->assertArrayHasKey(
            'WexampleSymfonyTunnelsBundle',
            self::$kernel->getBundles()
        );

        $this->assertInstanceOf(
            WexampleSymfonyTunnelsBundle::class,
            self::$kernel->getBundles()['WexampleSymfonyTunnelsBundle']
        );
    }
}
