<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\listener;

use Phoenix4041\Sentinel\form\PlayerHistoryForm;
use Phoenix4041\Sentinel\form\SanctionForm;
use Phoenix4041\Sentinel\form\TeleportForm;
use Phoenix4041\Sentinel\item\ToolItems;
use Phoenix4041\Sentinel\Loader;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\projectile\EnderPearl;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\lang\Translatable;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;

/**
 * Core staff-mode interactions: mode toggling side effects, tool item use,
 * freeze enforcement, vanish visibility on join, staff chat, kill/death
 * tracking and login-time ban enforcement.
 */
final class PlayerListener implements Listener {

    /**
     * Target-tool click cooldown, keyed by strtolower(moderator name).
     * @var array<string, float>
     */
    private array $lastToolUse = [];

    /** Tool types resolved by attacking the target rather than self-using the item. */
    private const TARGET_TOOLS = ["history", "freeze", "invsee", "enderinv", "ban"];

    public function __construct(private readonly Loader $plugin) {}

    public function onPlayerLogin(PlayerLoginEvent $event): void {
        $player = $event->getPlayer();

        if ($this->plugin->getSanctionManager()->isBanned($player->getName())) {
            $this->plugin->getSanctionManager()->kickBannedPlayer($player);
            $event->cancel();
        }
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $this->plugin->getModeManager()->handleJoin($event->getPlayer());
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $mode = $this->plugin->getModeManager();

        if ($mode->isActive($player)) {
            $mode->disable($player);
        }

        $mode->handleQuit($player);
        $this->plugin->getInventoryMenu()->stopViewing($player);
        unset($this->lastToolUse[strtolower($player->getName())]);
    }

    public function onPlayerChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $mode = $this->plugin->getModeManager();

        if ($mode->isStaffChatEnabled($player)) {
            $event->cancel();
            $mode->broadcastStaffMessage($player, $event->getMessage());
        }
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();

        if (!$this->plugin->getModeManager()->isFrozen($player)) {
            return;
        }

        $from = $event->getFrom();
        $to = $event->getTo();

        if ($from->x !== $to->x || $from->y !== $to->y || $from->z !== $to->z) {
            $event->cancel();
        }
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();

        if (!$this->plugin->getModeManager()->isActive($player)) {
            return;
        }

        $item = $event->getItem();

        if (!ToolItems::isToolItem($item)) {
            return;
        }

        $event->cancel();

