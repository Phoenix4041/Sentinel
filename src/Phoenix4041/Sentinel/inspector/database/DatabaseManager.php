<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\inspector\database;

use Phoenix4041\Sentinel\Loader;
use SQLite3;
use SQLite3Stmt;

class DatabaseManager {

    private Loader $plugin;
    private ?SQLite3 $database = null;
    private array $queryCache = [];
    private array $preparedStatements = [];
    private const CACHE_TTL = 300;
    private const MAX_CACHE_SIZE = 100;
    private int $cacheHits = 0;
    private int $cacheMisses = 0;

    public function __construct(Loader $plugin) {
        $this->plugin = $plugin;
    }

    public function initialize(): void {
        $dataFolder = $this->plugin->getDataFolder();
        if (!is_dir($dataFolder)) {
            mkdir($dataFolder, 0777, true);
        }

        $this->database = new SQLite3($dataFolder . "inspector.db");
        $this->database->busyTimeout(5000);

        $this->database->exec("PRAGMA journal_mode = WAL");
        $this->database->exec("PRAGMA synchronous = NORMAL");
        $this->database->exec("PRAGMA cache_size = -20000");
        $this->database->exec("PRAGMA temp_store = MEMORY");
        $this->database->exec("PRAGMA mmap_size = 30000000000");
        $this->database->exec("PRAGMA page_size = 4096");

        $this->createTables();
        $this->prepareStatements();
    }

    private function createTables(): void {
        $this->database->exec("
            CREATE TABLE IF NOT EXISTS command_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_name TEXT NOT NULL,
                command TEXT NOT NULL,
                arguments TEXT,
                timestamp INTEGER NOT NULL
            )
        ");

        $this->database->exec("CREATE INDEX IF NOT EXISTS idx_cmd_player_timestamp ON command_logs(player_name, timestamp DESC)");

        $this->database->exec("
            CREATE TABLE IF NOT EXISTS block_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_name TEXT NOT NULL,
                action TEXT NOT NULL,
                block_id TEXT NOT NULL,
                x INTEGER NOT NULL,
                y INTEGER NOT NULL,
                z INTEGER NOT NULL,
                world TEXT NOT NULL,
                timestamp INTEGER NOT NULL
            )
        ");

        $this->database->exec("CREATE INDEX IF NOT EXISTS idx_block_location_timestamp ON block_logs(x, y, z, world, timestamp DESC)");
        $this->database->exec("CREATE INDEX IF NOT EXISTS idx_block_player_timestamp ON block_logs(player_name, timestamp DESC)");

        $this->database->exec("
            CREATE TABLE IF NOT EXISTS container_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_name TEXT NOT NULL,
                action TEXT NOT NULL,
                container_type TEXT NOT NULL,
                items_changed TEXT,
                x INTEGER NOT NULL,
                y INTEGER NOT NULL,
                z INTEGER NOT NULL,
                world TEXT NOT NULL,
                timestamp INTEGER NOT NULL
            )
        ");

        $this->database->exec("CREATE INDEX IF NOT EXISTS idx_container_location_timestamp ON container_logs(x, y, z, world, timestamp DESC)");
        $this->database->exec("CREATE INDEX IF NOT EXISTS idx_container_player_timestamp ON container_logs(player_name, timestamp DESC)");
    }

