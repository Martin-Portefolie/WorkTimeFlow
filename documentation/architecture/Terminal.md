# Terminal Architecture

## Purpose

The terminal is one of the primary command interfaces in WorkTimeFlow.

Traditional UI clicking is also supported, but the terminal is designed to provide a faster workflow for both administrators and normal users.

The terminal is used in:

```text
/admin
/profile
```

The terminal is responsible for:

- command execution
- object previews
- workflow orchestration
- AJAX interactions
- terminal wizard steps
- future optional FormFlow integration

The goal is to create a workspace-like experience instead of many disconnected admin pages.

---

# Frontend

## Main Frontend Controller

Main file:

```text
assets/controllers/terminal_controller.js
```

The Stimulus controller handles terminal interactions in the browser.

Responsibilities:

- command aliases
- command history
- command palette
- AJAX requests
- keyboard shortcuts
- command allowlists
- awaiting/wizard state
- terminal focus handling
- multi-command execution

Examples:

```text
u.l → users:list
p.a → projects:add
```

---

# Shared Terminal Templates

Shared terminal templates live in:

```text
templates/terminals/
```

These templates are shared between:

- admin
- profile

The goal is to keep the terminal UI reusable across domains.

---

## `_terminal.html.twig`

File:

```text
templates/terminals/_terminal.html.twig
```

Purpose:

- renders the main terminal wrapper
- decides whether to load admin or profile terminal UI
- keeps terminal styling shared
- keeps admin/profile-specific terminal logic separated

This template should not contain business logic.

---

## `_line.html.twig`

File:

```text
templates/terminals/_line.html.twig
```

Purpose:

- renders a single terminal response line
- displays success/error state
- displays command feedback
- supports `data-await="1"` for wizard mode

Examples of output:

- success messages
- error messages
- validation messages
- step-by-step prompts
- rendered partial HTML

---

# Admin Terminal Templates

Admin terminal templates live in:

```text
templates/terminals/admin/
```

The admin terminal is used for system-wide management.

Examples:

- users
- clients
- projects
- teams
- rates
- todos
- company settings

---

## Admin Terminal Shell

File:

```text
templates/terminals/admin/_admin.html.twig
```

Purpose:

- renders the admin terminal UI
- initializes the Stimulus controller
- passes the admin command allowlist
- defines the terminal input
- defines the terminal output area
- defines keyboard shortcut tiles
- defines the command palette

This template controls the admin terminal interface, but it should not contain command business logic.

---

# Terminal Commands

Admin terminal command templates live in:

```text
templates/terminals/admin/terminal_commands/
```

Each resource gets one dispatcher template.

Example:

```text
templates/terminals/admin/terminal_commands/users.html.twig
templates/terminals/admin/terminal_commands/clients.html.twig
templates/terminals/admin/terminal_commands/projects.html.twig
templates/terminals/admin/terminal_commands/teams.html.twig
templates/terminals/admin/terminal_commands/rates.html.twig
templates/terminals/admin/terminal_commands/todos.html.twig
```

A dispatcher template decides which universal CRUD partial should be rendered.

Example:

```text
users:list
→ users.html.twig
→ partials/_resource_list.html.twig

users:show
→ users.html.twig
→ partials/_resource_show.html.twig

users:add
→ users.html.twig
→ partials/_resource_add.html.twig
```

---

# Universal CRUD Partials

Universal CRUD partials live in:

```text
templates/terminals/admin/terminal_commands/partials/
```

Recommended structure:

```text
partials/
├── _resource_add.html.twig
├── _resource_edit.html.twig
├── _resource_show.html.twig
├── _resource_list.html.twig
├── _resource_delete.html.twig
├── _resource_error.html.twig
└── _step-by-step_prompt.html.twig
```

These partials are shared across resources.

The goal is to avoid one Twig file per action per entity.

Instead of this:

```text
_users_list_admin.html.twig
_users_show_admin.html.twig
_clients_list_admin.html.twig
_clients_show_admin.html.twig
_projects_list_admin.html.twig
_projects_show_admin.html.twig
```

We use this:

```text
users.html.twig
clients.html.twig
projects.html.twig
partials/_resource_list.html.twig
partials/_resource_show.html.twig
```

This keeps terminal rendering simpler and easier to maintain.

---

## `_resource_list.html.twig`

Purpose:

- renders table-based list output
- used by commands like `users:list`, `clients:list`, `projects:list`

The service provides:

```php
[
    'view' => 'list',
    'resource' => 'users',
    'title' => 'Users',
    'columns' => [],
    'rows' => [],
]
```

---

## `_resource_show.html.twig`

Purpose:

- renders a single resource
- used by commands like `users:show`, `clients:show`, `projects:show`

The service provides:

```php
[
    'view' => 'show',
    'resource' => 'users',
    'title' => 'User',
    'item' => $user,
]
```

---

## `_resource_add.html.twig`

Purpose:

- renders add/create result output
- used after successful create commands
- may also render validation feedback

Command examples:

```text
users:add
clients:add
projects:add
```

---

## `_resource_edit.html.twig`

Purpose:

- renders update result output
- used after successful edit/update commands

Command examples:

```text
users:update
clients:update
projects:update
```

---

## `_resource_delete.html.twig`

