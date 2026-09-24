<?php

namespace Wexample\SymfonyTunnels\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Entity\TunnelSession;
use Wexample\SymfonyTunnels\Enum\TunnelCursorPosition;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\InMemoryTunnelVariableStorage;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepFive;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepFour;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepOne;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepThree;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepThreeBis;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepTwo;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\TestTunnelManagerService;

class TunnelTreeTest extends TestCase
{
    private TestTunnelManagerService $manager;

    private TunnelCursor $entrypoint;

    protected function setUp(): void
    {
        $this->manager = $this->createManager();
        $this->manager->setSession(new TunnelSession());
        $this->entrypoint = $this->manager->createEntrypoint();
    }

    public function testEntrypointOpensThreeVariantsOfTheSameStep(): void
    {
        $this->assertSame(StepOne::STEP_NAME, $this->entrypoint->step::getName());
        $this->assertTrue($this->entrypoint->isFirst());
        $this->assertCount(3, $this->entrypoint->next);

        foreach ($this->entrypoint->next as $cursor) {
            $this->assertSame(StepTwo::STEP_NAME, $cursor->step::getName());
        }

        $optionsPerCursor = array_values(
            array_map(
                static fn (TunnelCursor $cursor): array => $cursor->options,
                $this->entrypoint->next
            )
        );

        $this->assertSame([
            [],
            ['lorem' => 'ipsum', 'name' => 'selected-if-step-three-direct-access'],
            ['sit' => 'amet'],
        ], $optionsPerCursor);
    }

    public function testStepThreeBisHasThreeNamedVariants(): void
    {
        $stepTwo = $this->entrypoint->findFirstNext();

        $names = [];
        foreach ($stepTwo->next as $cursor) {
            if ($cursor->step::getName() === StepThreeBis::STEP_NAME) {
                $names[] = $cursor->name;
            }
        }

        $this->assertSame(
            ['typed-default', 'typed-query-string', 'typed-session'],
            $names
        );
    }

    public function testCursorHashesAreStableAcrossRebuilds(): void
    {
        $hashes = array_keys($this->manager->getCursors());

        $other = $this->createManager();
        $other->setSession(new TunnelSession());
        $other->createEntrypoint();

        $this->assertSame($hashes, array_keys($other->getCursors()));
    }

    public function testTheSameStepReachedTwiceGivesTwoCursors(): void
    {
        $stepFourCursors = array_filter(
            $this->manager->getCursors(),
            static fn (TunnelCursor $cursor): bool => $cursor->step::getName() === StepFour::STEP_NAME
        );

        // Three step two variants, each opening step four under step three and
        // under each of the three step three bis.
        $this->assertCount(12, $stepFourCursors);

        $relativeHashes = array_unique(
            array_map(
                static fn (TunnelCursor $cursor): string => $cursor->buildRelativeHash(),
                $stepFourCursors
            )
        );

        // They are the same step with the same options: only the path differs.
        $this->assertCount(1, $relativeHashes);
    }

    public function testCursorLookupAndPositions(): void
    {
        $stepTwo = $this->entrypoint->findFirstNext();
        $stepThree = $stepTwo->findFirstNextByStep(StepThree::class);
        $stepFour = $stepThree->findFirstNext();

        $this->assertSame($stepThree, $this->manager->getCursor($stepThree->hash));
        $this->assertNull($this->manager->getCursor('unknown-hash'));

        $this->assertSame(0, $this->entrypoint->distanceFromRoot());
        $this->assertSame(3, $stepFour->distanceFromRoot());
        $this->assertTrue($stepFour->isLast());

        $this->assertSame(TunnelCursorPosition::SAME, $stepThree->getPosition($stepThree));
        $this->assertSame(TunnelCursorPosition::PREVIOUS, $this->entrypoint->getPosition($stepThree));
        $this->assertSame(TunnelCursorPosition::NEXT, $stepFour->getPosition($stepThree));

        $otherBranch = $stepTwo->findFirstNextByName('typed-session');
        $this->assertSame(TunnelCursorPosition::UNRELATED, $otherBranch->getPosition($stepThree));
    }

    public function testOptionMatchingRequiresEveryOption(): void
    {
        $stepTwo = $this->entrypoint->findFirstNext();

        $sessionVariant = $stepTwo->findFirstNextByOptions([
            'step-three-test-type' => 'session',
            'name' => 'typed-session',
        ]);
        $this->assertSame('typed-session', $sessionVariant->name);

        // A single matching option is not enough to pick a variant.
        $this->assertNull(
            $stepTwo->findFirstNextByOptions([
                'step-three-test-type' => 'session',
                'name' => 'typed-default',
            ])
        );

        $this->assertTrue($sessionVariant->hasSameOptions([
            'step-three-test-type' => 'session',
            'name' => 'typed-session',
        ]));
        $this->assertFalse($sessionVariant->hasSameOptions(['step-three-test-type' => 'session']));
        $this->assertTrue($sessionVariant->hasOptions(['step-three-test-type' => 'session']));
    }

    public function testForEachCursorBetweenStopsAtTheLimit(): void
    {
        $stepTwo = $this->entrypoint->findFirstNext();
        $stepThree = $stepTwo->findFirstNextByStep(StepThree::class);
        $stepFour = $stepThree->findFirstNext();

        $visited = [];
        $stepFour->forEachCursorBetween(
            $this->entrypoint,
            static function (TunnelCursor $cursor) use (&$visited): void {
                $visited[] = $cursor->step::getName();
            }
        );

        // The walk covers what separates the two cursors, both ends excluded.
        $this->assertSame(
            [StepThree::STEP_NAME, StepTwo::STEP_NAME],
            $visited
        );
    }

    public function testBranchSelectionUsesTheStepOverride(): void
    {
        $stepTwo = $this->entrypoint->findFirstNext();
        $threeBis = $stepTwo->findFirstNextByName('typed-default');

        $selected = $threeBis->getNextCursor();

        $this->assertSame(StepFive::STEP_NAME, $selected->step::getName());
        $this->assertSame(
            [StepFive::OPTION_NAME_VARIANT_ID => StepThreeBis::VARIANT_ID_FIRST],
            $selected->options
        );

        // Without an override, the first declared cursor wins.
        $this->assertSame(
            StepFour::STEP_NAME,
            $stepTwo->findFirstNextByStep(StepThree::class)->getNextCursor()->step::getName()
        );
    }

    private function createManager(): TestTunnelManagerService
    {
        $requestStack = new RequestStack();
        $stepFour = new StepFour($requestStack);
        $stepThreeBis = new StepThreeBis($stepFour, new StepFive());

        return new TestTunnelManagerService(
            new InMemoryTunnelVariableStorage(),
            new StepOne(
                new StepTwo(
                    new StepThree($stepFour),
                    $stepThreeBis,
                    $requestStack
                )
            )
        );
    }
}
