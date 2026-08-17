<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\staff\manager;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\player\Player;

final class DataManager {

    private PluginBase $plugin;
    private Config $blockData;
    private Config $containerData;
    private Config $playerData;
    private array $kills = [];
    private array $deaths = [];
    private array $commands = [];

    public function __construct(PluginBase $plugin) {
        $this->plugin = $plugin;
        $this->load();
    }

    private function load(): void {
        @mkdir($this->plugin->getDataFolder());

        $this->blockData = new Config($this->plugin->getDataFolder() . "blocks.yml", Config::YAML);
        $this->containerData = new Config($this->plugin->getDataFolder() . "containers.yml", Config::YAML);
        $this->playerData = new Config($this->plugin->getDataFolder() . "players.yml", Config::YAML);

        $this->kills = $this->playerData->get("kills", []);
        $this->deaths = $this->playerData->get("deaths", []);
        $this->commands = $this->playerData->get("commands", []);
    }

    public function save(): void {
        $this->playerData->set("kills", $this->kills);
        $this->playerData->set("deaths", $this->deaths);
        $this->playerData->set("commands", $this->commands);

        $this->blockData->save();
        $this->containerData->save();
        $this->playerData->save();
    }

    public function logBlockPlace(int $x, int $y, int $z, string $world, string $player): void {
        $key = "$world:$x:$y:$z";
        $this->blockData->set($key, [
            "player" => $player,
            "time" => time()
        ]);
    }

    public function getBlockData(int $x, int $y, int $z, string $world): ?array {
        $key = "$world:$x:$y:$z";
        return $this->blockData->get($key);
    }

    public function logContainerAccess(int $x, int $y, int $z, string $world, string $player, string $action, string $item): void {
        $key = "$world:$x:$y:$z";
        $logs = $this->containerData->get($key, []);
        $logs[] = [
            "player" => $player,
            "action" => $action,
            "item" => $item,
            "time" => time()
        ];
        $this->containerData->set($key, $logs);
    }

    public function getContainerLogs(int $x, int $y, int $z, string $world): array {
        $key = "$world:$x:$y:$z";
        return $this->containerData->get($key, []);
    }

    public function logKill(string $killer, string $victim, string $weapon, array $enchants): void {
        if (!isset($this->kills[$killer])) {
            $this->kills[$killer] = [];
        }

        $this->kills[$killer][] = [
            "victim" => $victim,
            "weapon" => $weapon,
            "enchants" => $enchants,
            "time" => time()
        ];
    }

    public function logDeath(string $victim, string $killer, string $weapon, array $enchants): void {
        if (!isset($this->deaths[$victim])) {
            $this->deaths[$victim] = [];
        }

        $this->deaths[$victim][] = [
            "killer" => $killer,
            "weapon" => $weapon,
            "enchants" => $enchants,
            "time" => time()
        ];
    }

    public function logCommand(string $player, string $command): void {
        if (!isset($this->commands[$player])) {
            $this->commands[$player] = [];
        }

        $this->commands[$player][] = [
            "command" => $command,
            "time" => time()
        ];

        $weekAgo = time() - 604800;
        $this->commands[$player] = array_filter($this->commands[$player], fn($log) => $log["time"] >= $weekAgo);
    }

    public function getKills(string $player): array {
        return $this->kills[$player] ?? [];
    }

    public function getDeaths(string $player): array {
        return $this->deaths[$player] ?? [];
    }

    public function getCommands(string $player): array {
        return $this->commands[$player] ?? [];
    }
}
