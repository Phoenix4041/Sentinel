<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\staff\manager;

use pocketmine\player\Player;
use pocketmine\Server;
use Phoenix4041\Sentinel\staff\item\StaffItems;
use Phoenix4041\Sentinel\manager\PlayerRegistry;

final class StaffManager {

    private array $staffMode = [];
    private array $vanished = [];
    private array $frozen = [];
    private GlowManager $glowManager;
    private PlayerRegistry $playerRegistry;

    public function __construct(GlowManager $glowManager, PlayerRegistry $playerRegistry) {
        $this->glowManager = $glowManager;
        $this->playerRegistry = $playerRegistry;
    }

    public function toggle(Player $player): void {
        $name = $player->getName();

        if (isset($this->staffMode[$name])) {
            $this->disable($player);
        } else {
            $this->enable($player);
        }
    }

    public function enable(Player $player): void {
        $name = $player->getName();
        $this->staffMode[$name] = true;

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        StaffItems::give($player);

        $this->glowManager->addGlow($player);
        $player->sendPopup("§aModo Staff §f(§a✓§f)");
    }

    public function disable(Player $player): void {
        $name = $player->getName();
        unset($this->staffMode[$name]);

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();

        if ($this->isVanished($player)) {
            $this->toggleVanish($player);
        }

        $this->glowManager->removeGlow($player);
        $player->sendPopup("§cModo Staff §f(§c✗§f)");
    }

    public function disableAll(): void {
        foreach ($this->staffMode as $name => $_) {
            // PM4->PM5 fix: previously used Server::getPlayerExact($name) here.
            // Replaced with the shared PlayerRegistry lookup to avoid the
            // forbidden getPlayerExact() pattern.
            $player = $this->playerRegistry->getByName($name);
            if ($player !== null) {
                $this->disable($player);
            }
        }
    }

    public function isInStaffMode(Player $player): bool {
        return isset($this->staffMode[$player->getName()]);
    }

    public function toggleVanish(Player $player): void {
        $name = $player->getName();

        if (isset($this->vanished[$name])) {
            unset($this->vanished[$name]);
            foreach (Server::getInstance()->getOnlinePlayers() as $online) {
                $online->showPlayer($player);
            }
            $player->sendPopup("§aVanish §f(§c✗§f)");
        } else {
            $this->vanished[$name] = true;
            foreach (Server::getInstance()->getOnlinePlayers() as $online) {
                if (!$this->canSeeStaff($online)) {
                    $online->hidePlayer($player);
                }
            }
            $player->sendPopup("§aVanish §f(§a✓§f)");
        }
    }

    public function isVanished(Player $player): bool {
        return isset($this->vanished[$player->getName()]);
    }

    public function toggleFreeze(Player $target): void {
        $name = $target->getName();

        if (isset($this->frozen[$name])) {
            unset($this->frozen[$name]);
            $target->sendPopup("§aUnfrozen");
        } else {
            $this->frozen[$name] = true;
            $target->sendPopup("§cFrozen");
        }
    }

    public function isFrozen(Player $player): bool {
        return isset($this->frozen[$player->getName()]);
    }

    public function canSeeStaff(Player $player): bool {
        return $this->isInStaffMode($player) || $player->hasPermission("pocketmine.command.op");
    }

    public function handleJoin(Player $player): void {
        foreach (Server::getInstance()->getOnlinePlayers() as $staff) {
            if ($this->isVanished($staff) && !$this->canSeeStaff($player)) {
                $player->hidePlayer($staff);
            }

            if ($this->isInStaffMode($staff)) {
                $this->glowManager->sendGlow($staff, $player);
            }
        }
    }

    public function handleQuit(Player $player): void {
        $name = $player->getName();
        unset($this->staffMode[$name], $this->vanished[$name], $this->frozen[$name]);
        $this->glowManager->removeGlow($player);
    }
}
