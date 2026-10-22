<?php

namespace Wexample\SymfonyTunnels\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Entity\TunnelSession;
use Wexample\SymfonyTunnels\Exception\TunnelCycleException;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Cycle\CycleTunnelManagerService;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Cycle\SelfReferencingStep;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\InMemoryTunnelSessionStorage;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepFive;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepFour;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepOne;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepThree;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepThreeBis;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepTwo;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\TestTunnelManagerService;

class TunnelNavigationTest extends TestCase
{
    private TestTunnelManagerService $manager;

    private TunnelCursor $stepOne;

    private TunnelCursor $stepTwo;

    protected function setUp(): void
    {
        $requestStack = new RequestStack();
        $stepFour = new StepFour($requestStack);

        $this->manager = new TestTunnelManagerService(
            new InMemoryTunnelSessionStorage(),
            new StepOne(
                new StepTwo(
                    new StepThree($stepFour),
                    new StepThreeBis($stepFour, new StepFive()),
                    $requestStack
                )
            )
        );

        $this->manager->setSession(new TunnelSession());
        $this->stepOne = $this->manager->createEntrypoint();
        $this->stepTwo = $this->stepOne->findFirstNext();
    }

    public function testCompleteOnInitRemembersTheOnlyFollowingCursor(): void
    {
        $stepThree = $this->stepTwo->findFirstNextByStep(StepThree::class);
        $stepFour = $stepThree->findFirstNext();

        $stepThree->step->initAsCurrentStep($stepThree);

        $this->assertTrue($stepThree->isComplete());
        $this->assertSame($stepFour, $stepThree->getRedirectsToCursor());
    }

    public function testCompleteOnInitWithSeveralBranchesRemembersNone(): void
    {
        $this->stepOne->step->initAsCurrentStep($this->stepOne);

        $this->assertTrue($this->stepOne->isComplete());
        $this->assertNull($this->stepOne->getRedirectsToCursor());
    }

    public function testCompleteOnNextInitWaitsForTheFollowingStep(): void
    {
        $this->stepTwo->step->initAsCurrentStep($this->stepTwo);
        $this->assertFalse($this->stepTwo->isComplete());

        $stepThree = $this->stepTwo->findFirstNextByStep(StepThree::class);
        $stepThree->step->initAsCurrentStep($stepThree);

        $this->assertTrue($this->stepTwo->isComplete());
        $this->assertSame($stepThree, $this->stepTwo->getRedirectsToCursor());
    }

    public function testManualStrategyNeverCompletesOnItsOwn(): void
    {
        $threeBis = $this->stepTwo->findFirstNextByName('typed-default');
        $stepFive = $threeBis->findFirstNextByStep(StepFive::class);

        $stepFive->step->initAsCurrentStep($stepFive);

        $this->assertFalse($stepFive->isComplete());
    }

    public function testRedirectSendsBackToTheFirstIncompleteAncestor(): void
    {
        $stepThree = $this->stepTwo->findFirstNextByStep(StepThree::class);
        $stepFour = $stepThree->findFirstNext();

        // Nothing walked yet: the visitor belongs at the top.
        $this->assertSame($this->stepOne, $stepFour->step->needsRedirect($stepFour));

        $this->stepOne->step->initAsCurrentStep($this->stepOne);
        $this->stepTwo->step->initAsCurrentStep($this->stepTwo);
        $stepThree->step->initAsCurrentStep($stepThree);

        $this->assertNull($stepFour->step->needsRedirect($stepFour));
    }

    public function testAStepCompletedByItsSuccessorDoesNotBlockIt(): void
    {
        $this->stepOne->step->initAsCurrentStep($this->stepOne);
        $stepThree = $this->stepTwo->findFirstNextByStep(StepThree::class);

        // Step two is incomplete, but it is step three that completes it.
        $this->assertFalse($this->stepTwo->isComplete());
        $this->assertNull($stepThree->step->needsRedirect($stepThree));
    }

    public function testGoingBackResetsTheAbandonedBranch(): void
    {
        $typedDefault = $this->stepTwo->findFirstNextByName('typed-default');
        $typedSession = $this->stepTwo->findFirstNextByName('typed-session');

        $this->stepOne->step->initAsCurrentStep($this->stepOne);
        $this->stepTwo->step->initAsCurrentStep($this->stepTwo);
        $typedDefault->step->initAsCurrentStep($typedDefault);

        $this->assertTrue($typedDefault->isComplete());
        $this->assertTrue($this->manager->getVariableValue(StepThreeBis::VARIABLE_NAME_GLOBAL));
        $this->assertSame($typedDefault, $this->stepTwo->getRedirectsToCursor());

        // Back to the branching step, then down the other branch.
        $this->stepTwo->step->initAsCurrentStep($this->stepTwo);

        $this->assertFalse($typedDefault->isComplete());
        $this->assertNull($this->manager->getVariableValue(StepThreeBis::VARIABLE_NAME_GLOBAL));
        $this->assertNull($this->stepTwo->getRedirectsToCursor());

        $typedSession->step->initAsCurrentStep($typedSession);
        $this->assertSame($typedSession, $this->stepTwo->getRedirectsToCursor());
    }

    public function testCursorVariablesAreDroppedOnReset(): void
    {
        $this->stepTwo->step->initAsCurrentStep($this->stepTwo);
        $this->assertTrue($this->stepTwo->getVariableValue(StepTwo::VARIABLE_NAME));

        $this->manager->resetCursor($this->stepTwo);

        $this->assertNull($this->stepTwo->getVariableValue(StepTwo::VARIABLE_NAME));
    }

    public function testDirectAccessIsVetoedByACursorInBetween(): void
    {
        $stepThree = $this->stepTwo->findFirstNextByStep(StepThree::class);
        $stepFour = $stepThree->findFirstNext();

        $this->assertTrue($stepFour->step->allowDirectAccess($this->stepTwo, $stepFour));

        $stepThree->step->initAsCurrentStep($stepThree);

        // Step three is done with, and refuses to be walked back through.
        $this->assertFalse($stepFour->step->allowDirectAccess($this->stepTwo, $stepFour));
    }

    public function testMovingForwardRequiresCompletingWhereWeStand(): void
    {
        $stepThree = $this->stepTwo->findFirstNextByStep(StepThree::class);

        $this->stepOne->setComplete();
        $this->assertFalse($stepThree->step->allowDirectAccess($stepThree, $this->stepTwo));

        $this->stepTwo->setComplete();

        $this->assertTrue($stepThree->step->allowDirectAccess($stepThree, $this->stepTwo));
    }

    public function testAStepTwoLevelsAheadIsReachableOnlyOnceTheOneBetweenIsDone(): void
    {
        $stepThree = $this->stepTwo->findFirstNextByStep(StepThree::class);
        $stepFour = $stepThree->findFirstNext();

        $this->stepOne->setComplete();
        $this->stepTwo->setComplete();
        $this->assertFalse($stepFour->step->allowDirectAccess($stepFour, $this->stepTwo));

        $stepThree->setComplete();
        $this->assertTrue($stepFour->step->allowDirectAccess($stepFour, $this->stepTwo));
    }

    public function testAStepReachableFromItselfIsRefused(): void
    {
        $manager = new CycleTunnelManagerService(
            new InMemoryTunnelSessionStorage(),
            new SelfReferencingStep()
        );

        $this->expectException(TunnelCycleException::class);

        $manager->createEntrypoint();
    }
}
