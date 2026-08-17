<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\staff\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerMoveEvent;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\block\tile\Chest;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use Phoenix4041\Sentinel\staff\manager\StaffManager;
use Phoenix4041\Sentinel\staff\item\StaffItems;
use Phoenix4041\Sentinel\staff\form\TeleportForm;
use Phoenix4041\Sentinel\staff\form\InspectorForm;
use Phoenix4041\Sentinel\Loader;

final class StaffListener implements Listener {

    private StaffManager $staffManager;

    public function __construct(StaffManager $staffManager) {
        $this->staffManager = $staffManager;
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $this->staffManager->handleJoin($event->getPlayer());
    }

    public function onQuit(PlayerQuitEvent $event): void {
        $this->staffManager->handleQuit($event->getPlayer());
    }

    public function onInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();

        if (!$this->staffManager->isInStaffMode($player)) return;
        if (!StaffItems::isStaffItem($item)) return;

        $event->cancel();
        $type = StaffItems::getType($item);

        match ($type) {
            "randomtp" => $this->handleRandomTp($player),
            "vanish" => $this->handleVanish($player),
            "nightvision" => $this->handleNightVision($player),
            "teleport" => $this->handleTeleport($player),
            "freezer" => $this->handleFreezer($player, $event),
            "invsee" => $this->handleInvSee($player, $event),
            "inspector" => $this->handleInspector($player, $event),
            default => null
        };
    }

    private function handleRandomTp(Player $player): void {
        $online = array_values(array_filter(
            Server::getInstance()->getOnlinePlayers(),
            fn($p) => $p->getName() !== $player->getName()
        ));

        if (empty($online)) {
            $player->sendPopup("§cNo players online");
            return;
        }

        $target = $online[array_rand($online)];
        $player->teleport($target->getPosition());
        $player->sendPopup("§aTeleported to " . $target->getName());
    }

    private function handleVanish(Player $player): void {
        $this->staffManager->toggleVanish($player);
    }

    private function handleNightVision(Player $player): void {
        if ($player->getEffects()->has(VanillaEffects::NIGHT_VISION())) {
            $player->getEffects()->remove(VanillaEffects::NIGHT_VISION());
            $player->sendPopup("§cNight Vision §f(§c✗§f)");
        } else {
            $player->getEffects()->add(new EffectInstance(VanillaEffects::NIGHT_VISION(), 2147483647, 0, false));
            $player->sendPopup("§aNight Vision §f(§a✓§f)");
        }
    }

    private function handleTeleport(Player $player): void {
        $player->sendForm(new TeleportForm());
    }

    private function handleFreezer(Player $player, PlayerInteractEvent $event): void {
        $entity = $event->getPlayer()->getWorld()->getNearestEntity($player->getEyePos(), 5, Player::class);

        if (!$entity instanceof Player) {
            $player->sendPopup("§cNo player nearby");
            return;
        }

        $this->staffManager->toggleFreeze($entity);
        $player->sendPopup("§aToggled freeze on " . $entity->getName());
    }

    private function handleInvSee(Player $player, PlayerInteractEvent $event): void {
        $entity = $event->getPlayer()->getWorld()->getNearestEntity($player->getEyePos(), 5, Player::class);

        if (!$entity instanceof Player) {
            $player->sendPopup("§cNo player nearby");
            return;
        }

        $player->sendPopup("§aOpening inventory of " . $entity->getName());
    }

    private function handleInspector(Player $player, PlayerInteractEvent $event): void {
        $block = $event->getBlock();
        $entity = $event->getPlayer()->getWorld()->getNearestEntity($player->getEyePos(), 5, Player::class);

        if ($entity instanceof Player && $entity->getName() !== $player->getName()) {
            $player->sendForm(new InspectorForm($entity));
            return;
        }

        $pos = $block->getPosition();
        $world = $pos->getWorld()->getFolderName();
        $data = Loader::getInstance()->getDataManager()->getBlockData(
            (int)$pos->x,
            (int)$pos->y,
            (int)$pos->z,
            $world
        );

        if ($data === null) {
            $player->sendPopup("§cNo data for this block");
            return;
        }

        $date = date("Y-m-d H:i:s", $data["time"]);
        $player->sendPopup("§aPlaced by {$data["player"]} at $date");
    }

    public function onMove(PlayerMoveEvent $event): void {
        if (!$this->staffManager->isFrozen($event->getPlayer())) return;
        $event->cancel();
    }



    public function onBreak(BlockBreakEvent $event): void {
        if ($this->staffManager->isFrozen($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        if (!$entity instanceof Player) return;

        if ($this->staffManager->isFrozen($entity)) {
            $event->cancel();
        }

        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            if ($damager instanceof Player && $this->staffManager->isFrozen($damager)) {
                $event->cancel();
            }
        }
    }

    public function onTransaction(InventoryTransactionEvent $event): void {
        if ($this->staffManager->isFrozen($event->getTransaction()->getSource())) {
            $event->cancel();
        }
    }
}
