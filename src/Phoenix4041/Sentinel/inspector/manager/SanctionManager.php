<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\inspector\manager;

use Phoenix4041\Sentinel\Loader;
use Phoenix4041\Sentinel\manager\PlayerRegistry;
use pocketmine\player\Player;
use pocketmine\utils\Config;

class SanctionManager {

    private Loader $plugin;
    private PlayerRegistry $playerRegistry;
    private Config $bansConfig;
    private Config $mutesConfig;
    private array $activeBans = [];
    private array $activeMutes = [];

    public function __construct(Loader $plugin, PlayerRegistry $playerRegistry) {
        $this->plugin = $plugin;
        $this->playerRegistry = $playerRegistry;
        $this->loadSanctions();
    }

    private function loadSanctions(): void {
        $dataFolder = $this->plugin->getDataFolder();
        $this->bansConfig = new Config($dataFolder . "bans.yml", Config::YAML);
        $this->mutesConfig = new Config($dataFolder . "mutes.yml", Config::YAML);

        $bans = $this->bansConfig->get("bans", []);
        $mutes = $this->mutesConfig->get("mutes", []);
        $currentTime = time();

        foreach ($bans as $playerName => $banData) {
            if ($banData["expires"] === -1 || $banData["expires"] > $currentTime) {
                $this->activeBans[$playerName] = $banData;
            }
        }

        foreach ($mutes as $playerName => $muteData) {
            if ($muteData["expires"] === -1 || $muteData["expires"] > $currentTime) {
                $this->activeMutes[$playerName] = $muteData;
            }
        }
    }

    public function saveBans(): void {
        $this->bansConfig->set("bans", $this->activeBans);
        $this->bansConfig->save();
    }

    public function saveMutes(): void {
        $this->mutesConfig->set("mutes", $this->activeMutes);
        $this->mutesConfig->save();
    }

    public function banPlayer(string $playerName, string $reason, int $duration, string $bannedBy): bool {
        $expireTime = $duration === 0 ? -1 : time() + $duration;

        $this->activeBans[$playerName] = [
            "reason" => $reason,
            "expires" => $expireTime,
            "banned_by" => $bannedBy,
            "banned_at" => time()
        ];

        $this->saveBans();

        // PM4->PM5 fix: previously used $this->plugin->getServer()->getPlayerExact($playerName)
        // here. Replaced with the shared PlayerRegistry lookup to avoid the
        // forbidden getPlayerExact() pattern.
        $player = $this->playerRegistry->getByName($playerName);
        if ($player !== null) {
            $this->kickBannedPlayer($player);
        }

        return true;
    }

    public function mutePlayer(string $playerName, string $reason, int $duration, string $mutedBy): bool {
        $expireTime = $duration === 0 ? -1 : time() + $duration;

        $this->activeMutes[$playerName] = [
            "reason" => $reason,
            "expires" => $expireTime,
            "muted_by" => $mutedBy,
            "muted_at" => time()
        ];

        $this->saveMutes();

        return true;
    }

    public function unbanPlayer(string $playerName): bool {
        if (!isset($this->activeBans[$playerName])) {
            return false;
        }

        unset($this->activeBans[$playerName]);
        $this->saveBans();
        return true;
    }

    public function unmutePlayer(string $playerName): bool {
        if (!isset($this->activeMutes[$playerName])) {
            return false;
        }

        unset($this->activeMutes[$playerName]);
        $this->saveMutes();
        return true;
    }

    public function removeAllSanctions(string $playerName): bool {
        $hadBan = isset($this->activeBans[$playerName]);
        $hadMute = isset($this->activeMutes[$playerName]);

        if ($hadBan) {
            unset($this->activeBans[$playerName]);
            $this->saveBans();
        }

        if ($hadMute) {
            unset($this->activeMutes[$playerName]);
            $this->saveMutes();
        }

        return $hadBan || $hadMute;
    }

    public function isBanned(string $playerName): bool {
        if (!isset($this->activeBans[$playerName])) {
            return false;
        }

        $banData = $this->activeBans[$playerName];

        if ($banData["expires"] === -1) {
            return true;
        }

        if ($banData["expires"] > time()) {
            return true;
        }

        unset($this->activeBans[$playerName]);
        $this->saveBans();
        return false;
    }

    public function isMuted(string $playerName): bool {
        if (!isset($this->activeMutes[$playerName])) {
            return false;
        }

        $muteData = $this->activeMutes[$playerName];

        if ($muteData["expires"] === -1) {
            return true;
        }

        if ($muteData["expires"] > time()) {
            return true;
        }

        unset($this->activeMutes[$playerName]);
        $this->saveMutes();
        return false;
    }

    public function getBanInfo(string $playerName): ?array {
        return $this->activeBans[$playerName] ?? null;
    }

    public function getMuteInfo(string $playerName): ?array {
        return $this->activeMutes[$playerName] ?? null;
    }

    public function kickBannedPlayer(Player $player): void {
        $banData = $this->getBanInfo($player->getName());

        if ($banData === null) {
            return;
        }

        $reason = $banData["reason"];
        $expires = $banData["expires"];

        $timeLeft = $expires === -1 ?
            $this->plugin->getMessageManager()->getRawMessage("ban-permanent") :
            $this->formatTime($expires - time());

        $kickMessage = $this->plugin->getMessageManager()->getRawMessage("ban-kick-message", [
            "reason" => $reason,
            "time" => $timeLeft
        ]);

        $player->kick($kickMessage);
    }

    private function formatTime(int $seconds): string {
        if ($seconds <= 0) {
            return $this->plugin->getMessageManager()->getRawMessage("ban-expired");
        }

        $months = floor($seconds / 2592000);
        $seconds %= 2592000;

        $days = floor($seconds / 86400);
        $seconds %= 86400;

        $hours = floor($seconds / 3600);
        $seconds %= 3600;

        $minutes = floor($seconds / 60);
        $seconds %= 60;

        $parts = [];
        if ($months > 0) $parts[] = "{$months}m";
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}min";
        if ($seconds > 0) $parts[] = "{$seconds}s";

        return empty($parts) ? "0s" : implode(" ", $parts);
    }

    public function getActiveBans(): array {
        return $this->activeBans;
    }

    public function getActiveMutes(): array {
        return $this->activeMutes;
    }

    public function parseDuration(string $durationStr): int {
        if (empty($durationStr) || strtolower($durationStr) === "permanent") {
            return 0;
        }

        $durationStr = strtolower(trim($durationStr));
        $total = 0;

        if (preg_match_all('/(\d+)(s|min|h|d|m)/', $durationStr, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $value = (int)$match[1];
                $unit = $match[2];

                $total += match($unit) {
                    's' => $value,
                    'min' => $value * 60,
                    'h' => $value * 3600,
                    'd' => $value * 86400,
                    'm' => $value * 2592000,
                    default => 0
                };
            }
        }

        return $total;
    }
}
