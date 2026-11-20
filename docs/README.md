# Boilerplate documentation

Boilerplate is a ready-made website with a public site, user accounts and an
admin panel. It comes with a blog, content pages, a contact form, and online
payments and subscriptions.

In these guides, **you** are the person who installs and runs the site, and
**visitors** are the people who use it.

## Guides

| Guide | Read it when |
| --- | --- |
| [Installing on shared hosting (cPanel)](installation-shared-hosting.md) | You have ordinary PHP hosting and want to use the browser installer. It also covers updates. |

## What the site does

- **Accounts.** Visitors can register, sign in, reset a forgotten password and
  verify their email address. They can also turn on two-factor
  authentication or sign in with a passkey.
- **Content pages.** You write and publish pages from the admin panel. The
  legal pages (privacy, terms) are created for you when you install.
- **Blog.** Posts with categories and tags, and publishing scheduled for a
  later date.
- **Contact form.** Messages arrive in the admin panel, and you can have them
  emailed to you too.
- **Payments.** One-off payments, payment links and subscription plans,
  through Stripe or PayPal. Each payment gets a receipt, and you can issue
  refunds from the admin panel.
- **Staff roles.** Besides administrators, you can give people a limited role
  in the admin panel: *editor* (content), *bookkeeper* (payments, read-only) or
  *manager* (both, plus refunds and users).
- **Media library.** Uploaded images are resized and stripped of their photo
  metadata before they appear on the site.

## Where things are

| Address | Who uses it |
| --- | --- |
| `/admin` | You and your staff: content, users, payments, settings and updates. |
| `/dashboard` | Signed-in visitors: their account, security settings and subscriptions. |
| `/login`, `/register` | Everyone. You can switch registration off under Admin → Settings → Registration. |

## What's new

The [changelog](../CHANGELOG.md) lists the changes in every release, newest
first. To update, see
[Installing on shared hosting](installation-shared-hosting.md#updating).
