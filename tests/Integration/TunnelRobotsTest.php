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
        $content = $client->getResponse()->getContent();

        // Each tunnel route by the fixed start of its path, wherever it is mounted.
        $this->assertStringContainsString('Disallow: /tunnel/test-tunnel/with/prefix/', $content);
        $this->assertStringContainsString('Disallow: /tunnel/form-tunnel/', $content);
        $this->assertStringContainsString('Disallow: /pages/form/', $content);
    }
}
