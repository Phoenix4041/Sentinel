<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\database;

use Phoenix4041\Sentinel\Loader;
use SQLite3;
use SQLite3Stmt;

/**
 * Single source of truth for all Sentinel persistence: command, block,
 * container and kill/death history. Indexed SQLite3 tables with prepared
 * statements and a short-lived in-memory query cache.
 */
final class DatabaseManager {

    private SQLite3 $database;

    /** @var array<string, SQLite3Stmt> */
    private array $preparedStatements = [];

    /** @var array<string, array{data: list<array<string, mixed>>, time: int}> */
    private array $queryCache = [];

    private const CACHE_TTL = 300;
    private const MAX_CACHE_SIZE = 100;

    private int $cacheHits = 0;
    private int $cacheMisses = 0;

    public function __construct(private readonly Loader $plugin) {}

    public function initialize(): void {
        $dataFolder = $this->plugin->getDataFolder();
        if (!is_dir($dataFolder)) {
            mkdir($dataFolder, 0777, true);
        }

        $this->database = new SQLite3($dataFolder . "sentinel.db");
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

    private function preparedOrThrow(string $sql): SQLite3Stmt {
        $stmt = $this->database->prepare($sql);
        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare statement: " . $this->database->lastErrorMsg());
        }
        return $stmt;
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

        $this->database->exec("
            CREATE TABLE IF NOT EXISTS kill_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                killer_name TEXT NOT NULL,
                victim_name TEXT NOT NULL,
                weapon TEXT NOT NULL,
                enchantments TEXT,
                timestamp INTEGER NOT NULL
            )
        ");
        $this->database->exec("CREATE INDEX IF NOT EXISTS idx_kill_killer_timestamp ON kill_logs(killer_name, timestamp DESC)");
        $this->database->exec("CREATE INDEX IF NOT EXISTS idx_kill_victim_timestamp ON kill_logs(victim_name, timestamp DESC)");
    }

