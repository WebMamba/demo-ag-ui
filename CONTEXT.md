# Passage Agent

An AI navigator for an ocean passage (a Pacific crossing, Panama → Tahiti). A Symfony
application where an LLM agent answers through server-rendered UI rather than plain
text: it streams AG-UI Protocol events over SSE, and its tools either
produce markup for the transcript or change the application around it.

## Language

**Render tool**:
A tool whose output is content belonging to one assistant message. Its result is
positional and immutable — it stays with that message forever, and replaying it is
always correct.
_Avoid_: UI tool, component tool, generative UI tool

**Effect tool**:
A tool whose output is a change to the application outside the assistant message.
Its result is global and current — only the latest one means anything, and replaying
it re-applies a stale change. Here: `set_theme` and `set_locale`, which change the
`Theme` and `Locale` services and are announced to the page as a `CUSTOM` event.
_Avoid_: frontend tool (see below), action tool, side-effect tool

**Frontend tool**:
AG-UI's term for a tool the *client* declares (in `RunAgentInput.tools`) and executes.
Here: `get_boat_position`, declared by the agent-chat Stimulus controller. The server
offers it to the model but never runs it: the run stops, the browser runs it, and the
result comes back as a tool message in the next run.
_Avoid_: client tool (in prose), browser tool

**Interrupt**:
What a tool returns when it needs the skipper's decision before it can act (`reserve_mooring`).
The run finishes with an `interrupt` outcome, and the next run *resumes* it with the
user's answer, which the tool's `resume()` turns into its real result.
_Avoid_: confirmation, approval step, pause

**Shared state**:
The AG-UI `state` both sides read and write — here, the passage plan (waypoints). It lives in the browser,
travels in every `RunAgentInput.state`, and comes back as `STATE_DELTA` patches when a
tool changes it. Unlike an effect, it is data, not a command.
_Avoid_: UI state (that is the effect values: theme, locale)

**Passage plan**:
The waypoints of the crossing, in sailing order. Legs (great-circle distance in nautical
miles, days at sea at 6 knots) are derived from them, never stored.
_Avoid_: trip, itinerary, route (a route is what the chart draws)

**Dead reckoning**:
Predicting the boat's position from its current position, speed and heading over a
number of hours, along a great circle, assuming they stay constant (`predict_position`).
The instruments it reads are simulated in the demo.
_Avoid_: routing, weather routing (that also accounts for wind and polars)

**Course to steer**:
The great-circle bearing from the boat to the next waypoint of the passage plan (the end
of the leg the boat is on), shown big on the chart plotter with the turn to make from
the current heading. Derived from the shared state and the instruments, never stored.
_Avoid_: heading (that is where the boat points now), route

**Night watch**:
The red-light theme: what a sailor on watch uses to keep their night vision. A theme
effect like light and dark.

**Activity**:
A frontend-only AG-UI message carrying server-rendered markup: what a render tool shows,
or the question an interrupt asks. Never sent back to the model.

**Run**:
One execution of the agent, from a `RunAgentInput` to `RUN_FINISHED`, carrying however
many model calls and tool calls it takes. Usually ends with the agent's answer; ends
early when it needs a frontend tool or an interrupt answered. Identified by a run id.

**Thread**:
The conversation a run belongs to, identified by a thread id that outlives any
single run.
_Avoid_: conversation, session (session means the HTTP session here)
