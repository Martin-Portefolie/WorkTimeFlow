# Company, Clients and Users browser selectors

Use `data-testid` for browser interactions, not Tailwind classes or translated text.
Repeated controls deliberately share a test identifier: scope the selector to their
editor, form or row. Use IDs from the test fixtures instead of assuming database IDs.
HTML `id` attributes identify individual inputs; `name` attributes remain the submitted field names.

## Navigation and Company

| Control | CSS selector |
| --- | --- |
| Dashboard | `[data-testid="nav-dashboard"]` |
| Clients | `[data-testid="nav-clients"]` |
| Main content | `[data-testid="admin-content"]` |
| Company panel | `[data-testid="company-panel"]` |
| Company name | `[data-testid="company-name-input"]` |
| Save name | `[data-testid="company-name-save"]` |
| Name status | `[data-testid="company-name-editor"] [data-testid="save-status"]` |
| Logo file | `[data-testid="company-logo-input"]` |
| Save logo | `[data-testid="company-logo-save"]` |
| Logo preview | `[data-testid="company-logo-preview"]` |
| Sidebar name / logo | `[data-testid="company-brand-name"]` / `[data-testid="company-brand-logo"]` |
| Currency region | `[data-testid="company-currency-region"]` |
| Currency | `[data-testid="company-currency"]` |
| Save currency | `[data-testid="company-currency-save"]` |
| Existing rate | `[data-testid="company-rate-row"][data-rate-id="42"]` |
| New rate form | `[data-testid="company-rate-new"]` |

Inside a rate row or the new-rate form, use `rate-name`, `rate-value`,
`rate-save` and (existing rows only) `rate-delete` as `data-testid` values.
Rate inputs are associated with their form using `form`; existing inputs are
outside the form element, so scope to the row when finding them.
Currency and rate save feedback is under `[data-testid="company-rates"] [data-testid="save-status"]`.

## Clients

| Control | CSS selector |
| --- | --- |
| Client list | `[data-testid="clients-panel"]` |
| Create button | `[data-testid="client-create"]` |
| Draft row | `[data-testid="client-rows"] [data-client-row="new"]` |
| Existing row | `[data-testid="client-rows"] [data-client-row="42"]` |
| Contact person within a row | `[data-testid="client-field-contactPerson"]` |
| Save within a row | `[data-testid="client-save"]` |
| Cancel draft | `[data-testid="client-cancel"]` |
| Project count within a row | `[data-testid="client-projects-open"]` |
| Open projects modal within a row | `[data-testid="client-projects-dialog"][open]` |
| Active projects within the modal | `[data-testid="client-projects-active"]` |
| Inactive projects within the modal | `[data-testid="client-projects-inactive"]` |
| Specific project within the modal | `[data-testid="client-project-open"][data-project-id="9"]` |
| Close modal | `[data-testid="client-projects-close"]` |

Field identifiers use the entity field names: `name`, `contactPerson`,
`contactEmail`, `contactPhone`, `adress`, `postalCode`, `city`, `country`.
`adress` intentionally preserves the application's existing spelling.

The client detail view reached through the terminal uses `client-details` with
`data-client-id`, and the same field/save identifiers scoped to that detail view.

## Waiting for updates

- Use visibility checks for dialogs: their contents exist while closed.
- Re-query elements after AJAX saves; the client row or Company rates panel is replaced.
- A created client changes from `data-client-row="new"` to its persisted ID.
- Status messages use `data-testid="save-status"` and `data-state="saving|success|error"`.
  Also check for nonempty text: the empty initial status has `data-state="success"` too.
- Successful status text disappears after 3.5 seconds. Assert persisted field values
  as well, rather than relying solely on the temporary message.
- Select region/currency by stable option values such as `europe` and `DKK`.
- Wait for the new content/row/modal state, rather than using fixed sleeps.

See [browser-tests.md](browser-tests.md) for Panther setup and commands.

## Users

| Control | CSS selector |
| --- | --- |
| Users navigation | `[data-testid="nav-users"]` |
| Users list | `[data-testid="users-panel"]` |
| Create button | `[data-testid="user-create"]` |
| Draft row | `[data-user-row="new"]` |
| Existing row | `[data-user-row="42"]` |
| Name / email within row | `[data-testid="user-field-username"]` / `[data-testid="user-field-email"]` |
| Save / cancel draft | `[data-testid="user-save"]` / `[data-testid="user-cancel"]` |

Role and status use `user-field-role` and `user-field-active` selectors. Both are
editable for other users; your own controls are disabled and the server also rejects
self-deactivation or removing your own administrator role. New accounts default to
active `ROLE_USER`; both dropdowns can be changed before creation. The randomly
generated initial password is sent through the existing welcome
email and Messenger queue. Passwords are never rendered into the GUI. Users are saved through `AdminController` and
`UserAdminService`. The legacy UsersController remains reference code.
