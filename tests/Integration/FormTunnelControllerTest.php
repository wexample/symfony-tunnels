<?php

namespace Wexample\SymfonyTunnels\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyTunnels\Tests\Traits\DatabaseTestTrait;

/**
 * A step made of a form: shown, refused, accepted, and accepted from a modal.
 */
class FormTunnelControllerTest extends WebTestCase
{
    use DatabaseTestTrait;

    private const string BASE_PATH = '/tunnel/form-tunnel';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->createDatabaseSchema();
    }

    public function testTheFormIsShown(): void
    {
        $crawler = $this->client->request('GET', self::BASE_PATH . '/name');

        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('input[name="tunnel_test_form[name]"]')->count());
        // The tag has an id, for a submit button outside of it to point at.
        $this->assertSame(1, $crawler->filter('form#tunnel_test_form')->count());
    }

    public function testAnInvalidSubmissionStaysOnTheStep(): void
    {
        $this->client->request('GET', self::BASE_PATH . '/name');
        $this->client->request('POST', self::BASE_PATH . '/name', ['tunnel_test_form' => ['name' => '']]);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('[[STEP:name]]', $this->client->getResponse()->getContent());

        // Not done yet: the next step sends back here.
        $this->client->request('GET', self::BASE_PATH . '/thanks');
        $this->assertResponseRedirects(self::BASE_PATH . '/name');
    }

    public function testAValidSubmissionMovesOnAndIsRemembered(): void
    {
        $this->client->request('GET', self::BASE_PATH . '/name');
        $this->client->request('POST', self::BASE_PATH . '/name', ['tunnel_test_form' => ['name' => 'Ada']]);

        $this->assertResponseRedirects(self::BASE_PATH . '/thanks');

        $this->client->followRedirect();
        $this->assertStringContainsString('[[STEP:thanks]]', $this->client->getResponse()->getContent());

        // Coming back shows what was entered.
        $crawler = $this->client->request('GET', self::BASE_PATH . '/name');
        $this->assertSame('Ada', $crawler->filter('input[name="tunnel_test_form[name]"]')->attr('value'));
    }

    public function testAValidSubmissionFromAModalRedirectsWithinIt(): void
    {
        $this->client->request('GET', self::BASE_PATH . '/name', ['__layout' => 'modal']);
        $this->client->xmlHttpRequest(
            'POST',
            self::BASE_PATH . '/name?__layout=modal',
            ['tunnel_test_form' => ['name' => 'Ada']]
        );

        $this->assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame(AbstractFormProcessor::ACTION_EMBED_REDIRECT, $payload['action']['type']);
        $this->assertSame(self::BASE_PATH . '/thanks?__layout=modal', $payload['action']['url']);
    }
}