    private function prepareStatements(): void {
        $this->preparedStatements['insert_command'] = $this->database->prepare("
            INSERT INTO command_logs (player_name, command, arguments, timestamp)
            VALUES (:player, :command, :args, :time)
        ");

        $this->preparedStatements['select_commands'] = $this->database->prepare("
            SELECT command, arguments, timestamp
            FROM command_logs
            WHERE player_name = :player
            ORDER BY timestamp DESC
            LIMIT :limit
        ");

        $this->preparedStatements['insert_block'] = $this->database->prepare("
            INSERT INTO block_logs (player_name, action, block_id, x, y, z, world, timestamp)
            VALUES (:player, :action, :block, :x, :y, :z, :world, :time)
        ");

        $this->preparedStatements['select_blocks'] = $this->database->prepare("
            SELECT player_name, action, block_id, timestamp
            FROM block_logs
            WHERE x = :x AND y = :y AND z = :z AND world = :world
            ORDER BY timestamp DESC
            LIMIT :limit
        ");

        $this->preparedStatements['insert_container'] = $this->database->prepare("
            INSERT INTO container_logs (player_name, action, container_type, items_changed, x, y, z, world, timestamp)
            VALUES (:player, :action, :type, :items, :x, :y, :z, :world, :time)
        ");

        $this->preparedStatements['select_containers'] = $this->database->prepare("
            SELECT player_name, action, container_type, items_changed, timestamp
            FROM container_logs
            WHERE x = :x AND y = :y AND z = :z AND world = :world
            ORDER BY timestamp DESC
            LIMIT :limit
        ");
    }

    public function logCommand(string $playerName, string $command, string $arguments): void {
        $stmt = $this->preparedStatements['insert_command'];
        $stmt->reset();
        $stmt->clear();

        $stmt->bindValue(":player", $playerName, SQLITE3_TEXT);
        $stmt->bindValue(":command", $command, SQLITE3_TEXT);
        $stmt->bindValue(":args", $arguments, SQLITE3_TEXT);
        $stmt->bindValue(":time", time(), SQLITE3_INTEGER);
        $stmt->execute();

        $this->invalidateCache("cmd_" . $playerName);
    }

    public function getCommandHistory(string $playerName, int $limit = 10): array {
        $cacheKey = "cmd_" . $playerName . "_" . $limit;

        if (isset($this->queryCache[$cacheKey])) {
            $cached = $this->queryCache[$cacheKey];
            if (time() - $cached['time'] < self::CACHE_TTL) {
                $this->cacheHits++;
                return $cached['data'];
            }
        }

        $this->cacheMisses++;

        $stmt = $this->preparedStatements['select_commands'];
        $stmt->reset();
        $stmt->clear();

        $stmt->bindValue(":player", $playerName, SQLITE3_TEXT);
        $stmt->bindValue(":limit", $limit, SQLITE3_INTEGER);

        $result = $stmt->execute();
        $logs = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $logs[] = $row;
        }

        $this->addToCache($cacheKey, $logs);

        return $logs;
    }

    public function logBlockAction(
        string $playerName,
        string $action,
        string $blockId,
        int $x,
        int $y,
        int $z,
        string $world
    ): void {
        $stmt = $this->preparedStatements['insert_block'];
        $stmt->reset();
        $stmt->clear();

        $stmt->bindValue(":player", $playerName, SQLITE3_TEXT);
        $stmt->bindValue(":action", $action, SQLITE3_TEXT);
        $stmt->bindValue(":block", $blockId, SQLITE3_TEXT);
        $stmt->bindValue(":x", $x, SQLITE3_INTEGER);
        $stmt->bindValue(":y", $y, SQLITE3_INTEGER);
        $stmt->bindValue(":z", $z, SQLITE3_INTEGER);
        $stmt->bindValue(":world", $world, SQLITE3_TEXT);
        $stmt->bindValue(":time", time(), SQLITE3_INTEGER);
        $stmt->execute();

        $this->invalidateCache("block_" . $x . "_" . $y . "_" . $z . "_" . $world);
    }

    public function getBlockHistory(int $x, int $y, int $z, string $world, int $limit = 10): array {
        $cacheKey = "block_" . $x . "_" . $y . "_" . $z . "_" . $world . "_" . $limit;

        if (isset($this->queryCache[$cacheKey])) {
            $cached = $this->queryCache[$cacheKey];
            if (time() - $cached['time'] < self::CACHE_TTL) {
                $this->cacheHits++;
                return $cached['data'];
            }
        }

        $this->cacheMisses++;

        $stmt = $this->preparedStatements['select_blocks'];
        $stmt->reset();
        $stmt->clear();

        $stmt->bindValue(":x", $x, SQLITE3_INTEGER);
        $stmt->bindValue(":y", $y, SQLITE3_INTEGER);
        $stmt->bindValue(":z", $z, SQLITE3_INTEGER);
        $stmt->bindValue(":world", $world, SQLITE3_TEXT);
        $stmt->bindValue(":limit", $limit, SQLITE3_INTEGER);

        $result = $stmt->execute();
        $logs = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $logs[] = $row;
        }

        $this->addToCache($cacheKey, $logs);

        return $logs;
    }

    public function logContainerAction(
        string $playerName,
        string $action,
        string $containerType,
        string $itemsChanged,
        int $x,
        int $y,
        int $z,
        string $world
    ): void {
        $stmt = $this->preparedStatements['insert_container'];
        $stmt->reset();
        $stmt->clear();

        $stmt->bindValue(":player", $playerName, SQLITE3_TEXT);
        $stmt->bindValue(":action", $action, SQLITE3_TEXT);
        $stmt->bindValue(":type", $containerType, SQLITE3_TEXT);
        $stmt->bindValue(":items", $itemsChanged, SQLITE3_TEXT);
        $stmt->bindValue(":x", $x, SQLITE3_INTEGER);
        $stmt->bindValue(":y", $y, SQLITE3_INTEGER);
        $stmt->bindValue(":z", $z, SQLITE3_INTEGER);
        $stmt->bindValue(":world", $world, SQLITE3_TEXT);
        $stmt->bindValue(":time", time(), SQLITE3_INTEGER);
        $stmt->execute();

        $this->invalidateCache("container_" . $x . "_" . $y . "_" . $z . "_" . $world);
    }

    public function getContainerHistory(int $x, int $y, int $z, string $world, int $limit = 10): array {
        $cacheKey = "container_" . $x . "_" . $y . "_" . $z . "_" . $world . "_" . $limit;

        if (isset($this->queryCache[$cacheKey])) {
            $cached = $this->queryCache[$cacheKey];
            if (time() - $cached['time'] < self::CACHE_TTL) {
                $this->cacheHits++;
                return $cached['data'];
            }
        }

        $this->cacheMisses++;

        $stmt = $this->preparedStatements['select_containers'];
        $stmt->reset();
        $stmt->clear();

        $stmt->bindValue(":x", $x, SQLITE3_INTEGER);
        $stmt->bindValue(":y", $y, SQLITE3_INTEGER);
        $stmt->bindValue(":z", $z, SQLITE3_INTEGER);
        $stmt->bindValue(":world", $world, SQLITE3_TEXT);
        $stmt->bindValue(":limit", $limit, SQLITE3_INTEGER);

        $result = $stmt->execute();
        $logs = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $logs[] = $row;
        }

        $this->addToCache($cacheKey, $logs);

        return $logs;
    }

    private function addToCache(string $key, array $data): void {
        if (count($this->queryCache) >= self::MAX_CACHE_SIZE) {
            $oldestKey = array_key_first($this->queryCache);
            unset($this->queryCache[$oldestKey]);
        }

        $this->queryCache[$key] = [
            'data' => $data,
            'time' => time()
        ];
    }

    private function invalidateCache(string $prefix): void {
        foreach (array_keys($this->queryCache) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->queryCache[$key]);
            }
        }
    }

    public function getCacheStats(): array {
        return [
            'hits' => $this->cacheHits,
            'misses' => $this->cacheMisses,
            'size' => count($this->queryCache),
            'hit_rate' => $this->cacheHits > 0 ?
                round(($this->cacheHits / ($this->cacheHits + $this->cacheMisses)) * 100, 2) : 0
        ];
    }

    public function close(): void {
        if ($this->database !== null) {
            foreach ($this->preparedStatements as $stmt) {
                $stmt->close();
            }
            $this->preparedStatements = [];

            $this->database->close();
            $this->database = null;
        }
        $this->queryCache = [];
    }
}
