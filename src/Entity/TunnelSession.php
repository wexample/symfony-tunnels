<?php

namespace Wexample\SymfonyTunnels\Entity;

use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Random\RandomException;
use Wexample\Pseudocode\Attribute\PseudocodeExport;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Traits\HasDateCreatedTrait;
use Wexample\SymfonyTunnels\Enum\TunnelSessionStatus;
use Wexample\SymfonyTunnels\Repository\TunnelSessionRepository;

#[ORM\Entity(repositoryClass: TunnelSessionRepository::class)]
#[ORM\Table(name: 'tunnel_session')]
#[ORM\Index(fields: ['hash'])]
#[PseudocodeExport(inherited: true)]
class TunnelSession extends AbstractEntity
{
    use HasDateCreatedTrait;

    /**
     * Length of the hexadecimal resume hash, 16 random bytes.
     */
    public const int HASH_LENGTH = 32;

    #[ORM\Column(length: 60)]
    private ?string $tunnel = null;

    #[ORM\Column(enumType: TunnelSessionStatus::class)]
    private TunnelSessionStatus $status = TunnelSessionStatus::OPENED;

    #[ORM\Column(length: self::HASH_LENGTH, unique: true)]
    private string $hash;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $lastAccessedCursorHash = null;

    /**
     * The security identifier of the visitor, when there is one. The package
     * deliberately holds no relation to the application user entity: not every
     * application installing it has one.
     */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $userIdentifier = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipV4 = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $browserData = null;

    /**
     * @var Collection<int, TunnelSessionVariable>
     */
    #[ORM\OneToMany(mappedBy: 'tunnelSession', targetEntity: TunnelSessionVariable::class, orphanRemoval: true)]
    private Collection $tunnelSessionVariables;

    /**
     * @throws RandomException
     */
    public function __construct()
    {
        parent::__construct();

        $this->tunnelSessionVariables = new ArrayCollection();
        $this->hash = bin2hex(random_bytes(self::HASH_LENGTH / 2));
    }

    public function getTunnel(): ?string
    {
        return $this->tunnel;
    }

    public function setTunnel(string $tunnel): self
    {
        $this->tunnel = $tunnel;

        return $this;
    }

    public function getStatus(): TunnelSessionStatus
    {
        return $this->status;
    }

    public function setStatus(TunnelSessionStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function hasStatus(TunnelSessionStatus $status): bool
    {
        return $this->status === $status;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): self
    {
        $this->hash = $hash;

        return $this;
    }

    public function getLastAccessedCursorHash(): ?string
    {
        return $this->lastAccessedCursorHash;
    }

    public function setLastAccessedCursorHash(?string $lastAccessedCursorHash): self
    {
        $this->lastAccessedCursorHash = $lastAccessedCursorHash;

        return $this;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(?string $userIdentifier): self
    {
        $this->userIdentifier = $userIdentifier;

        return $this;
    }

    public function getIpV4(): ?string
    {
        return $this->ipV4;
    }

    public function setIpV4(?string $ipV4): self
    {
        $this->ipV4 = $ipV4;

        return $this;
    }

    public function getBrowserData(): ?string
    {
        return $this->browserData;
    }

    public function setBrowserData(?string $browserData): self
    {
        $this->browserData = $browserData;

        return $this;
    }

    /**
     * @return Collection<int, TunnelSessionVariable>
     */
    public function getTunnelSessionVariables(): Collection
    {
        return $this->tunnelSessionVariables;
    }

    public function addTunnelSessionVariable(TunnelSessionVariable $tunnelSessionVariable): self
    {
        if (!$this->tunnelSessionVariables->contains($tunnelSessionVariable)) {
            $this->tunnelSessionVariables->add($tunnelSessionVariable);
            $tunnelSessionVariable->setTunnelSession($this);
        }

        return $this;
    }

    public function removeTunnelSessionVariable(TunnelSessionVariable $tunnelSessionVariable): self
    {
        $this->tunnelSessionVariables->removeElement($tunnelSessionVariable);

        return $this;
    }

    /**
     * A session is expired once it has been opened for too long. Completed and
     * pending sessions are kept: something else decides when they are done.
     */
    public function isExpired(DateTimeInterface $expirationDate): bool
    {
        return $this->hasStatus(TunnelSessionStatus::OPENED)
            && $this->getDateCreated() < $expirationDate;
    }
}
