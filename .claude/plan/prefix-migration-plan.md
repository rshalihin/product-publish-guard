# Product Publish Guard — Prefix Migration Plan (`wcpg` → `sit_wcpg` / `sit-wcpg`)

**Status:** planning only. No code changes are made by authoring this document.
**Relation to other plans:** this amends `coding-plan.md` §2 ("Global prefix") and §10.4 (CSS). Per `coding-plan.md` §0 rule 4, the plan documents are updated **first**, then the code.
**Decision:** new prefix chosen by the user on 2026-09-25: **`sit_wcpg`** (snake contexts) / **`sit-wcpg`** (kebab contexts).

---

## 1. The prefix, per context

Both spellings are the **same** prefix; which one to use depends on where the name lives. Using one exact form per context is what keeps the names greppable and enforceable.

| Context | Form | Example |
|---|---|---|
| PHP constants | `SIT_WCPG_` | `SIT_WCPG_VERSION` |
| Global functions, global variables | `sit_wcpg_` | `sit_wcpg_bootstrap()`, `$sit_wcpg_user_ids` |
| Hooks (actions / filters) | `sit_wcpg_` | `sit_wcpg_register_rules` |
| Options, transients, post meta | `sit_wcpg_` (hidden meta: `_sit_wcpg_`) | `sit_wcpg_settings` |
| Object-cache group | `sit_wcpg` | `'sit_wcpg'` |
| REST namespace | `sit-wcpg/v1` | `/wp-json/sit-wcpg/v1/products/12/validate` |
| REST / `WP_Error` / settings-error codes | `sit_wcpg_` | `sit_wcpg_forbidden` |
| Meta box id, list-column key | `sit_wcpg_` | `sit_wcpg_product_checklist` |
| Script & style handles, admin page slugs, HTML ids | `sit-wcpg-` | `sit-wcpg-editor`, `sit-wcpg-settings` |
| CSS classes (BEM) | `sit-wcpg-block__element--modifier` | `.sit-wcpg-checklist__item--fail` |
| JS globals | `sitWcpg` + PascalCase | `window.sitWcpgEditorData` |
| Test / audit IDs in docs | `SIT-WCPG-` | `SIT-WCPG-REST-1` |
| PHP namespace | **unchanged** `ProductPublishGuard\<Concern>` | `ProductPublishGuard\Engine\Validator` |
| Text domain, slug, main file | **unchanged** `product-publish-guard` | `__( '…', 'product-publish-guard' )` |

Rule: every string that WordPress, WooCommerce or the browser stores **globally** (outside our PHP namespace) carries the prefix, and each such string is defined **once**, as a class constant, and referenced from there.

### 1.1 Consequence: the old prefix is a substring of the new one
`wcpg` is inside `sit_wcpg`. Two things follow and **must** be respected:

1. **A plain replace is not idempotent.** `s/wcpg_/sit_wcpg_/` run twice produces `sit_sit_wcpg_`. Every replacement must skip text that's already prefixed (§3.2).
2. **A plain `grep wcpg` can't prove the migration is finished**, because every new name matches too. The leftover check must look for `wcpg` **not** preceded by `sit_`, `sit-`, `SIT_`, `SIT-` or `sit` (camelCase) (§3.3).

---

## 2. Current state (audited 2026-09-25)

