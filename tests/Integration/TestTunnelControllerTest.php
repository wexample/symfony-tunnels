<?php

namespace Wexample\SymfonyTunnels\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyTunnels\Tests\Traits\DatabaseTestTrait;

/**
 * Walks the test tunnel over HTTP, the way a visitor would.
 */
class TestTunnelControllerTest extends WebTestCase
{
    use DatabaseTestTrait;

    private const string BASE_PATH = '/tunnel/test-tunnel/with/prefix';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // The database lives in memory: a rebooted kernel would start on an
        // empty one at every request.
        $this->client->disableReboot();
        $this->createDatabaseSchema();
    }

    public function testTheTunnelRootRedirectsToTheEntrypoint(): void
    {
        $this->client->request('GET', self::BASE_PATH);

        $this->assertResponseRedirects(self::BASE_PATH . '/step-one');
    }

    public function testAnUnknownStepIsNotFound(): void
    {
        $this->client->request('GET', self::BASE_PATH . '/no-such-step');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDirectAccessSendsBackToTheFirstIncompleteStep(): void
    {
        $this->client->request('GET', self::BASE_PATH . '/step-four');

        $this->assertResponseRedirects(self::BASE_PATH . '/step-one');
    }

    public function testWalkingForwardThenBack(): void
    {
        $this->assertStep('step-one');
        $this->assertStep('step-two');
        $this->assertStep('step-three');
        $this->assertStep('step-four');

        // Back to the branching step, then down another branch picked by the
        // query string.
        $this->assertStep('step-two');
        $content = $this->assertStep(
            'step-three-bis',
            ['cursor-options' => ['step-three-test-type' => 'queryString']]
        );

        $this->assertStringContainsString('[[OPTION:step-three-test-type=queryString]]', $content);
    }

    public function testAVariantNamedByTheUrlIsReachedFromAnotherBranch(): void
    {
        $this->assertStep('step-one');
        $this->assertStep('step-two');
        $this->assertStep('step-three-bis', ['cursor-options' => ['step-three-test-type' => 'queryString']]);

        // The browser went back to step two from its cache: the server still
        // stands on the query string variant when the default one is asked for.
        $content = $this->assertStep('step-three-bis', ['cursor-options' => ['step-three-test-type' => 'default']]);

        $this->assertStringContainsString('[[OPTION:step-three-test-type=default]]', $content);
    }

    public function testAStepCanRedirectWithinTheTunnel(): void
    {
        $this->assertStep('step-one');
        $this->client->request('GET', self::BASE_PATH . '/step-two', ['step-two-complete' => 1]);

        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertStringStartsWith(self::BASE_PATH . '/step-three-bis?', $location);
        $this->assertStringContainsString('typed-session', urldecode($location));

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('[[OPTION:step-three-test-type=session]]', $this->client->getResponse()->getContent());
    }

    public function testAStepCanRedirectOutOfTheTunnel(): void
    {
        $this->assertStep('step-one');
        $this->assertStep('step-two');
        $this->assertStep('step-three');

        $this->client->request('GET', self::BASE_PATH . '/step-four', ['step-four-redirects' => 1]);

        $this->assertResponseRedirects('/');
    }

    public function testRedirectsKeepTheLayout(): void
    {
        $this->client->request('GET', self::BASE_PATH, ['__layout' => 'modal']);

        $this->assertResponseRedirects(self::BASE_PATH . '/step-one?__layout=modal');
    }

    private function assertStep(string $step, array $query = []): string
    {
        $this->client->request('GET', self::BASE_PATH . '/' . $step, $query);

        $this->assertResponseIsSuccessful(
            'Expected to stay on ' . $step . ', got a ' . $this->client->getResponse()->getStatusCode()
            . ' to ' . $this->client->getResponse()->headers->get('Location')
        );

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('[[STEP:' . $step . ']]', $content);

        return $content;
    }
}
