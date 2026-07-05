# OpenSpec Changes

本目录承载 OpenSpec 的 **change 流程**：每个非平凡改动以一个子目录提出，经评审后归档。

## 流程（proposal → archive）

1. **创建 change**：复制约定命名（如 `add-merchant-impersonation/`），内含：
   - `proposal.md` —— 动机、方案、影响范围。
   - `tasks.md` —— 可勾选的实施步骤。
   - `specs/<capability>/spec.md` —— 该 capability 的 spec 增量（SHALL 新增/修改）。
2. **实施**：按 `tasks.md` 执行，每步打勾。
3. **归档**：完成后将目录移至 `archive/<change>/`，并把 spec 增量合并进 `openspec/specs/<capability>/spec.md`（即此处为"当前真值"）。

## 现状

- 本次为 **baseline 导入**：直接落地 `openspec/project.md`、`openspec/agreements/`、`openspec/specs/`（摘自现有 `docs/superpowers/specs/` SDD）。`changes/` 初始为空。
- 后续阶段 2+ 的每个里程碑（如「阶段 2 迁移与模型」、「阶段 3 Filament CRUD」）建议各开一个 change。

## 命名约定

- 目录用 kebab-case 动词短语：`add-<thing>`、`change-<thing>`、`remove-<thing>`。
- 一个 change 只做一件可独立评审的事。
