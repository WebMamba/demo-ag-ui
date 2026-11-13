---
status: accepted
---

# AG-UI through the SDK: the app keeps only what is specific to it

The protocol plumbing this app wrote for ADR 0002 — the runner translating Symfony AI
into events, the client toolbox, the suspending tool executor, the message log, the
JSON Patch diff — is now the AG-UI SDK's (`webmamba/ag-ui`, `-symfony-ai`, `-bundle`),
on Symfony AI 1.0. The app proves the SDK's promise: an agent speaks AG-UI without
AG-UI code in it.

## What changed

- **One endpoint** (`config/packages/ag_ui.yaml`): the `default` agent, with the passage
  plan as shared state. `StreamController` serves it at `/agent/stream`, and the `Theme` and
  `Locale` services state the interface's settings to the agent with `#[AsAgentContext]`;
  the SDK does the rest. The demo no
  longer has one version of the app per step of the talk: only the final one.
- **The passage plan is a typed state class** (`PassagePlan` of `Waypoint`s), injected
  as itself into `add_waypoint`/`remove_waypoint`; the state on the wire is now
  `{waypoints: [...]}`. It describes itself to the model (legs, distances, days).
- **Render tools are gone**: `Agent:Map`, `Agent:SeaState` and `Agent:LinkList` are
  `#[AsAgentComponent]`s, so the component *is* the tool (`render_map`, …). Their
  activities are `twig_component`s with the markup in `content.html`.
- **No server-side loading placeholder**: the client keeps a `render_*` call's line
  pulsing until its component arrives. Loading is a UI choice.
- **`reserve_mooring` is a `ResumableTool`** returning an `Interrupt` whose UI is the
  `Agent:MooringReservation` component; the client finds it as `act_<toolCallId>`.
- **Effects still ride on `CUSTOM`** (ADR 0001), through an `EffectMapping`.
- **Thinking is on for every run**: Claude's signed thinking
  round-trips through AG-UI's encrypted values, which lifts ADR 0002's restriction.
- `TOOL_CALL_RESULT` is now emitted for every server tool result.
