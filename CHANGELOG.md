# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.2.0] - 2026-08-17

### Added

- `/mute` and `/unmute` commands, mirroring `/unban`'s pattern - `SanctionForm` now takes a `mute` flag to post to `SanctionManager::mutePlayer()` instead of `banPlayer()`, and `UnmuteForm` lists/clears active mutes only.
- Real client-side command autocomplete/argument previews for `/staff`, `/unban`, `/mute` and `/unmute`, via the vendored `CortexPE/Commando` (LGPL-3.0) + `muqsit/SimplePacketHandler` (GPL-3.0) virions.
- Multi-language messages: `resources/langs/en.yml` and `es.yml`, selected via `language` in `config.yml`, loaded through `MessageManager` (same `sendToast`/`broadcastToast`/`getRawMessage` API as before).

### Changed

- Swapped the Vanish and EnderInv tool kit slots (hotbar 5 and 6).
- `resources/messages.yml` moved to `resources/langs/en.yml`.

## [1.1.1] - 2026-08-17

### Fixed

- `GlowManager` sent a `SetActorDataPacket` referencing `EntityMetadataFlags::GLOWING`, which does not exist in PMMP 5.x - it never would have compiled. Bedrock Edition has no client-side glow/outline effect at all (it's Java Edition exclusive; there is no metadata flag, status effect or packet for it), so `GlowManager` now marks staff with a colored, always-visible nametag (`Entity::setNameTag()` / `setNameTagAlwaysVisible()`) instead - the closest real equivalent the PMMP API supports.
- `DatabaseManager` could call `exec()`/`prepare()` on a nullable `SQLite3` property without a null-check, and `prepare()`/`execute()` failures were never checked before use.
- `SanctionManager` trusted `bans.yml`/`mutes.yml` contents without validating their shape after loading.
- `ConfigManager` and `MessageManager` cast `Config::get()` values (`mixed`) straight to `float`/`int`/`string` without validating them first.
- Form `handleResponse()` handlers cast client-submitted form data straight to `int`/`string` without validating it was actually numeric/scalar first.
- Removed a dead `??` fallback on `ArmorInventory` getters, which never return `null` in this PMMP version.
- Removed an unreachable `match` default arm in `SanctionManager::parseDuration()`.

### Added

- `composer.json` + `phpstan.neon` (pinned to the PMMP 5.x stubs) so the codebase can be linted at PHPStan level 9 (max) going forward.
- `tools/build_phar.php` to package the plugin into a `.phar` the same way DevTools does, for local verification.
- `.gitignore` for `vendor/`, `build/` and PHPStan's cache.

## [1.1.0] - 2026-08-17

### Changed

- Real architectural merge: the plugin was previously two parallel systems glued under one namespace; it is now one system designed as a single plugin.
- Replaced the dual staff-mode/inspector-mode duality with a single `manager\ModeManager`: one save/restore snapshot (position, inventory, armor, gamemode, flight), one vanish state, one freeze state, one staff-chat state, all cleaned up on `PlayerQuitEvent`.
- Replaced the two overlapping tool sets (7 CustomName-matched items and 8 NBT-tagged items) with one 9-item kit in `item\ToolItems`, always resolved by NBT tag: RandomTp, Teleport, History, InvSee, EnderInv, Vanish, Freeze, Night Vision, Ban. Filled hotbar slots 0-8 with no gaps and no duplicate function.
- Replaced YAML-backed `DataManager` with SQLite: kill/death tracking now lives in a new `kill_logs` table on `database\DatabaseManager`, indexed by killer and victim, following the same prepared-statement + query-cache pattern as the existing command/block/container tables.
- Merged the two `CommandEvent` listeners into one `listener\CommandListener`: logs to SQLite and cancels commands from frozen players.
- Merged block-place/death tracking into `listener\BlockListener`/`listener\PlayerListener`, backed entirely by SQLite (block/container logging already used it; kill/death logging was moved onto it).
- Replaced `epicstaff.use` and `inspector.command.access` with `sentinel.staff` (staff mode + tool kit) and `sentinel.command.unban` (`/unban`).
- Replaced `/staff` (toggle-only) and `/inspector` (toggle/on/off/chat/help) with a single `/staff` command (aliases `staffmode`, `mod`) carrying every subcommand.
- Reorganized `src/Phoenix4041/Sentinel/` by responsibility instead of origin: `command/`, `listener/`, `manager/`, `database/`, `item/`, `form/`, `menu/` replace the old `staff/` and `inspector/` top-level split.
- Renamed the SQLite database file from `inspector.db` to `sentinel.db` and the toast title from `[Inspector]` to `[Sentinel]`.
- `GlowManager` no longer reaches into `Loader::getInstance()` to check staff status; `ModeManager` is now wired in via setter injection after construction, avoiding the constructor cycle while keeping it out of the forbidden global-lookup pattern.
- Inventory/ender-chest viewing now always goes through `menu\InventoryMenu` (InvMenu-based, shows armor slots), which was the more complete of the two prior implementations; the raw window-swap version was unused dead code and is gone.
- Vanish, freeze and history cooldowns/limits are now actually read from `config.yml` via `ConfigManager` instead of being hardcoded past a dead config wiring.

### Removed

- `staff/manager/StaffManager.php` and `inspector/manager/InspectorManager.php`, merged into `manager/ModeManager.php`.
- `staff/manager/DataManager.php` (YAML kill/death/command/block/container storage) - functionality now lives in `database/DatabaseManager.php`.
- `inspector/menu/InventoryMenu.php` (the unused raw window-swap implementation).
- `/inspector` command and its class `inspector/command/InspectorCommand.php` - folded into `/staff`.
- Legacy permissions `epicstaff.use` and `inspector.command.access`.
- Duplicate `CommandListener` (one of the two former copies).
- All folder-level `staff/` and `inspector/` separation.

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
