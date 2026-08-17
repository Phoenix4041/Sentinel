<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\manager;

use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\player\Player;

/**
 * Makes staff members glow for other staff/ops via SetActorDataPacket
 * metadata, without touching everyone else's view of them.
 */
final class GlowManager {

    /** @var array<string, true> */
    private array $glowing = [];

    private ?ModeManager $modeManager = null;

    public function __construct(private readonly PlayerRegistry $playerRegistry) {}

    /**
     * ModeManager depends on GlowManager, so the reverse link is wired
     * after both are constructed instead of forming a constructor cycle.
     */
    public function setModeManager(ModeManager $modeManager): void {
        $this->modeManager = $modeManager;
    }

    public function addGlow(Player $player): void {
        $this->glowing[strtolower($player->getName())] = true;

        foreach ($this->playerRegistry->getAll() as $viewer) {
            if ($viewer->getName() === $player->getName()) {
                continue;
            }
            if ($this->canSeeGlow($viewer)) {
                $this->sendGlowPacket($player, $viewer, true);
            }
        }
    }

    public function removeGlow(Player $player): void {
        unset($this->glowing[strtolower($player->getName())]);

        foreach ($this->playerRegistry->getAll() as $viewer) {
            $this->sendGlowPacket($player, $viewer, false);
        }
    }

    public function removeAllGlow(): void {
        foreach (array_keys($this->glowing) as $name) {
            // PM4->PM5 fix: previously used Server::getPlayerExact($name) here.
            // Replaced with the shared PlayerRegistry lookup to avoid the
            // forbidden getPlayerExact() pattern.
            $player = $this->playerRegistry->getByName($name);
            if ($player !== null) {
                $this->removeGlow($player);
            }
        }
    }

    public function sendGlow(Player $player, Player $viewer): void {
        if ($viewer->getName() === $player->getName()) {
            return;
        }
        if (!$this->hasGlow($player)) {
            return;
        }
        if ($this->canSeeGlow($viewer)) {
            $this->sendGlowPacket($player, $viewer, true);
        }
    }

    private function sendGlowPacket(Player $player, Player $viewer, bool $enable): void {
        $pk = new SetActorDataPacket();
        $pk->actorRuntimeId = $player->getId();
        $pk->metadata = [
            EntityMetadataProperties::FLAGS => new LongMetadataProperty(
                $enable
                    ? (1 << EntityMetadataFlags::HAS_COLLISION) | (1 << EntityMetadataFlags::GLOWING)
                    : (1 << EntityMetadataFlags::HAS_COLLISION)
            )
        ];
        $viewer->getNetworkSession()->sendDataPacket($pk);
    }

    private function canSeeGlow(Player $player): bool {
        return ($this->modeManager?->isStaffMember($player)) ?? $player->hasPermission("pocketmine.command.op");
    }

    private function hasGlow(Player $player): bool {
        return isset($this->glowing[strtolower($player->getName())]);
    }
}