Purpose:

- renders delete/deactivation result output

Command examples:

```text
users:delete
clients:delete
projects:delete
```

---

## `_resource_error.html.twig`

Purpose:

- renders command errors
- renders missing data messages
- renders invalid command feedback

---

## `_step-by-step_prompt.html.twig`

Purpose:

- renders terminal wizard prompts
- supports multi-step terminal commands
- works with `TerminalStateService`

Example flow:

```text
projects:add
→ ask for project name
→ ask for client
→ ask for deadline
→ create project
```

This is the current replacement for Symfony FormFlow inside the terminal.

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

The profile domain must not expose admin-only data.

---

# Object Preview Area

The terminal supports an object preview area below the terminal itself.

Purpose:

- render affected entities
- display previews
- show summaries
- display workflow progress
- provide visual feedback

Example command:

```text
users:add name=Test email=test@test.com
```

Can produce:

```text
Terminal line:
User created successfully.

Object preview:
users.html.twig → _resource_show.html.twig
```

The goal is to combine command workflows with visual UI feedback.

---

# Backend Flow

Main terminal request flow:

```text
User Input
→ Stimulus Controller
→ AdminController
→ TerminalService
→ Terminal Resource Service
→ Repository / Domain Service
→ Twig Dispatcher Template
→ Universal CRUD Partial
→ Terminal Response
```

---

# Backend Architecture

## Controller

The controller is responsible for HTTP only.

It should:

- receive the request
- read the terminal input
- call the terminal service
- render the returned Twig response

It should not:

- parse command arguments
- contain business logic
- query repositories directly
- create/update entities directly

---

## Terminal Services

Terminal services are responsible for command handling.

Recommended structure:

```text
src/Service/Terminal/
├── ArgsParser.php
├── DateInputParser.php
├── TerminalStateService.php
└── Admin/
    ├── TerminalUserService.php
    ├── TerminalClientService.php
    ├── TerminalProjectService.php
    ├── TerminalTeamService.php
    ├── TerminalRateService.php
    └── TerminalTodoService.php
```

---

## `ArgsParser`

File:

```text
src/Service/Terminal/ArgsParser.php
```

Purpose:

`ArgsParser` converts raw terminal input into structured arguments.

Example input:

```text
users:list --limit=20 --q=martin
```

Becomes:

```php
[
    'limit' => '20',
    'q' => 'martin',
]
```

The parser should only parse input.

It should not:

- query the database
- validate business rules
- create entities
- render Twig

---

## `DateInputParser`

File:

```text
src/Service/Terminal/DateInputParser.php
```

Purpose:

`DateInputParser` converts human-friendly date input into real date objects.

Examples:

```text
today
tomorrow
next monday
2026-05-28
```

This keeps date parsing out of command services.

Command services should not need to know how natural date input is parsed.

---

## `TerminalStateService`

File:

```text
src/Service/Terminal/TerminalStateService.php
```

Purpose:

`TerminalStateService` stores temporary terminal wizard state.

It is used when a command needs multiple inputs.

Example:

```text
projects:add
→ ask for project name
→ ask for client
→ ask for deadline
→ create project
```

The state service remembers:

- active command
- current step
- collected answers
- previous errors
- preview data

This is the reason we do not need Symfony FormFlow yet for terminal commands.

---

## Admin Terminal Resource Services

Admin terminal resource services contain command logic for one admin resource.

Example:

```text
src/Service/Terminal/Admin/TerminalUserService.php
```

Purpose:

- handle user terminal commands
- call repositories
- call domain/admin services when needed
- return normalized terminal payloads

Example methods:

```php
list()
show()
add()
edit()
delete()
```

These services should return data, not HTML directly.

Example return:

```php
[
    'success' => true,
    'template' => 'terminals/admin/terminal_commands/users.html.twig',
    'vars' => [
        'view' => 'list',
        'resource' => 'users',
        'title' => 'Users',
        'columns' => [],
        'rows' => [],
    ],
]
```

---

# Repositories

Repositories are the query layer.

They should:

- find entities
- fetch list rows
- perform search queries
- provide preview data for terminal prompts

They should not:

- contain terminal command logic
- render templates
- handle wizard state
- create terminal response payloads

---

# Forms

Forms are grouped by domain:

```text
src/Form/
├── Admin/
└── Profile/
```

Use one form type per resource where possible.

Example:

```text
src/Form/Admin/UserAdminType.php
```

Do not create separate forms for every action unless necessary.

Preferred:

```text
UserAdminType.php
```

with options like:

```php
'mode' => 'create'
'mode' => 'update'
```

---

# Current Decision: No FormFlow Yet

Symfony FormFlow is not used yet for terminal commands.

Reason:

The terminal already has its own wizard system:

```text
TerminalStateService
_step-by-step_prompt.html.twig
```

FormFlow may be useful later for normal HTML multi-step forms.

For the terminal-first UI, the current terminal wizard is simpler and easier to control.

---

# Future Direction

Long-term goals:

- reactive object previews
- reusable workflow steps
- smarter command parsing
- workspace-oriented admin experience
- reduced controller complexity
- reduced duplicated Twig templates
- one command template per resource
- universal CRUD partials
- optional Symfony FormFlow for non-terminal multi-step forms
