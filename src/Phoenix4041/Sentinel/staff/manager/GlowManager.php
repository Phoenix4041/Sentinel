<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\staff\manager;

use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use Phoenix4041\Sentinel\manager\PlayerRegistry;

final class GlowManager {

    private array $glowing = [];
    private PlayerRegistry $playerRegistry;

    public function __construct(PlayerRegistry $playerRegistry) {
        $this->playerRegistry = $playerRegistry;
    }

    public function addGlow(Player $player): void {
        $this->glowing[$player->getName()] = true;

        foreach (Server::getInstance()->getOnlinePlayers() as $viewer) {
            if ($viewer->getName() === $player->getName()) continue;
            if (!$this->canSeeGlow($viewer)) continue;

            $this->sendGlowPacket($player, $viewer, true);
        }
    }

    public function removeGlow(Player $player): void {
        unset($this->glowing[$player->getName()]);

        foreach (Server::getInstance()->getOnlinePlayers() as $viewer) {
            $this->sendGlowPacket($player, $viewer, false);
        }
    }

    public function removeAllGlow(): void {
        foreach ($this->glowing as $name => $_) {
            // PM4->PM5 fix: previously used Server::getPlayerExact($name) here.
            // Replaced with the shared PlayerRegistry lookup (see class docblock
            // on PlayerRegistry) to avoid the forbidden getPlayerExact() pattern.
            $player = $this->playerRegistry->getByName($name);
            if ($player !== null) {
                $this->removeGlow($player);
            }
        }
    }

    public function sendGlow(Player $player, Player $viewer): void {
        if ($viewer->getName() === $player->getName()) return;
        if (!$this->hasGlow($player)) return;
        if (!$this->canSeeGlow($viewer)) return;

        $this->sendGlowPacket($player, $viewer, true);
    }

    private function sendGlowPacket(Player $player, Player $viewer, bool $enable): void {
        $pk = new SetActorDataPacket();
        $pk->actorRuntimeId = $player->getId();
        $pk->metadata = [
            EntityMetadataProperties::FLAGS => new LongMetadataProperty(
                $enable ? (1 << EntityMetadataFlags::HAS_COLLISION) | (1 << EntityMetadataFlags::GLOWING) : (1 << EntityMetadataFlags::HAS_COLLISION)
            )
        ];
        $viewer->getNetworkSession()->sendDataPacket($pk);
    }

    private function canSeeGlow(Player $player): bool {
        $staffManager = \Phoenix4041\Sentinel\Loader::getInstance()->getStaffManager();
        return $staffManager->isInStaffMode($player) || $player->hasPermission("pocketmine.command.op");
    }

    private function hasGlow(Player $player): bool {
        return isset($this->glowing[$player->getName()]);
    }
}