    private function prepareStatements(): void {
        $this->preparedStatements['insert_command'] = $this->preparedOrThrow("
            INSERT INTO command_logs (player_name, command, arguments, timestamp)
            VALUES (:player, :command, :args, :time)
        ");

        $this->preparedStatements['select_commands'] = $this->preparedOrThrow("
            SELECT command, arguments, timestamp
            FROM command_logs
            WHERE player_name = :player
            ORDER BY timestamp DESC
            LIMIT :limit
        ");

        $this->preparedStatements['insert_block'] = $this->preparedOrThrow("
            INSERT INTO block_logs (player_name, action, block_id, x, y, z, world, timestamp)
            VALUES (:player, :action, :block, :x, :y, :z, :world, :time)
        ");

        $this->preparedStatements['select_blocks'] = $this->preparedOrThrow("
            SELECT player_name, action, block_id, timestamp
            FROM block_logs
            WHERE x = :x AND y = :y AND z = :z AND world = :world
            ORDER BY timestamp DESC
            LIMIT :limit
        ");

        $this->preparedStatements['insert_container'] = $this->preparedOrThrow("
            INSERT INTO container_logs (player_name, action, container_type, items_changed, x, y, z, world, timestamp)
            VALUES (:player, :action, :type, :items, :x, :y, :z, :world, :time)
        ");

        $this->preparedStatements['select_containers'] = $this->preparedOrThrow("
            SELECT player_name, action, container_type, items_changed, timestamp
            FROM container_logs
            WHERE x = :x AND y = :y AND z = :z AND world = :world
            ORDER BY timestamp DESC
            LIMIT :limit
        ");

        $this->preparedStatements['insert_kill'] = $this->preparedOrThrow("
            INSERT INTO kill_logs (killer_name, victim_name, weapon, enchantments, timestamp)
            VALUES (:killer, :victim, :weapon, :enchantments, :time)
        ");

        $this->preparedStatements['select_kills'] = $this->preparedOrThrow("
            SELECT victim_name, weapon, enchantments, timestamp
            FROM kill_logs
            WHERE killer_name = :player
            ORDER BY timestamp DESC
            LIMIT :limit
        ");

        $this->preparedStatements['select_deaths'] = $this->preparedOrThrow("
            SELECT killer_name, weapon, enchantments, timestamp
            FROM kill_logs
            WHERE victim_name = :player
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

    /** @return list<array{command: string, arguments: string, timestamp: int}> */
    public function getCommandHistory(string $playerName, int $limit = 10): array {
        /** @var list<array{command: string, arguments: string, timestamp: int}> $rows */
        $rows = $this->cachedSelect(
            "cmd_" . $playerName . "_" . $limit,
            'select_commands',
            [":player" => [$playerName, SQLITE3_TEXT], ":limit" => [$limit, SQLITE3_INTEGER]]
        );
        return $rows;
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

    /** @return list<array{player_name: string, action: string, block_id: string, timestamp: int}> */
    public function getBlockHistory(int $x, int $y, int $z, string $world, int $limit = 10): array {
        /** @var list<array{player_name: string, action: string, block_id: string, timestamp: int}> $rows */
        $rows = $this->cachedSelect(
            "block_" . $x . "_" . $y . "_" . $z . "_" . $world . "_" . $limit,
            'select_blocks',
            [
                ":x" => [$x, SQLITE3_INTEGER],
                ":y" => [$y, SQLITE3_INTEGER],
                ":z" => [$z, SQLITE3_INTEGER],
                ":world" => [$world, SQLITE3_TEXT],
                ":limit" => [$limit, SQLITE3_INTEGER]
            ]
        );
        return $rows;
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

    /** @return list<array{player_name: string, action: string, container_type: string, items_changed: string, timestamp: int}> */
    public function getContainerHistory(int $x, int $y, int $z, string $world, int $limit = 10): array {
        /** @var list<array{player_name: string, action: string, container_type: string, items_changed: string, timestamp: int}> $rows */
        $rows = $this->cachedSelect(
            "container_" . $x . "_" . $y . "_" . $z . "_" . $world . "_" . $limit,
            'select_containers',
            [
                ":x" => [$x, SQLITE3_INTEGER],
                ":y" => [$y, SQLITE3_INTEGER],
                ":z" => [$z, SQLITE3_INTEGER],
                ":world" => [$world, SQLITE3_TEXT],
                ":limit" => [$limit, SQLITE3_INTEGER]
            ]
        );
        return $rows;
    }

    /**
     * @param array<int, string> $enchantments
     */
    public function logKill(string $killerName, string $victimName, string $weapon, array $enchantments): void {
        $stmt = $this->preparedStatements['insert_kill'];
        $stmt->reset();
        $stmt->clear();

        $stmt->bindValue(":killer", $killerName, SQLITE3_TEXT);
        $stmt->bindValue(":victim", $victimName, SQLITE3_TEXT);
        $stmt->bindValue(":weapon", $weapon, SQLITE3_TEXT);
        $stmt->bindValue(":enchantments", implode(",", $enchantments), SQLITE3_TEXT);
        $stmt->bindValue(":time", time(), SQLITE3_INTEGER);
        $stmt->execute();

        $this->invalidateCache("kill_" . $killerName);
        $this->invalidateCache("death_" . $victimName);
    }

    /** @return list<array{victim_name: string, weapon: string, enchantments: string, timestamp: int}> */
    public function getKills(string $playerName, int $limit = 10): array {
        /** @var list<array{victim_name: string, weapon: string, enchantments: string, timestamp: int}> $rows */
        $rows = $this->cachedSelect(
            "kill_" . $playerName . "_" . $limit,
            'select_kills',
            [":player" => [$playerName, SQLITE3_TEXT], ":limit" => [$limit, SQLITE3_INTEGER]]
        );
        return $rows;
    }

    /** @return list<array{killer_name: string, weapon: string, enchantments: string, timestamp: int}> */
    public function getDeaths(string $playerName, int $limit = 10): array {
        /** @var list<array{killer_name: string, weapon: string, enchantments: string, timestamp: int}> $rows */
        $rows = $this->cachedSelect(
            "death_" . $playerName . "_" . $limit,
            'select_deaths',
            [":player" => [$playerName, SQLITE3_TEXT], ":limit" => [$limit, SQLITE3_INTEGER]]
        );
        return $rows;
    }

    /**
     * @param array<string, array{0: mixed, 1: int}> $bindings
     * @return list<array<string, mixed>>
     */
    private function cachedSelect(string $cacheKey, string $statementKey, array $bindings): array {
        if (isset($this->queryCache[$cacheKey])) {
            $cached = $this->queryCache[$cacheKey];
            if (time() - $cached['time'] < self::CACHE_TTL) {
                $this->cacheHits++;
                return $cached['data'];
            }
        }

        $this->cacheMisses++;

        $stmt = $this->preparedStatements[$statementKey];
        $stmt->reset();
        $stmt->clear();

        foreach ($bindings as $param => [$value, $type]) {
            $stmt->bindValue($param, $value, $type);
        }

        $result = $stmt->execute();
        $rows = [];

        if ($result !== false) {
            while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
                $rows[] = $row;
            }
        }

        $this->addToCache($cacheKey, $rows);

        return $rows;
    }

    /** @param list<array<string, mixed>> $data */
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

    /** @return array{hits: int, misses: int, size: int, hit_rate: float|int} */
    public function getCacheStats(): array {
        return [
            'hits' => $this->cacheHits,
            'misses' => $this->cacheMisses,
            'size' => count($this->queryCache),
            'hit_rate' => $this->cacheHits > 0
                ? round(($this->cacheHits / ($this->cacheHits + $this->cacheMisses)) * 100, 2)
                : 0
        ];
    }

    public function close(): void {
        if (isset($this->database)) {
            foreach ($this->preparedStatements as $stmt) {
                $stmt->close();
            }
            $this->preparedStatements = [];

            $this->database->close();
        }
        $this->queryCache = [];
    }
}
