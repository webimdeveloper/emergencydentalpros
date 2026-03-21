# Emergency Dental Pros Plugin

## Branch flow

- `dev`: daily development.
- `main`: production deploy branch.
- Deployment runs automatically on every push to `main`.

## Local development with Local App

1. Create a site in Local App.
2. Find its plugin directory:
   - `.../app/public/wp-content/plugins/`
3. Symlink this repo into that plugins directory.

Example:

```bash
ln -s "/Users/webim/Documents/Projects/emergencydentalpros" "/path/to/local-site/app/public/wp-content/plugins/emergencydentalpros"
```

4. In WordPress admin, activate `Emergency Dental Pros`.

## Frontend assets

Install dependencies:

```bash
npm install
```

Build once:

```bash
npm run build
```

Watch and rebuild on file changes:

```bash
npm run dev
```

## Deploy setup (GitHub Secrets)

Add repository secrets:

- `SSH_HOST`
- `SSH_PORT`
- `SSH_USER`
- `SSH_PRIVATE_KEY`
- `KNOWN_HOSTS` (recommended)

Remote deploy path:

`/var/www/widev.pro/public_html/wp-content/plugins/emergencydentalpros/`

## Deploy command flow (no PR)

```bash
git checkout dev
# work + commits
git checkout main
git merge dev
git push origin main
```
