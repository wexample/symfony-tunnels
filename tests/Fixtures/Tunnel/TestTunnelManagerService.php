<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel;

use Wexample\SymfonyTunnels\Entity\TunnelSession;
use Wexample\SymfonyTunnels\Interface\TunnelSessionStorageInterface;
use Wexample\SymfonyTunnels\Service\AbstractTunnelManagerService;
use Wexample\SymfonyTunnels\Service\Step\AbstractTunnelStep;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepOne;

/**
 * The tunnel exercising every feature of the engine, ported from the one the
 * legacy code used as its executable specification.
 */
class TestTunnelManagerService extends AbstractTunnelManagerService
{
    public function __construct(
        TunnelSessionStorageInterface $variableStorage,
        private readonly StepOne $stepOne,
    ) {
        parent::__construct($variableStorage);
    }

    public static function getName(): string
    {
        return 'test';
    }

    public function getEntrypointStep(): AbstractTunnelStep
    {
        return $this->stepOne;
    }

    /**
     * Stands in for the repositories a real tunnel would load its entities
     * from, keyed by identifier.
     *
     * @var array<string, TunnelSession>
     */
    public array $entitiesById = [];

    public ?string $initialisedLabel = null;

    public function getInitVariablesConfig(): array
    {
        return [
            'label' => [
                'type' => 'string',
                'required' => true,
            ],
            'count' => [
                'type' => 'integer',
                'default' => 1,
            ],
            'entity' => [
                'type' => TunnelSession::class,
                'autoInit' => fn (string $id): ?TunnelSession => $this->entitiesById[$id] ?? null,
            ],
        ];
    }

    protected function initSessionVariable(string $name, mixed $value): void
    {
        if ($name === 'label') {
            $this->initialisedLabel = $value;
        }
    }
}
