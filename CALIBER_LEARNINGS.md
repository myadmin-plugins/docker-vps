# Caliber Learnings

Accumulated patterns and anti-patterns from development sessions.
Auto-managed by [caliber](https://github.com/caliber-ai-org/ai-setup) — do not edit manually.

- **[gotcha:project]** Caliber's reference validator rejects backtick path references containing `{placeholder}` syntax (e.g., `` `templates/{action}.sh.tpl` ``) — treated as literal paths and flagged invalid. In skill content, use concrete example paths (e.g., `templates/start.sh.tpl`) or prose descriptions for variable-action patterns.
- **[gotcha:project]** Caliber's reference validator rejects `filename:linenum` format (e.g., `src/Plugin.php:96`) in skill files — use plain file paths without line numbers (`src/Plugin.php`).
- **[gotcha:project]** Bare filename references without path prefix (e.g., `Plugin.php` instead of `src/Plugin.php`) fail Caliber's path validator — always use the full relative path as it appears in the project file tree.
- **[pattern:project]** Primary VPS templates (`templates/*.sh.tpl`) use `/root/cpaneldirect/provirted.phar {cmd} --virt=docker {$vps_vzid|escapeshellarg}` pattern; backup VPS templates (`templates/backup/*.sh.tpl`) use `virsh` commands with `export PATH="$PATH:/usr/sbin:/sbin:/bin:/usr/bin:";` preamble — always create both when adding a new VPS action.