| Where | What exists today |
|---|---|
| `coding-plan.md` §2 | Binding decision: global prefix `wcpg_`; namespace root `ProductPublishGuard\` |
| `coding-plan.md` §10.4 | CSS BEM with `wcpg-` |
| `general-plan.md` §14 | Uses `wcpc-` in the CSS example (old inconsistency; fixed here) |
| `phpcs.xml.dist` | `PrefixAllGlobals` prefixes: `wcpg`, `WCPG`, `ProductPublishGuard` |
| Code | 14 files in `src/`, 12 in `tests/`, plus `product-publish-guard.php`, `uninstall.php`, `README.md` |
| Plan docs | `wcpg` ×78 in `coding-plan.md`, ×8 in `implementation-checklist.md`; `wcpc` ×3 in `general-plan.md` |
| Git | **No commits yet**; every file is untracked |
| Release | Never released; no production site stores `wcpg_settings` |

---

## 3. Migration strategy

### 3.1 No backward compatibility
The plugin is unreleased, so v1.0.0 ships **without** data migration, deprecated-hook shims or old-option fallbacks.

Local dev sites only: move saved settings once by hand (or just re-save the settings page):

```bash
wp option get wcpg_settings --format=json | wp option update sit_wcpg_settings --format=json
wp option delete wcpg_settings
```

> If the plugin **had** been released: an on-upgrade migration `wcpg_settings` → `sit_wcpg_settings` behind a stored DB version; old hooks kept alive with `do_action_deprecated` / `apply_filters_deprecated` for one major version; `uninstall.php` cleaning both names; both REST namespaces registered during a transition.

### 3.2 Rename technique (idempotent, case-sensitive, ordered)
Every pattern uses the same guard: **not preceded by a letter/digit, and not already preceded by `sit_`/`sit-`/`SIT_`/`SIT-`**. `_` is intentionally allowed before `wcpg`, so derived names like `option_page_capability_wcpg_settings` and `woocommerce_page_wcpg-settings` are renamed too.

Guard (Perl/PCRE): `G = (?<![A-Za-z0-9])(?<!sit_)(?<!sit-)(?<!SIT_)(?<!SIT-)`

| # | Find (after guard `G`) | Replace | Covers |
|---|---|---|---|
| 1 | `wcpgEditorData` | `sitWcpgEditorData` | the only camelCase name; must run first |
| 2 | `WCPG_` | `SIT_WCPG_` | constants |
| 3 | `WCPG-` | `SIT-WCPG-` | test / audit IDs |
| 4 | `wcpg/v1` | `sit-wcpg/v1` | REST namespace (REST uses the **kebab** form) |
| 5 | `wcpg_` | `sit_wcpg_` | functions, hooks, options, transients, error codes, globals, meta box id, derived core hooks |
| 6 | `wcpg-` | `sit-wcpg-` | handles, slugs, HTML ids, CSS classes |
| 7 | `wcpc-` | `sit-wcpg-` | `general-plan.md` only |
| 8 | bare `wcpg` / `WCPG` | **by hand** | cache group `'wcpg'` → `'sit_wcpg'`; `phpcs.xml.dist` prefix elements |

Example for one step (run from the plugin root, only on the listed paths, **never** `vendor/`, `node_modules/`, `.git/`, `composer.lock`):

```bash
perl -pi -e 's/(?<![A-Za-z0-9])(?<!sit_)(?<!sit-)(?<!SIT_)(?<!SIT-)wcpg_/sit_wcpg_/g' \
  product-publish-guard.php uninstall.php README.md $(git ls-files src tests)
