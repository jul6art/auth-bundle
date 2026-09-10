# Security Policy

## Supported versions

`jul6art/auth-bundle` is installed by other applications through Composer, so a fix here
reaches them the moment they update. Only the current major line gets one.

| Version | Supported |
| --- | --- |
| `2.x` | ✅ |
| `1.x` | ❌ |
| any older tag or fork | ❌ |

Support means security fixes on the latest release of that line — upgrade to it before
reporting, in case the problem is already gone.

## What is in scope

This bundle owns the identity of every application in the ecosystem, so its defects are
authentication defects:

* **Anything wrong in password handling** — a hash written without the configured hasher, an
  upgrade path that stores a weaker hash, a comparison that is not constant-time, a
  plaintext password kept on the object longer than the request.
* **A password, hash or token that escapes** — serialized into an API response, rendered in
  a form, written to a log, or exposed through the profiler or an exception message.
* **Account takeover through lookup** — case-insensitive or trimmed matching that makes two
  addresses collide, or a uniqueness rule the database does not enforce.
* **Account enumeration** — a response, a timing difference or a validation message that
  tells an unauthenticated caller whether an address is registered.
* **A deactivated or deleted account that can still authenticate**, including through a
  remembered session or a soft-deleted row still loaded by the user provider.

Out of scope: vulnerabilities in Symfony, Doctrine, API Platform or any other third-party
package — report those to the project that owns the code, and they will reach you through
your own `composer update`. Also out of scope: an application that misconfigures this bundle
in a way the README warns against, though a warning that turns out to be easy to miss is
worth an issue of its own.

## Reporting a vulnerability

**Do not open a public issue for a security problem.**

Use [GitHub's private vulnerability reporting](https://github.com/jul6art/auth-bundle/security/advisories/new)
(the **Security** tab → *Report a vulnerability*). It opens a draft advisory only
you and the maintainers can read, and it is the channel this project prefers —
no email address needs to be published for it to work.

Please include:

* the version of `jul6art/auth-bundle` and of Symfony you are running,
* the relevant part of your bundle configuration,
* the shortest reproduction you have — ideally a failing test against this
  repository, since that is what a fix will be built on,
* what an attacker gains: which check is bypassed, which data is read or
  written, and whether authentication is required.

## What to expect

* An acknowledgement within **7 days**.
* An assessment — accepted, out of scope, or needing more detail — within
  **14 days**.
* For an accepted report: a fix released on the supported line, a
  [security advisory](https://github.com/jul6art/auth-bundle/security/advisories)
  describing the impact and the version to upgrade to, and credit in it unless
  you ask otherwise.

Please give the maintainers a reasonable window to ship a release before disclosing
publicly. This project runs no bug-bounty programme and offers no payment.