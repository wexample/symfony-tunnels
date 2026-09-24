<?php

namespace Wexample\SymfonyTunnels\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TunnelRobotsTest extends WebTestCase
{
    public function testTheTunnelsAreDisallowedInRobotsTxt(): void
    {
        $client = static::createClient();
        $client->request('GET', '/robots.txt');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(
            'Disallow: /tunnel/',
            $client->getResponse()->getContent()
        );
    }
}
