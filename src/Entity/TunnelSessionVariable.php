<?php

namespace Wexample\SymfonyTunnels\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\Pseudocode\Attribute\PseudocodeExport;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Traits\HasDateCreatedTrait;
use Wexample\SymfonyHelpers\Entity\Traits\HasNameTrait;
use Wexample\SymfonyTunnels\Repository\TunnelSessionVariableRepository;

#[ORM\Entity(repositoryClass: TunnelSessionVariableRepository::class)]
#[ORM\Table(name: 'tunnel_session_variable')]
#[ORM\Index(fields: ['name'])]
#[PseudocodeExport(inherited: true)]
class TunnelSessionVariable extends AbstractEntity
{
    use HasDateCreatedTrait;
    use HasNameTrait;

    #[ORM\ManyToOne(inversedBy: 'tunnelSessionVariables')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TunnelSession $tunnelSession = null;

    /**
     * Stored as JSON: a tunnel variable holds data, never an object graph.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private mixed $value = null;

    /**
     * The cursor the variable belongs to, null for a variable global to the session.
     */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $cursorHash = null;

    /**
     * True for a variable given by the caller when the tunnel was opened, as
     * opposed to one the visitor produced by walking it.
     */
    #[ORM\Column]
    private bool $initial = false;

    public function getTunnelSession(): ?TunnelSession
    {
        return $this->tunnelSession;
    }

    public function setTunnelSession(?TunnelSession $tunnelSession): self
    {
        $this->tunnelSession = $tunnelSession;

        return $this;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getCursorHash(): ?string
    {
        return $this->cursorHash;
    }

    public function setCursorHash(?string $cursorHash): self
    {
        $this->cursorHash = $cursorHash;

        return $this;
    }

    public function isGlobal(): bool
    {
        return $this->cursorHash === null;
    }

    public function isInitial(): bool
    {
        return $this->initial;
    }

    public function setInitial(bool $initial): self
    {
        $this->initial = $initial;

        return $this;
    }
}
