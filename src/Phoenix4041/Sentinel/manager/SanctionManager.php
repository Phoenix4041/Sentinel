<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\manager;

use Phoenix4041\Sentinel\Loader;
use pocketmine\player\Player;
use pocketmine\utils\Config;

final class SanctionManager {

    private Config $bansConfig;
    private Config $mutesConfig;

    /** @var array<string, array{reason: string, expires: int, banned_by: string, banned_at: int}> */
    private array $activeBans = [];

    /** @var array<string, array{reason: string, expires: int, muted_by: string, muted_at: int}> */
    private array $activeMutes = [];

    public function __construct(
        private readonly Loader $plugin,
        private readonly PlayerRegistry $playerRegistry
    ) {
        $this->loadSanctions();
    }

    private function loadSanctions(): void {
        $dataFolder = $this->plugin->getDataFolder();
        $this->bansConfig = new Config($dataFolder . "bans.yml", Config::YAML);
        $this->mutesConfig = new Config($dataFolder . "mutes.yml", Config::YAML);

        $bans = $this->bansConfig->get("bans", []);
        $mutes = $this->mutesConfig->get("mutes", []);
        $currentTime = time();

        if (is_array($bans)) {
            foreach ($bans as $playerName => $banData) {
                $ban = self::asBanEntry($banData);
                if (is_string($playerName) && $ban !== null && ($ban["expires"] === -1 || $ban["expires"] > $currentTime)) {
                    $this->activeBans[$playerName] = $ban;
                }
            }
        }

        if (is_array($mutes)) {
            foreach ($mutes as $playerName => $muteData) {
                $mute = self::asMuteEntry($muteData);
                if (is_string($playerName) && $mute !== null && ($mute["expires"] === -1 || $mute["expires"] > $currentTime)) {
                    $this->activeMutes[$playerName] = $mute;
                }
            }
        }
    }

    /** @return array{reason: string, expires: int, banned_by: string, banned_at: int}|null */
    private static function asBanEntry(mixed $data): ?array {
        if (!is_array($data) || !isset($data["reason"], $data["expires"], $data["banned_by"], $data["banned_at"])) {
            return null;
        }
        if (!is_string($data["reason"]) || !is_int($data["expires"]) || !is_string($data["banned_by"]) || !is_int($data["banned_at"])) {
            return null;
        }
        return $data;
    }

    /** @return array{reason: string, expires: int, muted_by: string, muted_at: int}|null */
    private static function asMuteEntry(mixed $data): ?array {
        if (!is_array($data) || !isset($data["reason"], $data["expires"], $data["muted_by"], $data["muted_at"])) {
            return null;
        }
        if (!is_string($data["reason"]) || !is_int($data["expires"]) || !is_string($data["muted_by"]) || !is_int($data["muted_at"])) {
            return null;
        }
        return $data;
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

    /** @return array{reason: string, expires: int, banned_by: string, banned_at: int}|null */
    public function getBanInfo(string $playerName): ?array {
        return $this->activeBans[$playerName] ?? null;
    }

    /** @return array{reason: string, expires: int, muted_by: string, muted_at: int}|null */
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

        $timeLeft = $expires === -1
            ? $this->plugin->getMessageManager()->getRawMessage("ban-permanent")
            : $this->formatTime($expires - time());

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

        $months = intdiv($seconds, 2592000);
        $seconds %= 2592000;

        $days = intdiv($seconds, 86400);
        $seconds %= 86400;

        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;

        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        $parts = [];
        if ($months > 0) $parts[] = "{$months}m";
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}min";
        if ($seconds > 0) $parts[] = "{$seconds}s";

        return empty($parts) ? "0s" : implode(" ", $parts);
    }

    /** @return array<string, array{reason: string, expires: int, banned_by: string, banned_at: int}> */
    public function getActiveBans(): array {
        return $this->activeBans;
    }

    /** @return array<string, array{reason: string, expires: int, muted_by: string, muted_at: int}> */
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
                };
            }
        }

        return $total;
    }
}
