<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\manager;

use Phoenix4041\Sentinel\Loader;

final class ConfigManager {

    public function __construct(private readonly Loader $plugin) {}

    public function getVanishCooldown(): float {
        return $this->getFloat("vanish-cooldown", 1.0);
    }

    public function getFreezeCooldown(): float {
        return $this->getFloat("freeze-cooldown", 0.5);
    }

    public function getCommandHistoryLimit(): int {
        return $this->getInt("command-history-limit", 10);
    }

    public function getBlockHistoryLimit(): int {
        return $this->getInt("block-history-limit", 10);
    }

    public function getContainerHistoryLimit(): int {
        return $this->getInt("container-history-limit", 10);
    }

    private function getFloat(string $key, float $default): float {
        $value = $this->plugin->getConfig()->get($key, $default);
        return is_numeric($value) ? (float) $value : $default;
    }

    private function getInt(string $key, int $default): int {
        $value = $this->plugin->getConfig()->get($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }
}
