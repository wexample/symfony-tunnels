<?php

namespace Wexample\SymfonyTunnels\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Class\TunnelNavigationItem;
use Wexample\SymfonyTunnels\Entity\TunnelSession;
use Wexample\SymfonyTunnels\Helper\TunnelTreeHelper;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\InMemoryTunnelSessionStorage;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepFive;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepFour;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepOne;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepThree;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepThreeBis;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepTwo;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\TestTunnelManagerService;

class TunnelTreeHelperTest extends TestCase
{
    private TunnelCursor $entrypoint;

    protected function setUp(): void
    {
        $requestStack = new RequestStack();
        $stepFour = new StepFour($requestStack);

        $manager = new TestTunnelManagerService(
            new InMemoryTunnelSessionStorage(),
            new StepOne(
                new StepTwo(
                    new StepThree($stepFour),
                    new StepThreeBis($stepFour, new StepFive()),
                    $requestStack
                )
            )
        );

        $manager->setSession(new TunnelSession());
        $this->entrypoint = $manager->createEntrypoint();
    }

    public function testEveryLeafEndsAPath(): void
    {
        $paths = TunnelTreeHelper::buildPaths($this->entrypoint);

        // Three step two, each opening step three (one leaf) and three step
        // three bis (two leaves each).
        $this->assertCount(21, $paths);
        $this->assertArrayHasKey(TunnelTreeHelper::PATH_INDEX_ROOT, $paths);

        foreach ($paths as $path) {
            $this->assertSame($this->entrypoint, $path[0]);
            $this->assertTrue(end($path)->isLast());
        }
    }

    public function testTheMapHasOneRowPerStepAndOptions(): void
    {
        $sections = TunnelTreeHelper::buildSections($this->entrypoint);

        $rows = array_map(
            static fn ($section): string => $section->cursor->step::getName(),
            $sections
        );

        // One step one, three step two, one step three, three step three bis,
        // one step four and one step five.
        $this->assertCount(10, $rows);
        $this->assertSame(StepOne::STEP_NAME, $rows[0]);

        // Every path reads its sections in the order the map gives them.
        $positions = array_flip(
            array_map(
                static fn ($section): string => $section->cursor->buildRelativeHash(),
                $sections
            )
        );

        foreach (TunnelTreeHelper::buildPaths($this->entrypoint) as $path) {
            $pathPositions = array_map(
                static fn (TunnelCursor $cursor): int => $positions[$cursor->buildRelativeHash()],
                $path
            );

            $sorted = $pathPositions;
            sort($sorted);
            $this->assertSame($sorted, $pathPositions);
        }
    }

    public function testFromTheStartOnlySharedStepsAreKnown(): void
    {
        $items = TunnelTreeHelper::buildKnownNextSteps($this->entrypoint, $this->entrypoint);

        $this->assertSame(
            [StepOne::STEP_NAME, StepTwo::STEP_NAME, null],
            $this->itemNames($items)
        );

        // Step two stands for three cursors: there is no single one to link to.
        $this->assertNull($items[1]->linkCursor);
    }

    public function testOnABranchTheWholePathIsKnownButNotClickableAhead(): void
    {
        $stepThree = $this->entrypoint->findFirstNext()->findFirstNextByStep(StepThree::class);

        $items = TunnelTreeHelper::buildKnownNextSteps($this->entrypoint, $stepThree);

        $this->assertSame(
            [StepOne::STEP_NAME, StepTwo::STEP_NAME, StepThree::STEP_NAME, StepFour::STEP_NAME],
            $this->itemNames($items)
        );

        // Behind: reachable. Ahead: not before the current step is done.
        $this->assertSame($this->entrypoint, $items[0]->linkCursor);
        $this->assertNull($items[3]->linkCursor);
    }

    /**
     * @param array<TunnelNavigationItem|null> $items
     */
    private function itemNames(array $items): array
    {
        return array_map(
            static fn (?TunnelNavigationItem $item): ?string => $item?->groupIdentifier,
            $items
        );
    }
}
