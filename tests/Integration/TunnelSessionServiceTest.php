<?php

namespace Wexample\SymfonyTunnels\Tests\Integration;

use DateTime;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Entity\TunnelSession;
use Wexample\SymfonyTunnels\Enum\TunnelSessionStatus;
use Wexample\SymfonyTunnels\Repository\TunnelSessionRepository;
use Wexample\SymfonyTunnels\Service\TunnelSessionService;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepFive;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepFour;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepOne;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepThree;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepThreeBis;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepTwo;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\TestTunnelManagerService;
use Wexample\SymfonyTunnels\Tests\Traits\DatabaseTestTrait;

class TunnelSessionServiceTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private const string TUNNEL_NAME = 'test';

    private TunnelSessionService $sessionService;

    private TunnelSessionRepository $sessionRepository;

    private StepFour $stepFour;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->createDatabaseSchema();

        $this->sessionService = self::getContainer()->get(TunnelSessionService::class);
        $this->sessionRepository = self::getContainer()->get(TunnelSessionRepository::class);
        $this->stepFour = new StepFour(new RequestStack());
    }

    public function testTheSameSessionIsReusedWithinItsLifetime(): void
    {
        $session = $this->sessionService->findOrCreateSession(self::TUNNEL_NAME);

        $this->assertSame(
            $session->getId(),
            $this->sessionService->findOrCreateSession(
                self::TUNNEL_NAME,
                browserSessionId: (string) $session->getId()
            )->getId()
        );
    }

    public function testAnExpiredSessionIsReplacedByANewOne(): void
    {
        $session = $this->sessionService->findOrCreateSession(self::TUNNEL_NAME);
        $session->setDateCreated(new DateTime('-2 days'));
        $this->sessionRepository->save($session);

        $this->assertNotSame(
            $session->getId(),
            $this->sessionService->findOrCreateSession(
                self::TUNNEL_NAME,
                browserSessionId: (string) $session->getId()
            )->getId()
        );
    }

    public function testASessionOfAnotherTunnelOrAnotherUserIsNotReused(): void
    {
        $session = $this->sessionService->findOrCreateSession(
            self::TUNNEL_NAME,
            userIdentifier: 'alice@example.com'
        );

        $this->assertNotSame(
            $session->getId(),
            $this->sessionService->findOrCreateSession(
                'another-tunnel',
                browserSessionId: (string) $session->getId(),
                userIdentifier: 'alice@example.com'
            )->getId()
        );

        $this->assertNotSame(
            $session->getId(),
            $this->sessionService->findOrCreateSession(
                self::TUNNEL_NAME,
                browserSessionId: (string) $session->getId(),
                userIdentifier: 'bob@example.com'
            )->getId()
        );
    }

    public function testResumingByHashChecksTheTunnelAndTheUser(): void
    {
        $session = $this->sessionService->findOrCreateSession(
            self::TUNNEL_NAME,
            userIdentifier: 'alice@example.com'
        );

        $this->assertSame(
            $session->getId(),
            $this->sessionService->findOrCreateSession(
                self::TUNNEL_NAME,
                resumeHash: $session->getHash(),
                userIdentifier: 'alice@example.com'
            )->getId()
        );

        $this->assertNotSame(
            $session->getId(),
            $this->sessionService->findOrCreateSession(
                self::TUNNEL_NAME,
                resumeHash: $session->getHash(),
                userIdentifier: 'bob@example.com'
            )->getId()
        );
    }

    public function testACompletedSessionIsStartedOverWhenEnteringAStep(): void
    {
        $manager = $this->createManager();
        $session = $this->sessionService->findOrCreateSession(self::TUNNEL_NAME);
        $manager->setSession($session);
        $cursor = $manager->createEntrypoint();

        $this->assertSame($session, $this->sessionService->initCursorSession($session, $cursor));

        $session->setStatus(TunnelSessionStatus::COMPLETED);
        $this->sessionRepository->save($session);

        $this->assertNotSame(
            $session->getId(),
            $this->sessionService->initCursorSession($session, $cursor)->getId()
        );
    }

    public function testThePurgeLetsEveryStepCleanUp(): void
    {
        $manager = $this->createManager();
        $manager->createEntrypoint();

        $expired = $this->sessionService->findOrCreateSession(self::TUNNEL_NAME);
        $expired->setDateCreated(new DateTime('-2 days'));
        $this->sessionRepository->save($expired);

        $kept = $this->sessionService->findOrCreateSession(self::TUNNEL_NAME);

        $this->assertSame(1, $this->sessionService->purgeExpiredSessions($manager));
        $this->assertCount(1, $this->stepFour->destroyedSessions);
        $this->assertSame($expired, $this->stepFour->destroyedSessions[0]);

        $this->assertNotNull($this->sessionRepository->find($kept->getId()));
        $this->assertNull($this->sessionRepository->find($expired->getId()));
    }

    public function testVariablesSurviveInTheirOwnScope(): void
    {
        $manager = $this->createManager();
        $manager->setSession($this->sessionService->findOrCreateSession(self::TUNNEL_NAME));
        $entrypoint = $manager->createEntrypoint();
        $stepTwo = $entrypoint->findFirstNext();

        $manager->setVariableValue('where', 'global');
        $entrypoint->setVariableValue('where', 'step-one');
        $stepTwo->setVariableValue('where', 'step-two');

        $this->assertSame('global', $manager->getVariableValue('where'));
        $this->assertSame('step-one', $entrypoint->getVariableValue('where'));
        $this->assertSame('step-two', $stepTwo->getVariableValue('where'));

        $this->assertTrue($manager->removeVariable('where'));
        $this->assertNull($manager->getVariableValue('where'));
        $this->assertSame('step-one', $entrypoint->getVariableValue('where'));

        $this->assertSame(1, $manager->resetCursor($entrypoint));
        $this->assertNull($entrypoint->getVariableValue('where'));
        $this->assertSame('step-two', $stepTwo->getVariableValue('where'));
    }

    public function testWalkingTheTunnelIsRememberedAcrossManagers(): void
    {
        $manager = $this->createManager();
        $session = $this->sessionService->findOrCreateSession(self::TUNNEL_NAME);
        $manager->setSession($session);
        $entrypoint = $manager->createEntrypoint();

        $entrypoint->step->initAsCurrentStep($entrypoint);
        $this->assertTrue($entrypoint->isComplete());
        $this->assertSame($entrypoint->hash, $session->getLastAccessedCursorHash());

        // A later request rebuilds everything and finds the flow where it was.
        $other = $this->createManager();
        $other->setSession($this->sessionRepository->find($session->getId()));
        $otherEntrypoint = $other->createEntrypoint();

        $this->assertSame($entrypoint->hash, $otherEntrypoint->hash);
        $this->assertTrue($otherEntrypoint->isComplete());
    }

    public function testOpeningValuesAreCheckedAndStored(): void
    {
        $manager = $this->createManager();
        $session = $this->sessionService->findOrCreateSession(self::TUNNEL_NAME);
        $manager->setSession($session);

        $entity = $this->sessionService->findOrCreateSession('some-other-tunnel');

        $manager->setInitialVariables([
            'label' => 'a label',
            'entity' => $entity,
        ]);

        $this->assertSame('a label', $manager->getVariableValue('label'));
        $this->assertSame('a label', $manager->initialisedLabel);
        // A missing value falls back on the default declared for it.
        $this->assertSame(1, $manager->getVariableValue('count'));
        // An entity is kept as its identifier.
        $this->assertSame($entity->getId()->toRfc4122(), $manager->getVariableValue('entity'));

        $stored = $this->sessionService->findInitialVariableValues($session);
        $this->assertSame(
            ['label', 'entity', 'count'],
            array_keys($stored)
        );
    }

    public function testOpeningValuesAreRebuiltFromTheSession(): void
    {
        $manager = $this->createManager();
        $session = $this->sessionService->findOrCreateSession(self::TUNNEL_NAME);
        $manager->setSession($session);

        $entity = $this->sessionService->findOrCreateSession('some-other-tunnel');
        $manager->setInitialVariables(['label' => 'a label', 'entity' => $entity]);

        $resumed = $this->createManager();
        $resumed->entitiesById = [$entity->getId()->toRfc4122() => $entity];
        $resumed->setSession($this->sessionRepository->find($session->getId()));
        $resumed->autoInitVariables(
            $this->sessionService->findInitialVariableValues($session)
        );

        $this->assertSame('a label', $resumed->initialisedLabel);
    }

    public function testAMissingOrMistypedOpeningValueIsRefused(): void
    {
        $manager = $this->createManager();
        $manager->setSession($this->sessionService->findOrCreateSession(self::TUNNEL_NAME));

        $this->expectExceptionMessage('Missing value for variable "label"');
        $manager->setInitialVariables([]);
    }

    public function testAnUnexpectedOpeningValueIsRefused(): void
    {
        $manager = $this->createManager();
        $manager->setSession($this->sessionService->findOrCreateSession(self::TUNNEL_NAME));

        $this->expectExceptionMessage('Unexpected value, expected one of: label, count, entity');
        $manager->setInitialVariables(['label' => 'a label', 'unknown' => true]);
    }

    public function testAnOpeningValueOfTheWrongTypeIsRefused(): void
    {
        $manager = $this->createManager();
        $manager->setSession($this->sessionService->findOrCreateSession(self::TUNNEL_NAME));

        $this->expectExceptionMessage('Type mismatch, expected "string", got "integer"');
        $manager->setInitialVariables(['label' => 12]);
    }

    public function testTheCompletionFlagIsAPlainCursorVariable(): void
    {
        $manager = $this->createManager();
        $manager->setSession($this->sessionService->findOrCreateSession(self::TUNNEL_NAME));
        $entrypoint = $manager->createEntrypoint();

        $this->assertFalse($entrypoint->isComplete());

        $entrypoint->setComplete($entrypoint->findFirstNext());

        $this->assertTrue($entrypoint->isComplete());
        $this->assertSame(
            $entrypoint->findFirstNext()->hash,
            $manager->getVariableValue(TunnelCursor::VARIABLE_NAME_REDIRECTS_TO, $entrypoint)
        );
    }

    private function createManager(): TestTunnelManagerService
    {
        $requestStack = new RequestStack();

        return new TestTunnelManagerService(
            $this->sessionService,
            new StepOne(
                new StepTwo(
                    new StepThree($this->stepFour),
                    new StepThreeBis($this->stepFour, new StepFive()),
                    $requestStack
                )
            )
        );
    }
}
