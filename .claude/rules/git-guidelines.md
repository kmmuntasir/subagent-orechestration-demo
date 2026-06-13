---
trigger: model_decision
description: Ruleset that MUST be followed when executing ANY git command
---

# Git Guidelines

## Sacred Rule:
- NEVER run a `git` command without the user's explicit approval.

## Merge Policy:
- **Rebase and Merge ONLY** — Repo uses the "Rebase and Merge" policy
- **No merge commits** — Never `git merge`
- **No squash merging** — Never the `--squash` flag
- **No local branch merging** — All merging via PR rebase on GitHub

## Project Slug:
- PROJECTSLUG: short project abbreviation (e.g., JIRA tickets)
- This project: **SOD**
- Source: `./project-metadata.md`

## Branch Naming:
- Format: `type/PROJECTSLUG-TICKET_NUMBER-hyphenated-short-description`
- Example: `feature/SOD-123-add-product-validation`, `bugfix/SOD-234-fix-delete-route`
- Exception: Release branches: `release/1.2.3` — version only, no ticket or description
- Imperative, hyphenated description
- Never assume a ticket number. If missing, omit it
- Trello projects: use Card Number instead of Ticket number

## Commit Messages:
- ALWAYS single-line commit message
- Format: `PROJECTSLUG-TICKET_NUMBER: message`
- Example: `SOD-123: Add product validation on create`
- Extract the ticket number from the branch name
- If the ticket is unidentifiable, omit the prefix — message only

## .gitignore
Ensure these entries exist. Never commit sensitive build artifacts or generated code:
- `vendor/` (Composer dependencies)
- `node_modules/` (the extractor script is Node-based)
- `.env` (actual secrets, not `.env.example`)
- `output/` (generated spec bundles from the `decompose-legacy` skill)
- `storage/logs/`, `storage/framework/` (Laravel runtime cache/sessions/views)
- `.phpunit.result.cache`
- `*.log`
- `.DS_Store`
- **Commit** `composer.lock` for applications (omit only for publishable libraries)
