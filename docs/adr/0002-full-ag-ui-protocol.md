---
status: accepted, partly superseded by 0003
---

# Implement the rest of AG-UI: RunAgentInput, activities, shared state, frontend tools, interrupts

ADR 0001 left most of the protocol aside. The app now demonstrates it end to end, for
a talk, so each part is used where it fits a Symfony UX app rather than bolted on.

## Decisions

- **`RunAgentInput` is the request body**, bound with `#[MapRequestPayload]`. The client
  owns the conversation and keeps it from `MESSAGES_SNAPSHOT`, emitted at the end of
  every run, so tool calls and results now survive across runs. Nothing about a thread
  is stored server-side.
- **Render tools use `ACTIVITY_SNAPSHOT` / `ACTIVITY_DELTA`** instead of the
  `turbo_agent.render` CUSTOM event. Activity messages are frontend-only by spec —
  exactly the "markup for the browser, a sentence for the model" split. A placeholder
  snapshot goes out when the call starts; a delta patches in the real markup.
- **Effects stay on `CUSTOM`** (ADR 0001 still holds): they are commands, not state.
- **The passage plan is AG-UI shared state.** It lives in the browser, rendered by the
  `PassagePanel` Live Component; tools change a request-scoped `PassagePlan`, and the runner
  diffs it into `STATE_DELTA` JSON Patches. User edits go back in the next run's `state`.
- **Frontend tools are exposed, not executed.** `ClientToolbox` adds them to the agent's
  toolbox; `StreamingToolExecutor` runs the batch's server tools, then suspends the run.
- **Interrupts end the run** with `RUN_FINISHED { outcome: interrupt }`. The interrupt
  id is derived from the tool call id (`int_<toolCallId>`), so resuming needs no server
  state: the open call is found in the messages the client sends back. This is the
  pre-execution consent gate ADR 0001 said was missing.
- **Reasoning** streams as `REASONING_*`, with Claude's adaptive thinking — only on
  runs that start from a user message: continuing a tool call would require the previous
  turn's thinking blocks, which AG-UI messages don't carry.

## Consequences

- `RUN_ERROR` is now terminal (no `RUN_FINISHED` after it), as the spec requires.
- A new user message cannot skip an open interrupt or frontend tool call: the run errors.
- `TOOL_CALL_RESULT` is only emitted for resumed interrupts, where the call was announced
  in an earlier run. Other results reach the client through `MESSAGES_SNAPSHOT`.
