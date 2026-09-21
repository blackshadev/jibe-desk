# Which OpenCode Go models can switch reasoning effort?

**Date:** 2026-09-21
**Question:** Which models in the OpenCode Go provider support switching between reasoning effort levels (e.g. Qwen3.8 Flash with a low reasoning mode)?

Reasoning effort in OpenCode is exposed through **model variants** — named options for one model, "often used for reasoning effort or token budgets" ([docs](https://opencode.ai/v2/docs/models/#variants)). A model can switch reasoning effort if its catalog entry has more than one variant. The data below comes from the live model catalog (`models` tool, provider `opencode-go`, 27 models).

## Summary

**16 of 27 OpenCode Go models expose switchable reasoning-effort variants.** The most flexible is **GPT-5.6 Luna** (6 levels: `none` → `max`). Your own session's model, **Qwen3.8 Flash**, supports `none`, `low`, `medium`, `xhigh` (note: no `high`/`max` — `medium` is followed by `xhigh`).

## Models WITH switchable reasoning effort

### Full effort ladders (4+ levels)

| Model | ID | Variants |
| --- | --- | --- |
| GPT-5.6 Luna | `opencode-go/gpt-5.6-luna` | `none`, `low`, `medium`, `high`, `xhigh`, `max` |
| Muse Spark 1.3 Contributor | `opencode-go/muse-spark-1.3-contributor` | `minimal`, `low`, `medium`, `high`, `xhigh` |
| Muse Spark 1.2 Contributor | `opencode-go/muse-spark-1.2-contributor` | `minimal`, `low`, `medium`, `high`, `xhigh` |
| Grok 4.6 | `opencode-go/grok-4.6` | `low`, `medium`, `high`, `xhigh` |
| Qwen3.8 Flash | `opencode-go/qwen3.8-flash` | `none`, `low`, `medium`, `xhigh` |

### Moderate ladders (3 levels)

| Model | ID | Variants |
| --- | --- | --- |
| Qwen3.8 Max | `opencode-go/qwen3.8-max` | `low`, `medium`, `xhigh` |
| DeepSeek V4.1 Flash | `opencode-go/deepseek-v4.1-flash` | `low`, `high`, `max` |
| DeepSeek V4 Flash | `opencode-go/deepseek-v4-flash` | `low`, `high`, `max` |
| DeepSeek V4 Flash Vision Exp | `opencode-go/deepseek-v4-flash-vision-exp` | `low`, `high`, `max` |
| GLM-5.3 Flash | `opencode-go/glm-5.3-flash` | `low`, `high`, `max` |
| GLM-5.3 | `opencode-go/glm-5.3` | `low`, `high`, `max` |
| Hy3 | `opencode-go/hy3` | `none`, `low`, `high` |

### Limited switching (2 levels)

| Model | ID | Variants | Notes |
| --- | --- | --- | --- |
| GLM-5.2 | `opencode-go/glm-5.2` | `high`, `max` | Two intensity levels |
| DeepSeek V4 Pro | `opencode-go/deepseek-v4-pro` | `high`, `max` | Two intensity levels |
| Hy4 preview | `opencode-go/hy4-preview` | `none`, `high` | Reasoning on/off |
| MiniMax-M3 | `opencode-go/minimax-m3` | `none`, `thinking` | Thinking on/off, not a graded ladder |

## Models WITHOUT switchable reasoning effort

- **Fixed single variant:** Kimi K3 (`max` only), MiMo V2.5 (`low` only) — a variant exists but there is nothing to switch between.
- **No variants at all:** LongCat-2.0, Kimi K2.7 Code, Kimi K2.6, Qwen3.7 Plus, Qwen3.7 Max, Qwen3.6 Plus, MiMo V2.5 Pro, GLM-5.1, MiniMax-M2.7.

## How to switch effort

Per the [Models docs → Variants / References](https://opencode.ai/v2/docs/models/), append `#variant` to the model reference:

```text
opencode-go/qwen3.8-flash#low
opencode-go/gpt-5.6-luna#xhigh
```

- **Session:** pick via `/models` and select a variant entry.
- **One-off run:** `opencode run --model opencode-go/qwen3.8-flash#low "..."`
- **Agents & commands:** their `model` field accepts the `provider/model#variant` string form.
- **Caveat:** the root `model` field in `opencode.json` retains only provider + model, **not** the variant — set variants per session, run, agent, or command.
- Unknown variant names produce a model-resolution error; variant names come from the model's catalog metadata (so e.g. `qwen3.8-flash#high` would fail — it has `medium` then `xhigh`, no `high`).

### Rolling your own effort levels

If a model's catalog variants don't fit, you can define or override variants in config, mapping them onto provider settings like `reasoningEffort`:

```jsonc
{
  "$schema": "https://opencode.ai/config.json",
  "providers": {
    "opencode-go": {
      "models": {
        "qwen3.8-flash": {
          "variants": [
            { "id": "quick", "settings": { "reasoningEffort": "low" } },
            { "id": "deep",  "settings": { "reasoningEffort": "medium" } }
          ]
        }
      }
    }
  }
}
```

Each variant can carry `settings`, `headers`, and `body`; they are applied **after** provider and model values ([Options](https://opencode.ai/v2/docs/models/#options)).

## Sources

- Live OpenCode model catalog via the `models` tool (provider `opencode-go`, `all: true`) — variant lists and release dates.
- OpenCode V2 docs — Models / Variants: https://opencode.ai/v2/docs/models/
- OpenCode Go console docs: https://opencode.ai/v2/docs/console/go/
