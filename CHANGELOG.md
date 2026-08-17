# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.0.0] - 2026-08-17

### Added

- Initial release of Sentinel, merging the EpicStaff and Inspector plugins into a single PocketMine-MP 5.x plugin under the `Phoenix4041\Sentinel` namespace.
- Unified `Loader` bootstrap that initializes both modules' managers, registers all commands (`/staff`, `/inspector`, `/unban`) and listeners in one `onEnable()`, and performs full cleanup (staff mode disable, glow removal, data save, inspector mode disable, database close, sanction save) in `onDisable()`.
- Staff module moved to `src/Phoenix4041/Sentinel/staff/{command,form,item,listener,manager}`.
- Inspector module moved to `src/Phoenix4041/Sentinel/inspector/{command,data,database,form,listener,manager,menu}`.
- Vendored `muqsit/invmenu` copied byte-for-byte into `src/muqsit/invmenu` (namespace untouched).
- Merged `plugin.yml` permissions (`epicstaff.use`, `inspector.command.access`) with no duplicates.
- Merged `resources/` from Inspector (`config.yml`, `messages.yml`); EpicStaff shipped no `resources/config.yml`, so nothing needed merging on that side.
- New `Phoenix4041\Sentinel\manager\PlayerRegistry` (+ `PlayerRegistryListener`), a small manager with real behavior: it maintains a `strtolower(name) => Player` map updated on `PlayerJoinEvent`/`PlayerQuitEvent`.

### Changed

- **getPlayerExact()/getPlayerByName() anti-pattern removed**: `GlowManager::removeAllGlow()`, `StaffManager::disableAll()`, `InspectorManager::handlePlayerJoin()` and `SanctionManager::banPlayer()` previously resolved a player by name via `Server::getPlayerExact()`. All four now use the shared `PlayerRegistry::getByName()` lookup instead.
- All moved PHP files now declare `strict_types=1` (already present in every original file) and keep explicit typed properties/return types; no other PM4-era API calls (`pocketmine\level`, legacy numeric item/block IDs, `Item::get()`) were found in either source plugin - both already targeted PMMP 5.x and used `VanillaItems`/`VanillaBlocks`/`pocketmine\world` correctly.
- No empty manager wrappers or chaotic nested-array-as-logic patterns were found; existing managers (`DataManager`, `SanctionManager`, `InspectorManager`, `DatabaseManager`, etc.) all hold real behavior and were kept as-is aside from namespace and player-lookup fixes.
