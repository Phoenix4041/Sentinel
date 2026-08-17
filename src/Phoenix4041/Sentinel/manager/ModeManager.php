<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\manager;

use Phoenix4041\Sentinel\item\ToolItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;

/**
 * Staff mode: saves the moderator's position/inventory/gamemode, equips the
 * tool kit, and tracks vanish/freeze/staff-chat state. Every set is keyed
 * by strtolower(name) and cleaned up on PlayerQuitEvent.
 */
final class ModeManager {

    private const COOLDOWN_CLEANUP_INTERVAL = 3600;

    /** @var array<string, ModeSnapshot> */
    private array $snapshots = [];

    /** @var array<string, true> */
    private array $active = [];

    /** @var array<string, true> */
    private array $vanished = [];

    /** @var array<string, true> */
    private array $frozen = [];

    /** @var array<string, true> */
    private array $staffChat = [];

    /** @var array<string, float> */
    private array $cooldowns = [];

    private int $lastCooldownCleanup;

    public function __construct(
        private readonly GlowManager $glowManager,
        private readonly PlayerRegistry $playerRegistry,
        private readonly MessageManager $messageManager,
        private readonly ConfigManager $configManager
    ) {
        $this->lastCooldownCleanup = time();
    }

    public function isActive(Player $player): bool {
        return isset($this->active[strtolower($player->getName())]);
    }

    public function toggle(Player $player): void {
        if ($this->isActive($player)) {
            $this->disable($player);
        } else {
            $this->enable($player);
        }
    }

    public function enable(Player $player): void {
        $name = strtolower($player->getName());
        if (isset($this->active[$name])) {
            return;
        }

        $this->snapshots[$name] = new ModeSnapshot(
            $player->getPosition(),
            $player->getInventory()->getContents(),
            $player->getArmorInventory()->getContents(),
            $player->getGamemode(),
            $player->getAllowFlight(),
            $player->isFlying()
        );

        $this->active[$name] = true;

        $player->setGamemode(GameMode::SURVIVAL());
        $player->setAllowFlight(true);
        $player->setFlying(true);

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        ToolItems::give($player);

        $this->glowManager->addGlow($player);
        $this->messageManager->sendToast($player, "mode-on");
    }

    public function disable(Player $player): void {
        $name = strtolower($player->getName());
        if (!isset($this->active[$name])) {
            return;
        }

        unset($this->active[$name]);

        if ($this->isVanished($player)) {
            $this->toggleVanish($player);
        }

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();

        $snapshot = $this->snapshots[$name] ?? null;

        if ($snapshot !== null) {
            $player->getInventory()->setContents($snapshot->inventory);
            $player->getArmorInventory()->setContents($snapshot->armor);
            $player->setGamemode($snapshot->gameMode);
            $player->setAllowFlight($snapshot->allowFlight);
            $player->setFlying($snapshot->allowFlight && $snapshot->isFlying);

            if ($snapshot->position->isValid()) {
                $player->teleport($snapshot->position);
            }

            unset($this->snapshots[$name]);
        }

        $this->glowManager->removeGlow($player);
        $this->messageManager->sendToast($player, "mode-off");
    }

    public function disableAll(): void {
        foreach (array_keys($this->active) as $name) {
            $player = $this->playerRegistry->getByName($name);
            if ($player !== null) {
                $this->disable($player);
            }
        }
    }

    public function toggleVanish(Player $player): void {
        if (!$this->checkCooldown($player, "vanish", $this->configManager->getVanishCooldown())) {
            return;
        }

        $name = strtolower($player->getName());
        $isVanished = isset($this->vanished[$name]);

        if ($isVanished) {
            unset($this->vanished[$name]);

            foreach ($this->playerRegistry->getAll() as $viewer) {
                $viewer->showPlayer($player);
            }

            $player->getInventory()->setItem(5, ToolItems::vanishItem(false));
            $this->messageManager->sendToast($player, "vanish-off");
        } else {
            $this->vanished[$name] = true;

            foreach ($this->playerRegistry->getAll() as $viewer) {
                if ($viewer->getName() !== $player->getName() && !$this->isStaffMember($viewer)) {
                    $viewer->hidePlayer($player);
                }
            }

            $player->getInventory()->setItem(5, ToolItems::vanishItem(true));
            $this->messageManager->sendToast($player, "vanish-on");
        }
    }

