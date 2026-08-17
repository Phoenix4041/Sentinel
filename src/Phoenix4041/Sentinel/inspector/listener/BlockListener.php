<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\inspector\listener;

use Phoenix4041\Sentinel\Loader;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;

class BlockListener implements Listener {

    private Loader $plugin;
    private array $interactionCache = [];
    private const CACHE_TTL = 5;

    public function __construct(Loader $plugin) {
        $this->plugin = $plugin;
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();

        if ($this->plugin->getInspectorManager()->isInspectorMode($player)) {
            $event->cancel();
            return;
        }

        if ($this->plugin->getInspectorManager()->isFrozen($player)) {
            $event->cancel();
            return;
        }

        $transaction = $event->getTransaction();
        $blocks = $transaction->getBlocks();

        foreach ($blocks as [$x, $y, $z, $block]) {
            $pos = $block->getPosition();

            $this->plugin->getDatabaseManager()->logBlockAction(
                $playerName,
                "place",
                $block->getTypeId() . "",
                (int)$pos->getFloorX(),
                (int)$pos->getFloorY(),
                (int)$pos->getFloorZ(),
                $pos->getWorld()->getFolderName()
            );
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();

        if ($this->plugin->getInspectorManager()->isInspectorMode($player)) {
            $event->cancel();
            return;
        }

        if ($this->plugin->getInspectorManager()->isFrozen($player)) {
            $event->cancel();
            return;
        }

        $block = $event->getBlock();
        $pos = $block->getPosition();

        $this->plugin->getDatabaseManager()->logBlockAction(
            $playerName,
            "break",
            $block->getTypeId() . "",
            (int)$pos->getFloorX(),
            (int)$pos->getFloorY(),
            (int)$pos->getFloorZ(),
            $pos->getWorld()->getFolderName()
        );
    }

    /**
     * @priority LOWEST
     * @ignoreCancelled false
     */
    public function onPlayerInteractLowest(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();

        if (!$this->plugin->getInspectorManager()->isInspectorMode($player)) {
            return;
        }

        $item = $event->getItem();
        $itemName = $item->getCustomName();

        if ($itemName !== "§8[§dInspector§8]§r") {
            return;
        }

        $action = $event->getAction();

        if ($action !== PlayerInteractEvent::RIGHT_CLICK_BLOCK && $action !== PlayerInteractEvent::LEFT_CLICK_BLOCK) {
            return;
        }

        $block = $event->getBlock();
        $pos = $block->getPosition();
        $world = $pos->getWorld();
        $x = (int)$pos->getFloorX();
        $y = (int)$pos->getFloorY();
        $z = (int)$pos->getFloorZ();
        $worldName = $world->getFolderName();

        $cacheKey = "{$x}_{$y}_{$z}_{$worldName}";

        if (isset($this->interactionCache[$cacheKey])) {
            $cached = $this->interactionCache[$cacheKey];
            if ((time() - $cached['time']) < self::CACHE_TTL) {
                $isContainer = $cached['isContainer'];

                if ($isContainer) {
                    $this->showContainerHistory($player, $x, $y, $z, $worldName);
                } else {
                    $this->showBlockHistory($player, $x, $y, $z, $worldName);
                }
                return;
            }
        }

        $tile = $world->getTile($pos);
        $isContainer = $tile !== null && method_exists($tile, 'getInventory');

        $this->interactionCache[$cacheKey] = [
            'isContainer' => $isContainer,
            'time' => time()
        ];

        if (count($this->interactionCache) > 50) {
            $this->cleanupCache();
        }

        if ($isContainer) {
            $this->showContainerHistory($player, $x, $y, $z, $worldName);
        } else {
            $this->showBlockHistory($player, $x, $y, $z, $worldName);
        }
    }

    private function showBlockHistory(
        \pocketmine\player\Player $player,
        int $x,
        int $y,
        int $z,
        string $world
    ): void {
        $db = $this->plugin->getDatabaseManager();
        $history = $db->getBlockHistory($x, $y, $z, $world, 10);

        if (empty($history)) {
            $player->sendMessage("§6Inspector | §cNo block history found at this location");
            return;
        }

        $player->sendMessage("§6Inspector | Block History at §e" . $x . ", " . $y . ", " . $z);

        foreach ($history as $entry) {
            $time = date("H:i:s d/m/Y", $entry["timestamp"]);
            $playerName = $entry["player_name"];
            $action = $entry["action"];
            $blockId = $entry["block_id"];

            $player->sendMessage("§3[§b{$time}§3] §f| §a{$playerName} §f| §e{$action} §f| §d{$blockId}");
        }
    }

    private function showContainerHistory(
        \pocketmine\player\Player $player,
        int $x,
        int $y,
        int $z,
        string $world
    ): void {
        $db = $this->plugin->getDatabaseManager();
        $history = $db->getContainerHistory($x, $y, $z, $world, 10);

        if (empty($history)) {
            $player->sendMessage("§6Inspector | §cNo container history found at this location");
            return;
        }

        $player->sendMessage("§6Inspector | Container History at §e" . $x . ", " . $y . ", " . $z);

        foreach ($history as $entry) {
            $time = date("H:i:s d/m/Y", $entry["timestamp"]);
            $playerName = $entry["player_name"];
            $actionType = $entry["action"];
            $containerType = $entry["container_type"];
            $itemsChanged = $entry["items_changed"];

            if ($actionType === "open") {
                $player->sendMessage("§3[§b{$time}§3] §f| §a{$playerName} §f| §eOpened §d{$containerType}");
            } else {
                $player->sendMessage("§3[§b{$time}§3] §f| §a{$playerName} §f| §e{$itemsChanged}");
            }
        }
    }

    private function cleanupCache(): void {
        $currentTime = time();
        $expired = array_filter(
            $this->interactionCache,
            fn($entry) => ($currentTime - $entry['time']) > self::CACHE_TTL * 2
        );

        foreach (array_keys($expired) as $key) {
            unset($this->interactionCache[$key]);
        }
    }
}
