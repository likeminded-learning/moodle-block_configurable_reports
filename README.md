# Configurable Reports block for Moodle (`block_configurable_reports`)

A Moodle block plugin that lets administrators and teachers build custom
reports (SQL, courses, users, categories, timeline, …) and embed them on
the site front page, courses or the user dashboard. Reports are saved,
shareable and exportable, and SQL reports support placeholders so the
same query can be reused across contexts.

## Likeminded fork

This fork of [jleyva/moodle-block_configurable_reports](https://github.com/jleyva/moodle-block_configurable_reports)
(branch `MOODLE_4x_STABLE`) is for Totara 20. It adds:

- **Totara 20 support.** Totara keeps the Moodle 3.4 core API. The plugin
  uses the Moodle 4 classes when they exist, else the Totara ones:
  user name fields, course categories, external API classes, and the
  course id for the remote report scripts.
- **Per-report DataTables options.** With the DataTables report table UI,
  each SQL report can set DataTables options as a JSON object. They are
  merged over the defaults. See the help of the "DataTables options" field.
  Options saved by the 2019 fork are converted when a report is viewed or edited.
- **DataTables Buttons and RowGroup** extensions, for column visibility,
  print and row grouping.
- **Report table styles**: the DataTables 1.13.7 default theme ("display" class), as on datatables.net,
  with a header colour and clear alternate rows.
- **Report repository.** Shared SQL reports come from a GitHub repository
  (setting "Report repository", `owner/repo@ref`, with a read-only token for a
  private repository). This replaces the two jleyva repository settings. Each
  report is a folder with `report.json`, `query.sql` (MySQL), `query.pgsql.sql`
  (PostgreSQL) and `datatables.json`; see the README of
  [likeminded-learning/totara-configurable-reports](https://github.com/likeminded-learning/totara-configurable-reports).
  Manage reports can create a report from the repository and export a report
  in its format. The Custom SQL tab can pull only the SQL or only the
  DataTables options of a repository report.

To update from upstream, merge `MOODLE_4x_STABLE` into this branch. The Totara
changes are in separate commits, to make conflicts easy to find.

The fork does not support the Moodle privacy API on Totara. Totara has no
`core_privacy`, and does not load the provider class.

## Try in Moodle Playground

Click the badge below to open this plugin instantly in
[Moodle Playground](https://moodle-playground.com) — a full Moodle site
running in the browser, with no local install. The demo boots with the
plugin installed, a sample SQL report ready to run, and lands on the
reports management page.

<a href="https://moodle-playground.com/?blueprint-url=https://raw.githubusercontent.com/jleyva/moodle-block_configurablereports/refs/heads/MOODLE_4x_STABLE/blueprint.json" target="_blank" rel="noopener"><img src="https://raw.githubusercontent.com/ateeducacion/action-moodle-playground-pr-preview/refs/heads/main/assets/playground-preview-button.svg" alt="Preview in Moodle Playground" width="200"></a>

## Installation

Install it like any other Moodle block, by copying this directory into
`blocks/configurable_reports` and visiting the notifications page. Usage
documentation, tutorials and the SQL placeholder reference are in the
[Moodle docs](https://docs.moodle.org/en/Configurable_reports).

Released versions are listed in [`CHANGELOG.md`](CHANGELOG.md).

## Credits

Author: Juan Leyva (<https://twitter.com/jleyvadelgado>)

Thanks to:

- Sara Arjona for being a co-maintainer
- Nadav Kavalerchik for developing amazing new features
- Ivan Breziansky for translating the block to Slovak
- Iñaki Arenaza for translating the block documentation to Spanish
- Luis de Vasconcelos for testing the block
- Adam Olley and NetSpot Moodle Partner for improving parts of the Moodle 2 version

Some functionality uses code from
[Admin Report: Custom SQL queries](https://moodle.org/mod/data/view.php?d=13&rid=2884)
by Tim Hunt.

## Working on the plugin

The bundled third party libraries (CodeMirror, DataTables, TableSorter and
pChart) are listed in [`thirdpartylibs.xml`](thirdpartylibs.xml), and
[`readme_moodle.txt`](readme_moodle.txt) says how to import a new version of
each of them.
