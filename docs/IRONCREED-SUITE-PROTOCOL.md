# IRONCREED Suite Protocol v1

## Purpose

This protocol lets independently installable IRONCREED plugins form a coherent
administration menu when several active plugins belong to the same theme. It
creates navigation interoperability without a companion plugin or framework.

## Stable identifiers

Every participant declares immutable identifiers:

| Field | Meaning | Example |
| --- | --- | --- |
| `vendor` | Publisher namespace | `ironcreed` |
| `product` | Unique plugin identity | `request-log` |
| `theme` | Administrative family | `security` |
| `protocol` | Contract major version | `1` |
| `page_slug` | Stable plugin page | `ironcreed-request-log` |

The full identity is `vendor/product`. A display name, directory, version, or
translated label never replaces it.

## Registry hook

A plugin attaches a callback to `ironcreed_suite_registry_v1` as soon as its
bootstrap file loads. The callback receives an array and returns the array with
one descriptor appended. Applying the filter after `plugins_loaded` therefore
discovers all participants regardless of load order.

A descriptor contains inert metadata and callables owned by its plugin:

```php
array(
	'vendor'     => 'ironcreed',
	'product'    => 'request-log',
	'theme'      => 'security',
	'protocol'   => 1,
	'page_slug'  => 'ironcreed-request-log',
	'menu_label' => __( 'Request Log', 'ironcreed-request-log' ),
	'capability' => 'view_ironcreed_request_log',
	'position'   => 20,
	'render'     => $render_callback,
);
```

Consumers validate descriptor shape and scalar types. A malformed or
incompatible descriptor is ignored and may produce a contextual administrator
notice. Enumeration never executes a render callback.

## Menu formation

Participants are grouped by `vendor/theme/protocol`.

- One active plugin in a group registers below the most appropriate existing
  WordPress menu. A utility uses `Tools` unless its contract says otherwise.
- Two or more active plugins in a group create one top-level menu. For
  `ironcreed/security/1`, the label is `IRONCREED — Security`, slug
  `ironcreed-security`, and standard Dashicon `shield`.
- Different themes form separate groups. Two plugins with `security` and
  `publishing` remain in independent standard locations until a theme has two
  participants.
- Inactive, incompatible, malformed, or network-inapplicable plugins do not
  participate.

Every participant calculates the same sorted registry. The plugin with the
lexicographically smallest full identity becomes coordinator and alone
registers the top-level page. Participants register their own submenu at a
later `admin_menu` priority. If the coordinator is deactivated, the next
identity assumes coordination automatically.

The coordinator gains no authority over another plugin's settings, rendering,
data, activation, updates, or lifecycle.

## Compatibility

Protocol major versions do not merge. An incompatible protocol uses a new hook
name. Additive fields within v1 must be ignored by older consumers.

Each plugin carries a small implementation under its namespace. A shared global
class, mutable option, vendor autoload file, or required companion plugin is
prohibited. WordPress Core is the protocol's only runtime dependency.

## Required tests

Each participant tests:

1. a solitary plugin in its standard location;
2. two same-theme plugins forming one top-level group;
3. two different-theme plugins remaining independent;
4. deterministic coordinator replacement after deactivation;
5. rejection of malformed and incompatible descriptors;
6. capability enforcement for every page;
7. single-site and network-administration behavior where supported.
