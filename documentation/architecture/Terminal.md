# Terminal Architecture

## Purpose

The terminal acts as one of the primary command interfaces in WorkTimeFlow.

Traditional UI clicking is also supported, but the terminal is designed to provide a faster and more flexible workflow for both administrators and normal users.

The terminal is used in:
- `/admin`
- `/profile`

The terminal is responsible for:
- command execution
- object previews
- workflow orchestration
- AJAX interactions
- future FormFlow integration

The goal is to create a workspace-like experience instead of many disconnected admin pages.

---

# Frontend

## Main Frontend Controller

Main file:

```text
assets/controllers/terminal_controller.js
```

This Stimulus controller handles most terminal interactions in the browser.

Responsibilities:
- aliases
- history
- command palette
- AJAX requests
- keyboard shortcuts
- command allowlists
- awaiting/wizard state
- terminal focus handling

Examples:
- `u.l` → `users:list`
- `p.a` → `projects:add`

The controller also manages:
- command history
- terminal focus
- client-side validation
- palette suggestions
- multi-command execution

---

# Shared Terminal Templates

Shared terminal UI templates live in:

```text
templates/terminal/
```

These templates are shared between:
- admin
- profile

The goal is to keep terminal UI reusable across domains.

---

## _terminal.html.twig

File:

```text
templates/terminal/_terminal.html.twig
```

Purpose:
- renders the main terminal shell
- initializes the Stimulus controller
- passes command allowlists
- configures terminal behavior
- handles admin/profile terminal setup

This template acts as the main terminal container.

It is responsible for:
- input area
- output area
- command suggestions
- palette support
- sticky terminal layout

---

## _line.html.twig

File:

```text
templates/terminal/_line.html.twig
```

Purpose:
- renders a single terminal response line

Examples:
- success messages
- error messages
- command feedback
- validation messages
- awaiting/wizard prompts

This template is used repeatedly whenever a command returns output.

It also supports:
- success/error states
- `data-await="1"`
- future multi-step workflows

---

## _help_admin.html.twig

File:

```text
templates/terminal/_help_admin.html.twig
```

Purpose:
- renders available admin commands
- displays command examples
- displays terminal hints

This acts as a quick reference system for terminal users.

---

## _step-by-step_prompt.html.twig

File:

```text
templates/terminal/_step-by-step_prompt.html.twig
```

Purpose:
- renders wizard-like prompts
- supports multi-step terminal workflows
- prepares future FormFlow integration

Example:

```text
Enter project name:
Enter client:
Enter deadline:
```

---

# Admin Domain

Admin templates live in:

```text
templates/admin/
```

The admin domain is responsible for managing shared system data.

Examples:
- users
- clients
- projects
- teams
- company settings
- rates

Admin templates are allowed to:
- render system-wide data
- perform management actions
- show lists and previews
- render summaries

---

## Admin Layout

Main file:

```text
templates/admin/admin.html.twig
```

Purpose:
- main admin workspace layout
- sticky terminal integration
- object preview workspace
- admin shell styling

This template acts as the primary admin workspace.

---

## Admin Partials

Examples:

```text
templates/admin/_users_list_admin.html.twig
templates/admin/_users_show_admin.html.twig
templates/admin/_clients_list_admin.html.twig
templates/admin/_projects_show_admin.html.twig
```

Purpose:
- render terminal command results
- render object previews
- render lists
- render summaries
- render workflow responses

These partials are usually rendered after terminal commands execute.

Example:

```text
users:list
→ _users_list_admin.html.twig

projects:show
→ _projects_show_admin.html.twig
```

---

# Profile Domain

Profile templates live in:

```text
templates/profile/
```

The profile domain is used by normal users.

Profile templates should only render data related to the currently authenticated user.

Examples:
- personal tasks
- time schedules
- timelogs
- profile settings
- password changes

The profile domain is intentionally separated from admin functionality.

---

# Partials

Partials are small reusable Twig templates.

Examples:

```text
_users_show_admin.html.twig
_projects_list_admin.html.twig
_team_created_summary_admin.html.twig
```

Partials are used for:
- lists
- object previews
- summaries
- validation responses
- workflow steps

The goal is to keep terminal responses modular and reusable.

---

# Object Preview Area

The terminal supports an object preview area below the terminal itself.

Purpose:
- render affected entities
- display previews
- show summaries
- display workflow progress
- provide visual feedback

Example:

```text
users:add name=Test email=test@test.com
```

Can produce:

```text
Terminal line:
User created successfully.

Object preview:
_users_show_admin.html.twig
```

The goal is to combine:
- command workflows
- visual UI feedback
- reusable partial rendering

---

# Backend Flow

Main backend flow:

```text
User Input
→ Stimulus Controller
→ AdminController
→ TerminalService
→ Domain Service
→ Twig Partial
→ Terminal Response
```

---

# Future Direction

Long-term goals:
- FormFlow integration
- reactive object previews
- reusable workflow steps
- smarter command parsing
- workspace-oriented admin experience
- reduced controller complexity
- reduced duplicated form logic
