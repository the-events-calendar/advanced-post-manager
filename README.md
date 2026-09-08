# Advanced Post Manager

Advanced filtering controls for custom post types.

## Specs and planning

Work on this repository is planned before it is written, using
[OpenSpec](https://github.com/Fission-AI/OpenSpec). **This is enforced** — every
pull request is checked for a plan.

Plans do not live here. A TEC feature routinely spans several repositories, so a
spec kept in one of them is invisible from the others. They all live in one shared
store instead: [`the-events-calendar/plans`](https://github.com/the-events-calendar/plans).

### First time only

```bash
npm install -g @fission-ai/openspec
git clone git@github.com:the-events-calendar/plans.git ~/repos/tec-plans
openspec store register ~/repos/tec-plans --id tec-plans
```

The clone path is yours to choose. The `--id` is not — every command refers to the
store as `tec-plans`.

### Working on a ticket

1. **Create the change before writing code**, named after the ticket in lower
   case. OpenSpec requires kebab-case and rejects anything else, so the ticket
   `SOFT-1234` becomes the change `soft-1234`:
   `openspec new change soft-1234 --store tec-plans --description "what this does"`
2. Write the proposal, then commit and push it in the plans repo.
3. Branch as usual: `feat/SOFT-1234/short-desc`.
4. Implement. If the work shows the plan was wrong — it often does — update the
   change rather than letting the plan and the code drift apart.
5. Open the PR. The template asks for the change ID, and CI checks the plan exists
   and is still active.

Every `openspec` command takes `--store tec-plans`. There is no default and no
repo-side link, so omitting it writes the change into whatever repository you
happen to be standing in.

### Where the rest is written down

This section covers what is specific to working here. The
[`tec-openspec` skill](https://github.com/stellarwp/skills-se) covers the
workflow itself — writing a proposal worth reviewing, keeping it current, and
archiving it once (after the last repository merges, not per repo). Install it with:

The below will work only once the `stellarwp/skills-se` becomes public.

```
/plugin marketplace add stellarwp/skills-se
/plugin install nexcess-se
```

## Running the tests

Tests are Codeception (`lucatume/wp-browser`) integration tests run inside Docker via [slic](https://github.com/stellarwp/slic); CI runs the same `slic run` command.

### Setup

All sibling plugins live next to `advanced-post-manager` in the same parent directory, and `slic` is pointed at that parent. From the directory that contains `advanced-post-manager`:

```bash
# 1. Sibling checkouts (APM's integration suite activates both).
#    Use the branch matching yours if it exists, otherwise the base branch.
git clone --recursive git@github.com:the-events-calendar/the-events-calendar.git
git clone --recursive git@github.com:the-events-calendar/events-pro.git

# 2. Point slic at this parent directory and turn off interactivity.
slic here
slic interactive off
slic build-prompt off
slic build-subdir off
slic xdebug off
slic info

# 3. Install dependencies for each plugin (TEC and ECP are runtime deps only).
slic use the-events-calendar         && slic composer install --no-dev
slic use the-events-calendar/common  && slic composer install --no-dev
slic use events-pro                  && slic composer install --no-dev
slic use advanced-post-manager       && slic composer install

# 4. Start the WordPress container.
slic up wordpress
```

### Running a suite

```bash
slic use advanced-post-manager
slic run integration

# Single file
slic run integration tests/integration/Tribe_FiltersTest.php

# Single test / filter
slic run integration --filter=test_name
```

### Suites

| Suite | Covers | CI on every PR |
|---|---|---|
| `integration` | WPLoader-booted tests against a real WP install with TEC, ECP and APM activated (`tests/integration/`) | Yes |

`integration` is the only suite in the repo (`tests/integration.suite.dist.yml`) and the only entry in CI's matrix.

### How this differs from CI

- CI pins WordPress to **6.6** (`slic wp core update --version=6.6 --force && slic wp core update-db`) and installs/activates `twentytwenty`. Locally the container's default WP is fine unless you are chasing a version-specific failure.
- CI adds `--ext DotReporter` for compact output; skip it locally to see per-test names.
- CI sets a shared composer cache, prunes Docker networks between steps, and skips the whole test job when a PR touches no `.php` files. None of that matters locally.
