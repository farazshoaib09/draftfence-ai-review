# DraftFence AI Review

DraftFence AI Review is a WordPress plugin for governing AI-connected WordPress users with scoped AI identities, draft creation, pending proposal review, SEO update controls, audit logs, and rollback.

## Development checks

Run PHP syntax checks locally:

```bash
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

Run WordPress Plugin Check locally from a WordPress install:

```bash
wp plugin check draftfence-ai-review
```

## WordPress.org deployment

This repository includes GitHub Actions for:

- Plugin Check CI
- PHP syntax linting
- WordPress.org SVN deployment on version tags
- WordPress.org readme/assets deployment from `main`

Required GitHub repository secrets:

- `SVN_USERNAME`
- `SVN_PASSWORD`

To deploy a release after WordPress.org allocates the slug:

```bash
git tag 0.4.0
git push origin 0.4.0
```

