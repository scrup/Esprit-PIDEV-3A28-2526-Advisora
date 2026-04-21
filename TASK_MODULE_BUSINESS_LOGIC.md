# Task Module Business Logic

## Purpose

This document captures the business logic of the `Task` module coming from the Java PI side, before implementing it fully in Symfony.

The goal is to keep a clear functional reference for the team:

- what a task represents,
- how the screen is expected to behave,
- which permissions apply,
- how validation and progression should work,
- and what Symfony will need to reproduce later.

## Functional Positioning

The `Task` module is not the same thing as `Project + Decision`.

- `Project` handles the main project record.
- `Decision` handles validation workflow around the project.
- `Task` handles the operational follow-up of the project.

So the task module is a separate functional block, even if it is attached to a project.

## Core Business Rules

### 1. Task ownership

A task belongs to exactly one project.

Expected minimum business data:

- `id`
- `projectId`
- `title`
- `status`
- `weight`

Confirmed in the current Symfony domain model:

- `Task` is linked to `Project`
- `Task` already contains `title`, `status`, `weight`

Additional fields already present in the current entity but secondary for the first business version:

- `duration_days`
- `last_warning_date`
- `created_at`

### 2. Functional objective of the screen

The task screen is mainly used to:

- create tasks,
- edit tasks,
- delete tasks,
- organize tasks by progress state,
- monitor the operational advancement of a project.

This is an execution and tracking screen, not a decision-validation screen.

### 3. Task statuses

The expected business workflow is based on 3 status groups:

- `A faire`
- `En cours`
- `Terminee`

These 3 statuses are expected to drive:

- the visual grouping into 3 separate lists,
- the operational state of each task,
- the project progression calculation.

Default assumption for future Symfony implementation:

- internal technical values can be normalized later,
- UI labels should stay in French.

### 4. Meaning of weight

`weight` represents the importance of the task in the global progression of the project.

It is not only a display order or a cosmetic priority.

This means:

- a task with a higher weight contributes more to project progress,
- progression should be based on completed work weighted by `weight`.

### 5. Permissions

The allowed managers of tasks are:

- the project manager / project owner,
- the admin.

If the current user does not have permission:

- form fields must be read-only or disabled,
- action buttons must be disabled,
- a read-only hint must explain the restriction.

The business intention is not just backend security, but also clear UI behavior.

## Expected Screen Behavior

### 1. Initialization

The task screen must always start with a current project context.

Expected behavior at initialization:

- load the current project,
- load the project tasks,
- load available statuses,
- set default form values,
- apply permission mode,
- display read-only hint if necessary.

### 2. Three-list organization

Tasks must be displayed in 3 distinct lists:

- `A faire`
- `En cours`
- `Terminee`

Each task appears in one list only, according to its status.

### 3. Selection behavior

Selection must be mutually exclusive across the 3 lists.

Business expectation:

- selecting one task clears selection in the other two lists,
- the form is filled with the selected task values,
- if no task is selected, the form switches back to creation mode.

### 4. Form role

The same form supports both:

- creation when no task is selected,
- update when a task is selected.

So the UI is expected to behave like:

- empty form -> add mode,
- populated form -> edit mode.

### 5. Validation

Minimum business validation rules:

- `title` is required,
- `title` must not be blank after trimming,
- `weight` must be an integer,
- `weight >= 1`.

These rules must later exist at least server-side in Symfony.

### 6. Save flow

Expected save logic:

- no selection -> create a new task,
- existing selection -> update the selected task,
- after save:
  - reload task lists,
  - recalculate counters,
  - recalculate progression,
  - reset the form into a coherent state.

### 7. Delete flow

Expected delete logic:

- no selected task -> deletion is blocked,
- selected task + permission -> delete,
- after deletion:
  - reload task lists,
  - refresh counters,
  - refresh progression,
  - clear selection and form.

### 8. Reload responsibility

Reload is a key business step, not just a visual refresh.

It should:

- fetch the tasks of the current project,
- regroup them by status,
- refresh counters,
- refresh progression,
- restore UI consistency.

### 9. Navigation

Expected navigation behavior:

- `Back to projects` returns to the project context,
- `Close` closes the task screen depending on how it was opened,
- unsaved modifications should not be silently discarded without a clear decision.

## Progression Logic

The business interpretation currently retained is:

- project progress depends on task completion,
- task completion impact is weighted by `weight`.

Default formula to keep in mind for later implementation:

- progress = completed task weight / total task weight

Open point still to confirm later:

- whether tasks `En cours` should count partially or not.

For now, the safest business default is:

- only `Terminee` contributes to completed progress.

## Error Message Expectations

Messages should be understandable and business-oriented.

They should distinguish:

- validation error,
- loading error,
- save error,
- delete error,
- permission error.

Recommended style:

- short title,
- explicit body,
- French wording for current business users.

## Direction For Future Symfony Work

When the module is implemented in Symfony, it should reproduce the business logic without necessarily copying the Java UI.

Minimum Symfony responsibilities later:

- map `Task` to `Project`,
- expose the 3 statuses,
- enforce permissions for project manager + admin,
- validate `title` and `weight` server-side,
- support create / update / delete,
- group tasks by status,
- compute weighted progression.

## Confirmed Defaults

These defaults are currently retained as the working business reference:

- `Task` is a project-level operational module
- statuses are `A faire / En cours / Terminee`
- `weight` contributes to progression
- authorized managers are project manager + admin
- the module is separate from `Decision`
- Symfony implementation comes after business understanding

## Open Questions For Later

These points are still business decisions to confirm before full Symfony implementation:

- Do `En cours` tasks contribute partially to progress?
- Is there a maximum allowed weight?
- Are status transitions fully free or constrained?
- Can completed tasks always be deleted?
- Should labels/messages be prepared for i18n immediately?
