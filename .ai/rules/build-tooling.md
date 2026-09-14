---
paths:
  - package.json
  - package-lock.json
  - .github/dependabot.yml
---

# Build Tooling

## vite-plus: bump the alias, the dep and the override together
Vite+ does not run on upstream Vite. package.json aliases `vite` to npm:@voidzero-dev/vite-plus-core@X, and vite-plus pins that alias to its OWN exact version, so three places must always hold the same version: dependencies.vite (the alias), dependencies.vite-plus, and overrides.vite. The override exists so transitive dependents (laravel-vite-plugin, @tailwindcss/vite) resolve that same copy instead of pulling a second, real Vite.

Any mismatch aborts `vp build` before it starts: "Failed to resolve vite command: Expected @voidzero-dev/vite-plus-core@<a>, but found <b>". Declaring upstream `vite: ^8.x` gives the same error with real vite as the "found" half.

Dependabot cannot see that the aliased `vite` key and the override are the same package under another name, so both names are in the npm `ignore` list in .github/dependabot.yml — an ignore, not a group exclude-pattern, which would only stop bundling and still raise a solo PR. Bump by hand: set all three to the new version, check `npm view vite-plus@<v> dependencies` for the vitest it bundles (pinned in overrides so one Vitest is shared with the bundled runner), then `npm install && npm run build`. Verify with a clean `rm -rf node_modules && npm ci && npm run build`, which is what CI runs.
