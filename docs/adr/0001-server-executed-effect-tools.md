---
status: accepted (partly superseded by 0002: render tools now use ACTIVITY events, and interrupts provide the consent gate; and since then the Effect/RefreshStrategy classes and the cache storage are gone — theme and language are plain in-memory services, announced as CUSTOM turbo_agent.theme / turbo_agent.locale)
---

# Effect tools execute on the server, not as AG-UI frontend tools

AG-UI's answer to "a tool that changes the interface" is the **frontend tool**: the
client declares it in `RunAgentInput.tools`, the client executes it, the backend runs
no code, emits no `TOOL_CALL_RESULT`, and the result reaches the agent only as a tool
message in the *next* run's input — the protocol has no mid-run channel back from the
consumer. We do the opposite: effect tools are ordinary server-side tools, and the
change is announced mid-stream on a `CUSTOM` event.

## Why

Effect values are persisted server-side, and the browser cannot write server-side
storage — so the client simply cannot execute these tools. Following the protocol's
recommendation would also end the model's turn on every effect and spend an entire
extra HTTP round trip to tell it that a theme toggle worked.

This is not a departure from AG-UI so much as a use of its other half: backend-executed
tools are first-class in the spec, and `CUSTOM` is its sanctioned escape hatch for
application signals (refresh strategy is application space; no standard event covers
it, now that `STATE_*` is unused — see below).

## Considered and rejected

- **Frontend tools, per the spec.** Cannot write server state; costs a run per effect.
- **`STATE_SNAPSHOT` / `STATE_DELTA`.** The natural protocol fit for something like a
  theme, and rejected deliberately: effects here are one-shot commands, not shared
  state, so there is no client→agent state channel and no round-tripped `state`. The
  agent instead learns current values from a system message composed server-side. If
  effects ever need to be readable and writable from both sides, this is the decision
  to revisit first.
- **Reusing `TOOL_CALL_RESULT`.** Spec-legal for backend tools, but its `content` is
  the model-facing result; putting a refresh strategy there feeds machinery back into
  the model's context on every subsequent turn.

## Consequences

- A conforming AG-UI client that ignores unknown `CUSTOM` names sees our runs as
  perfectly ordinary — it just won't apply any effects. That is the intended failure
  mode.
- Because execution happens server-side, a confirmation prompt in the browser would
  necessarily run *after* the mutation. We deliberately ship no consent gate: every
  effect today is trivially reversible. A destructive effect tool would need a
  pre-execution gate this architecture cannot currently express.
- Effect values are stored in the cache keyed by session id, not in the session
  itself. Symfony refuses to start a session once headers are sent, and a session held
  open across an SSE response would hold PHP's exclusive lock for the life of the
  stream — blocking the very refresh request the effect just asked for.
