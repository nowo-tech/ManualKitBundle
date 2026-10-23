# Administration

## Users <a id="users"></a>
<!-- tags: accounts, cuentas -->

The demo has two in-memory accounts: `demo` (member) and `admin` (administrator). Members never see this chapter: the
link from [Getting started](01-getting-started.md#first-steps) is only shown to administrators, and the URL answers 404.

## Translations

Run `bin/console nowo:manual:check` to list the translations that are missing or older than their source, and
`bin/console nowo:manual:stamp es 02-admin` after updating one.
