# Internal knowledge base

Engineer notes for `msstc4php/vo-base-codeigniter4`. Team-readable, not user-facing documentation.

| File | What it answers |
|---|---|
| [architecture.md](architecture.md) | The four areas, the deptrac rule, why the shared code is static |
| [integrations.md](integrations.md) | How CodeIgniter calls each class: cast parameters, null handling, rule signature, messages |
| [conventions.md](conventions.md) | Choices that look arbitrary: snake_case rule name, `mixed` on `set()`, the test boot |
| [known-issues.md](known-issues.md) | Framework quirks: advisories vs lower bound, `nullable` param, language discovery, PHPStan |

Related: the core's `.claude/docs/` (payload layer) and `../vo-base-doctrine/.claude/docs/`.