```

Review `git diff` after **each** step. Running a step twice is harmless because of the guard.

### 3.3 Leftover check (the definition of "finished")
```
(?i)(?<![a-z0-9])(?<!sit_)(?<!sit-)wcp[gc]
```
Case-insensitive, so it also allows `SIT_WCPG` and `SIT-WCPG`. `sitWcpg` passes because the `t` in front of `W` is a letter. This must return **zero** matches in the repo (excluding `vendor/`, `node_modules/`, `build/`, `.git/`), except lines marked `prefix-guard: ignore` (used only for the historical decision note in `coding-plan.md`).

---

## 4. Full rename inventory

⚠ = linked value: the same string is repeated or derived elsewhere, and missing one copy breaks a feature **without any PHP error**.

### 4.1 PHP constants & global functions
| Current | New | Location |
|---|---|---|
| `WCPG_VERSION`, `WCPG_FILE`, `WCPG_PATH`, `WCPG_URL`, `WCPG_MIN_PHP`, `WCPG_MIN_WP`, `WCPG_MIN_WC` | `SIT_WCPG_VERSION` … `SIT_WCPG_MIN_WC` | defined in `product-publish-guard.php`; used in `src/Admin/Assets.php`, `src/Compat/*`, `tests/bootstrap.php` |
| `wcpg_bootstrap()`, `wcpg_boot_plugin()` ⚠ | `sit_wcpg_bootstrap()`, `sit_wcpg_boot_plugin()` | `product-publish-guard.php`; `wcpg_boot_plugin` is **also a string callback** in `add_action( 'plugins_loaded', … )` |

### 4.2 Public extension hooks
| Current | New | Location |
|---|---|---|
| `wcpg_register_rules` (action) | `sit_wcpg_register_rules` | `src/Rules/Rules_Provider.php:56`, tests, plan, README |
| `wcpg_validation_result` (filter) | `sit_wcpg_validation_result` | `src/Engine/Validator.php:85`, tests, plan, README |
| `wcpg_should_enforce` (Phase 8, not built) | `sit_wcpg_should_enforce` | plan + checklist |
| `wcpg_can_override_publish_guard` (Phase 8, not built) | `sit_wcpg_can_override_publish_guard` | plan + checklist |

### 4.3 Stored data & caches
| Current | New | Location |
|---|---|---|
| option `wcpg_settings` ⚠ | `sit_wcpg_settings` | `Settings::OPTION_NAME`, `uninstall.php`, tests |
| settings group `wcpg_settings` ⚠ | `sit_wcpg_settings` | `Settings_Page::OPTION_GROUP` |
| `option_page_capability_wcpg_settings`, `sanitize_option_wcpg_settings` ⚠ | `…_sit_wcpg_settings` | in code **already built from `OPTION_GROUP`** (`Settings_Page.php:102`); hardcoded only in docblocks (`:22`) and tests |
| transients `wcpg_blocked_{user_id}[_{post_id}]` | `sit_wcpg_blocked_…` | `uninstall.php`, plan (Phase 8 `Notices`) |
| cache group `'wcpg'` | `'sit_wcpg'` | `Checklist_Service::CACHE_GROUP` (manual step 8) |
| `wcpg_threshold_clamped_{key}` | `sit_wcpg_threshold_clamped_…` | `Settings_Sanitizer.php:215`, tests |

### 4.4 Admin / UI identifiers
| Current | New | Location |
|---|---|---|
| menu slug `wcpg-settings` ⚠ | `sit-wcpg-settings` | `Settings_Page::MENU_SLUG` (`:47`), docblock `:169` |
| `Screen::SETTINGS_HOOK = 'woocommerce_page_wcpg-settings'` ⚠ | `'woocommerce_page_' . Settings_Page::MENU_SLUG` | `src/Admin/Screen.php:44` (**hardcoded** today; derive it from the constant) |
| meta box id `wcpg_product_checklist` | `sit_wcpg_product_checklist` | `Editor_Meta_Box::ID` |
| mount id `wcpg-checklist-root` ⚠ | `sit-wcpg-checklist-root` | `Editor_Meta_Box::MOUNT_ID` + JS `index.js` (Phase 7) |
| column key `wcpg_readiness` | `sit_wcpg_readiness` | plan (Phase 9) |
| `wcpg-threshold-*` ids; `wcpg-settings-rules`, `wcpg-settings-choice` classes | `sit-wcpg-…` | `Settings_Page.php:243, 320, 459` |
| handles `wcpg-editor`, `wcpg-admin` | `sit-wcpg-editor`, `sit-wcpg-admin` | `Assets::EDITOR_HANDLE`, `ADMIN_HANDLE` |
| JS global `wcpgEditorData` ⚠ | `sitWcpgEditorData` | `Assets::PAYLOAD_GLOBAL` + the JS reading `window.…` (Phase 7) |
| CSS `.wcpg-checklist*`, `.wcpg-readiness*` ⚠ | `.sit-wcpg-…` | PHP fallback markup, SCSS, React components |

### 4.5 REST
| Current | New | Location |
|---|---|---|
| namespace `wcpg/v1` ⚠ | `sit-wcpg/v1` | `Validate_Controller::REST_NAMESPACE`; **hardcoded again** at `Assets.php:196` → build it from the constant instead |
| `wcpg_forbidden`, `wcpg_validation_failed`, `wcpg_not_found` | `sit_wcpg_…` | `Validate_Controller.php:272, 303, 601`, tests, Phase 7 JS |

### 4.6 Procedural / test globals
| Current | New | Location |
|---|---|---|
| `$wcpg_user_ids`, `$wcpg_user_id` | `$sit_wcpg_…` | `uninstall.php` |
| `$wcpg_root`, `$wcpg_wp_tests`, `$wcpg_test_*`, `wcpg_value(s)`, `wcpg_snake_case`, `wcpg_limited_editor` … | `sit_wcpg_…` | `tests/bootstrap.php`, `tests/stubs/*`, tests |

### 4.7 Docs & labels
| Current | New | Location |
|---|---|---|
| `WCPG-REST-1`, `WCPG-XSS-1`, `WCPG-TEST-1`, `WCPG-SEC-*` | `SIT-WCPG-…` | plan + test docblocks |
| README hook / route / option docs | new names | `README.md` (5 mentions) |
| `.wcpc-*` examples | `.sit-wcpg-*` | `general-plan.md` §14 |

---

## 5. Execution order

### Step 0 — Preconditions
- [ ] **First git commit** of the current tree, so the rename is a reviewable, revertible diff: commit on `main`, then `git checkout -b chore/prefix-sit-wcpg`.
- [ ] Record the baseline: `composer lint` and `composer test` results (test count).

### Step 1 — Plan documents first
- [ ] `coding-plan.md` §2 "Global prefix" row → `sit_wcpg_` / `sit-wcpg-` / `sitWcpg…`, with a dated note in the §17.24 style: *"Changed from `wcpg` to `sit_wcpg` by user decision on 2026-09-25 to add a vendor prefix. <!-- prefix-guard: ignore -->"*
- [ ] Add §10.6 **"Naming contract"** to `coding-plan.md` = the table in §1 of this document.
- [ ] Rename all 78 mentions in `coding-plan.md` (hooks §5.2/§14, option §2, transients §6.3/§9, REST §8, CSS §10.4, test IDs §12), using §3.2.
- [ ] `implementation-checklist.md`: rename the 8 mentions (mostly unbuilt Phases 8–9); add a ticked entry for this migration.
- [ ] `general-plan.md` §14: `.wcpc-*` → `.sit-wcpg-*`.

### Step 2 — Guards (they become the to-do list)
- [ ] `phpcs.xml.dist` → `PrefixAllGlobals` prefixes: **`sit_wcpg`**, **`SIT_WCPG`**, `ProductPublishGuard` (remove `wcpg`, `WCPG`). From now on `composer lint` flags every unrenamed PHP global, function, constant and hook.
- [ ] `bin/check-prefix.php`: scans all text files outside `vendor/`, `node_modules/`, `build/`, `.git/` with the §3.3 regex, skips lines containing `prefix-guard: ignore`, exits non-zero with `file:line` for each hit. PHP rather than `grep`, so it behaves the same on Windows. Register it as composer script `lint:prefix` and add it to `lint`: `"lint": ["phpcs", "@lint:prefix"]`.
- [ ] `.stylelintrc.json` extending `@wordpress/stylelint-config`, with
  `"selector-class-pattern": ["^sit-wcpg-[a-z0-9]+(-[a-z0-9]+)*(__[a-z0-9]+(-[a-z0-9]+)*)?(--[a-z0-9]+(-[a-z0-9]+)*)?$", { "resolveNestedSelectors": true }]`
  so every future class must carry the prefix **and** be BEM. (If SCSS ever needs to target a core WordPress class, disable the rule on that line with a comment explaining why.)
- [ ] `.distignore`: add `bin` and `.stylelintrc.json`.

### Step 3 — Production code (order chosen to limit breakage)
- [ ] `product-publish-guard.php`: constants, both functions, the `'wcpg_boot_plugin'` string callback, `@package` unaffected.
- [ ] `src/Settings/*`: `OPTION_NAME`, `OPTION_GROUP`, `MENU_SLUG`, field ids / classes, clamp codes, docblocks.
- [ ] `src/Admin/Screen.php`: `SETTINGS_HOOK = 'woocommerce_page_' . Settings_Page::MENU_SLUG` (constant expression; valid in PHP 8.0).
- [ ] `src/Admin/Assets.php`: handles, `PAYLOAD_GLOBAL = 'sitWcpgEditorData'`, `restPath` built as `'/' . Validate_Controller::REST_NAMESPACE . '/products/' . $product_id . '/validate'`, `SIT_WCPG_*` constants.
- [ ] `src/Admin/Editor_Meta_Box.php`: `ID`, `MOUNT_ID`, fallback-markup classes.
- [ ] `src/Rest/Validate_Controller.php`: `REST_NAMESPACE = 'sit-wcpg/v1'`, 3 error codes, docblocks.
- [ ] `src/Rules/Rules_Provider.php`, `src/Engine/Validator.php`: hook names + their docblocks.
- [ ] `src/Support/Checklist_Service.php`: `CACHE_GROUP = 'sit_wcpg'`.
- [ ] `src/Compat/*`: constant references.
- [ ] `uninstall.php`: option name, transient prefix, globals.

### Step 4 — Tests
- [ ] `tests/bootstrap.php`, `tests/stubs/*`: globals and helpers.
- [ ] `tests/Integration/*`, `tests/Security/*`: option name, route `/sit-wcpg/v1/…`, error codes, hooks, test IDs.
- [ ] `tests/Unit/*`: hook names (`Rules_Provider_Test`, `Validator_Test`), `Settings_Sanitizer_Test` codes.
- [ ] **New** `tests/Unit/Naming_Contract_Test.php` asserting:
  - `Screen::SETTINGS_HOOK === 'woocommerce_page_' . Settings_Page::MENU_SLUG`
  - `Settings_Page::OPTION_GROUP === Settings::OPTION_NAME`
  - snake-form constants (`OPTION_NAME`, `Editor_Meta_Box::ID`, `CACHE_GROUP`) start with `sit_wcpg`
  - kebab-form constants (`MENU_SLUG`, `MOUNT_ID`, handles, `REST_NAMESPACE`) start with `sit-wcpg`
  - `PAYLOAD_GLOBAL` starts with `sitWcpg`
  This keeps future constants on the contract.

### Step 5 — Public docs
- [ ] `README.md`: hooks, REST route, option name.
- [ ] `readme.txt`: nothing to change today; any future Hooks/FAQ section uses the new names.

### Step 6 — Verification (Definition of Done)
- [ ] `composer lint` (PHPCS + `lint:prefix`) shows zero errors, zero warnings.
- [ ] `composer test` is green; test count = baseline + the new naming test.
- [ ] `npm run lint:css` passes once SCSS exists (Phase 7/9).
- [ ] No match of `sit_sit` / `sit-sit` / `SIT_SIT` anywhere (catches a double-applied replace).
- [ ] Manual, in `wp-env` (or Laragon once MySQL runs):
  - [ ] activates with `WP_DEBUG=true`, zero notices
  - [ ] **WooCommerce → Product Checklist** URL is `admin.php?page=sit-wcpg-settings`, admin CSS applied (proves screen hook = slug), save stores `sit_wcpg_settings`
  - [ ] product editor meta box renders, styled
  - [ ] `POST /wp-json/sit-wcpg/v1/products/<id>/validate` works; `/wcpg/v1/…` → 404
  - [ ] uninstall leaves no `sit_wcpg_settings` row
- [ ] Commit: `chore: rename global prefix wcpg → sit_wcpg / sit-wcpg`.

### Step 7 — Lock it in for future sessions
- [ ] Create `CLAUDE.md` at the plugin root with: the §1 table; *"never add an unprefixed global, and never the bare `wcpg` prefix"*; *"define each global identifier once as a class constant"*; *"run `composer lint` before finishing any task"*. Future Claude sessions load it automatically. That's the mechanism that makes future code follow the prefix.
- [ ] Optional: `.git/hooks/pre-commit` running `composer lint:prefix` (or a CI job later).

---

## 6. Phases still to build: names they must use

| Phase | Required names |
|---|---|
| 7 React UI | read `window.sitWcpgEditorData`; mount on `#sit-wcpg-checklist-root`; classes `sit-wcpg-checklist__*`; handle REST errors `sit_wcpg_*`; take the REST path from the payload's `restPath`, **never** hardcode it |
| 8 Enforcement | filters `sit_wcpg_should_enforce`, `sit_wcpg_can_override_publish_guard`; transients `sit_wcpg_blocked_*`; any query arg / notice key `sit_wcpg_*` |
| 9 Products list | column key `sit_wcpg_readiness`; classes `.sit-wcpg-readiness*` |
| 12 Release | `.pot` unaffected (text domain unchanged); verify `bin/` and `.stylelintrc.json` are excluded from the zip |

---

## 7. Risks

| Risk | Effect | Mitigation |
|---|---|---|
| Replace run twice → `sit_sit_wcpg_` | broken names everywhere | lookbehind guard (§3.2) + `sit_sit` check (Step 6) |
| Leftover check matches new names | false "not done" / check ignored | §3.3 regex ignores prefixed matches |
| Derived name `option_page_capability_wcpg_settings` missed | shop managers can't save settings | `_` is allowed before `wcpg` in §3.2; code already derives it from `OPTION_GROUP` |
| Menu slug and `Screen::SETTINGS_HOOK` drift | settings page silently unstyled | derived constant + `Naming_Contract_Test` |
| REST path hardcoded | editor revalidation 404s silently | only `REST_NAMESPACE` is the source; JS uses `restPath` |
| `'wcpg_boot_plugin'` string callback missed | plugin never boots, no error | PHPCS + manual activation check |
| Mixing `sit_wcpg` / `sit-wcpg` in the wrong context | inconsistent names; stylelint/guard noise | §1 table in `CLAUDE.md` + naming test |
| Global replace touches `vendor/` / `composer.lock` | broken dev dependencies | replace only on `git ls-files` paths listed in §3.2 |
| Dev DB still has `wcpg_settings` | settings look reset | one-off WP-CLI move (§3.1) |
