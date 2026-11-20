# Users and roles

Every account has one role, which decides what it can do. You manage accounts
under **Users** in the admin panel.

## The roles

| Role | What it can do |
| --- | --- |
| **User** | Signs in and manages their own account and subscription. No access to the admin panel. Everyone who registers on the site gets this role. |
| **Editor** | Manages the site's content: pages, their own blog posts, uploaded files and contact messages. No access to payments, users or settings. |
| **Bookkeeper** | Reads payments, refunds, subscriptions, disputes and tax rates. Cannot refund, capture or change anything. |
| **Manager** | Everything an editor and a bookkeeper can do, plus refunds, held payments, payment links, subscriptions, everyone's blog posts and the user list (read only). Cannot change settings, payment credentials, plans or roles. |
| **Administrator** | Everything, including every user and every setting. |

Give each person the lowest role that covers their work. Only administrators
can change roles.

## Adding people

- **Visitors register themselves** at `/register` once you turn registration
  on under **Admin → Settings → Registration**. It is off when you install.
  New accounts get the *User* role.
- **You can create an account** under **Users → New user**, with any role.
  This works whether registration is on or off.

## When someone leaves

Open their account under **Users** and choose **Deactivate**. They are
signed out everywhere and can no longer sign in, but their name stays on the
posts, payments and refunds they worked on. **Reactivate** lets them back in
with their existing password.

Use deactivation rather than deleting:

- An account that recorded payments or issued refunds can't be deleted, because
  it is part of the financial records.
- An account with a running subscription must have the subscription cancelled
  before it can be deactivated.
- The site always keeps at least one active administrator. The last one can't
  be deactivated, deleted or given another role.

## Account security

Every account can be protected with **two-factor authentication** (a code from
an authenticator app) and **passkeys** (a fingerprint, face or security key).
People turn these on from their own security settings. Two-factor
authentication is strongly recommended for administrators.

## Keeping track

**System → Audit log** records who changed what: content, users, settings and
payments, with the old and new values. Administrators can read it.

Under **Admin → Settings → Email**:

- **Email administrators a business summary** sends every administrator a
  weekly or monthly summary of revenue, new customers and anything needing
  attention. It is on when you install.
- **Failure alert address** is where a warning goes when a background task
  fails, such as an email that could not be sent.