    public function isVanished(Player $player): bool {
        return isset($this->vanished[strtolower($player->getName())]);
    }

    public function toggleFreeze(Player $moderator, Player $target): void {
        if (!$this->checkCooldown($moderator, "freeze", $this->configManager->getFreezeCooldown())) {
            return;
        }

        $name = strtolower($target->getName());

        if (isset($this->frozen[$name])) {
            unset($this->frozen[$name]);
            $this->messageManager->broadcastToast("frozen-off", ["player" => $target->getName()]);
            $target->sendTip("§r§8[§cUN§f-§cFROZEN§8]§r");
            $this->messageManager->sendToast($moderator, "notification-player-unfrozen", ["player" => $target->getName()]);
        } else {
            $this->frozen[$name] = true;
            $this->messageManager->broadcastToast("frozen-on", ["player" => $target->getName()]);
            $target->sendTip("§r§8[§bFROZEN§8]§r");
            $this->messageManager->sendToast($moderator, "notification-player-frozen", ["player" => $target->getName()]);
        }
    }

    public function isFrozen(Player $player): bool {
        return isset($this->frozen[strtolower($player->getName())]);
    }

    /**
     * Whether the player should see vanish/glow through - staff stays
     * visible to other staff (in or out of mode) and to ops.
     */
    public function isStaffMember(Player $player): bool {
        return $player->hasPermission("sentinel.staff") || $player->hasPermission("pocketmine.command.op");
    }

    public function enableStaffChat(Player $player): void {
        $this->staffChat[strtolower($player->getName())] = true;
        $this->messageManager->sendToast($player, "staff-chat-enabled");
    }

    public function disableStaffChat(Player $player): void {
        unset($this->staffChat[strtolower($player->getName())]);
        $this->messageManager->sendToast($player, "staff-chat-disabled");
    }

    public function isStaffChatEnabled(Player $player): bool {
        return isset($this->staffChat[strtolower($player->getName())]);
    }

    public function broadcastStaffMessage(Player $sender, string $message): void {
        $formatted = $this->messageManager->getRawMessage("staff-chat-format", [
            "player" => $sender->getName(),
            "message" => $message
        ]);

        foreach ($this->playerRegistry->getAll() as $player) {
            if ($player->hasPermission("sentinel.staff")) {
                $player->sendMessage($formatted);
            }
        }
    }

    public function handleJoin(Player $player): void {
        foreach ($this->playerRegistry->getAll() as $other) {
            if ($other->getName() === $player->getName()) {
                continue;
            }

            if ($this->isVanished($other) && !$this->isStaffMember($player)) {
                $player->hidePlayer($other);
            }

            if ($this->isActive($other)) {
                $this->glowManager->sendGlow($other, $player);
            }
        }
    }

    public function handleQuit(Player $player): void {
        $name = strtolower($player->getName());

        unset(
            $this->active[$name],
            $this->vanished[$name],
            $this->frozen[$name],
            $this->staffChat[$name],
            $this->snapshots[$name]
        );

        $this->glowManager->removeGlow($player);

        foreach (array_keys($this->cooldowns) as $key) {
            if (str_starts_with($key, $name . "_")) {
                unset($this->cooldowns[$key]);
            }
        }
    }

    private function checkCooldown(Player $player, string $action, float $seconds): bool {
        $key = strtolower($player->getName()) . "_" . $action;
        $now = microtime(true);

        if ($now - $this->lastCooldownCleanup > self::COOLDOWN_CLEANUP_INTERVAL) {
            $this->cleanupCooldowns($now);
            $this->lastCooldownCleanup = (int)$now;
        }

        if (isset($this->cooldowns[$key]) && ($now - $this->cooldowns[$key]) < $seconds) {
            return false;
        }

        $this->cooldowns[$key] = $now;
        return true;
    }

    private function cleanupCooldowns(float $now): void {
        $threshold = $now - 300;
        foreach ($this->cooldowns as $key => $time) {
            if ($time < $threshold) {
                unset($this->cooldowns[$key]);
            }
        }
    }
}