        match (ToolItems::getType($item)) {
            "randomtp" => $this->handleRandomTp($player),
            "teleport" => $player->sendForm(new TeleportForm($this->plugin, $player)),
            "vanish" => $this->plugin->getModeManager()->toggleVanish($player),
            "nightvision" => $this->handleNightVision($player),
            default => null
        };
    }

    public function onPlayerItemUse(PlayerItemUseEvent $event): void {
        $player = $event->getPlayer();
        $mode = $this->plugin->getModeManager();

        if ($mode->isFrozen($player)) {
            $event->cancel();
            return;
        }

        if (!$mode->isActive($player)) {
            return;
        }

        if (!ToolItems::isToolItem($event->getItem())) {
            $event->cancel();
        }
    }

    public function onPlayerDropItem(PlayerDropItemEvent $event): void {
        $player = $event->getPlayer();
        $mode = $this->plugin->getModeManager();

        if ($mode->isActive($player) || $mode->isFrozen($player)) {
            $event->cancel();
        }
    }

    public function onEntityItemPickup(EntityItemPickupEvent $event): void {
        $entity = $event->getEntity();

        if (!$entity instanceof Player) {
            return;
        }

        $mode = $this->plugin->getModeManager();

        if ($mode->isActive($entity) || $mode->isFrozen($entity)) {
            $event->cancel();
        }
    }

    public function onProjectileLaunch(ProjectileLaunchEvent $event): void {
        $entity = $event->getEntity();

        if (!$entity instanceof EnderPearl) {
            return;
        }

        $shooter = $entity->getOwningEntity();

        if (!$shooter instanceof Player) {
            return;
        }

        $mode = $this->plugin->getModeManager();

        if ($mode->isActive($shooter) || $mode->isFrozen($shooter)) {
            $event->cancel();
        }
    }

    public function onPlayerDeath(PlayerDeathEvent $event): void {
        $victim = $event->getPlayer();
        $cause = $victim->getLastDamageCause();

        if (!$cause instanceof EntityDamageByEntityEvent) {
            return;
        }

        $killer = $cause->getDamager();

        if (!$killer instanceof Player) {
            return;
        }

        $weapon = $killer->getInventory()->getItemInHand();
        $enchantNames = array_map(
            function(EnchantmentInstance $enchantment): string {
                $name = $enchantment->getType()->getName();
                return $name instanceof Translatable ? $name->getText() : $name;
            },
            $weapon->getEnchantments()
        );

        $this->plugin->getDatabaseManager()->logKill(
            $killer->getName(),
            $victim->getName(),
            $weapon->getName(),
            $enchantNames
        );
    }

    /**
     * @priority LOWEST
     * @ignoreCancelled false
     */
    public function onEntityDamageLowest(EntityDamageEvent $event): void {
        if (!$event instanceof EntityDamageByEntityEvent) {
            return;
        }

        $target = $event->getEntity();
        $moderator = $event->getDamager();

        if (!$target instanceof Player || !$moderator instanceof Player) {
            return;
        }

        if (!$this->plugin->getModeManager()->isActive($moderator)) {
            return;
        }

        $type = ToolItems::getType($moderator->getInventory()->getItemInHand());

        if (!in_array($type, self::TARGET_TOOLS, true)) {
            return;
        }

        $name = strtolower($moderator->getName());
        $now = microtime(true);

        if (($now - ($this->lastToolUse[$name] ?? 0.0)) < 0.3) {
            return;
        }

        $this->lastToolUse[$name] = $now;
        $this->handleTargetTool($moderator, $target, $type);
    }

    /**
     * @priority MONITOR
     * @ignoreCancelled false
     */
    public function onEntityDamageMonitor(EntityDamageEvent $event): void {
        $entity = $event->getEntity();

        if (!$entity instanceof Player) {
            return;
        }

        $mode = $this->plugin->getModeManager();

        if (!$event instanceof EntityDamageByEntityEvent) {
            if ($mode->isActive($entity) || $mode->isFrozen($entity)) {
                $event->cancel();
            }
            return;
        }

        $damager = $event->getDamager();

        if (!$damager instanceof Player) {
            if ($mode->isActive($entity) || $mode->isFrozen($entity)) {
                $event->cancel();
            }
            return;
        }

        if ($mode->isActive($damager)) {
            $event->cancel();
            return;
        }

        if ($mode->isActive($entity) || $mode->isFrozen($entity) || $mode->isFrozen($damager)) {
            $event->cancel();
        }
    }

    private function handleRandomTp(Player $player): void {
        $candidates = array_values(array_filter(
            $this->plugin->getPlayerRegistry()->getAll(),
            fn(Player $online): bool => $online->getName() !== $player->getName()
        ));

        if (empty($candidates)) {
            $this->plugin->getMessageManager()->sendToast($player, "randomtp-no-players");
            return;
        }

        $target = $candidates[array_rand($candidates)];
        $player->teleport($target->getPosition());
        $this->plugin->getMessageManager()->sendToast($player, "randomtp-success", ["player" => $target->getName()]);
    }

    private function handleNightVision(Player $player): void {
        $effects = $player->getEffects();

        if ($effects->has(VanillaEffects::NIGHT_VISION())) {
            $effects->remove(VanillaEffects::NIGHT_VISION());
            $this->plugin->getMessageManager()->sendToast($player, "nightvision-off");
        } else {
            $effects->add(new EffectInstance(VanillaEffects::NIGHT_VISION(), 2147483647, 0, false));
            $this->plugin->getMessageManager()->sendToast($player, "nightvision-on");
        }
    }

    private function handleTargetTool(Player $moderator, Player $target, string $type): void {
        match ($type) {
            "history" => $moderator->sendForm(new PlayerHistoryForm($this->plugin, $target->getName())),
            "freeze" => $this->plugin->getModeManager()->toggleFreeze($moderator, $target),
            "invsee" => $this->plugin->getInventoryMenu()->openPlayerInventory($moderator, $target),
            "enderinv" => $this->plugin->getInventoryMenu()->openPlayerEnderInventory($moderator, $target),
            "ban" => $moderator->sendForm(new SanctionForm($this->plugin, $target->getName())),
            default => null
        };
    }
}
