# Codex agent (Codespaces)

This document explains how to securely configure and run the example Codex agent provided in `scripts/run-codex-agent.js` inside a GitHub Codespace for the `luxverified-video` repo.

> ⚠️ Never paste tokens into chat or commit them into the repository.

## Required secrets (set these as **Codespaces repository secrets**) ✅
- `CODEX_GH_PAT` — a Personal Access Token used by the demo agent to call `gh` (use Fine‑grained token, least privilege). Recommended scopes: `repo` (read) and `issues` (create) if you want issue creation.
- `OPENAI_API_KEY` — your OpenAI/Codex API key (if you integrate with OpenAI in the future).

## Add secrets (recommended)
- Use the GitHub UI: Repo → Settings → Codespaces → Secrets → New repository secret
- Or use `gh` locally:

```bash
read -s -p "Enter secret value: " VAL; echo; printf "%s" "$VAL" | gh secret set CODEX_GH_PAT --repo ldshawver/luxverified-video --app codespaces --body -
```

If you prefer, run the included helper script locally:

```bash
# run locally; it will prompt and set the Codespaces secret via gh
chmod +x scripts/set-codespaces-secret.sh
./scripts/set-codespaces-secret.sh GITHUB_PAT
```

## Running the example agent in Codespaces
1. Open a Codespace for this repo (the `.devcontainer/devcontainer.json` maps `CODEX_GH_PAT` and `OPENAI_API_KEY` from the Codespaces environment into the container).
2. Ensure `gh` and `node` are available (the devcontainer image includes node via features).
3. Run the agent:

```bash
node scripts/run-codex-agent.js
```

The example agent currently:
  - scans the repo for `TODO` comments (prefers `rg` when available; falls back to `grep` and excludes `node_modules` and `.git`)
  - creates a GitHub issue summarizing the findings (using `gh` and the `CODEX_GH_PAT` token)

This is a minimal scaffold — replace or extend the script with real Codex/OpenAI calls and richer automation flows.

Notes on errors and debugging:
- The agent now prefers `rg` (ripgrep) for faster searching. If `rg` is not installed it will fall back to `grep` but exclude `node_modules` and `.git` to avoid large outputs.
- If your `OPENAI_API_KEY` is invalid you will see a clear message: `OpenAI call failed: 401 Unauthorized — invalid or expired OPENAI_API_KEY.` Use `MOCK_OPENAI=1` for local testing to avoid real API calls.


## NPM scripts
- `npm run codex:run` — run the Codex agent and create issues (requires `CODEX_GH_PAT`).
- `npm run codex:dry` — run the agent in **dry run** mode (sets `DRY_RUN=1`), prints the issue body and skips creating the GitHub issue.

## Running tests and CI
- Run tests locally:

```bash
npm test
```

- CI: a GitHub Actions workflow `.github/workflows/codex-agent.yml` runs tests on push and pull requests.

## Testing notes
- The test suite uses `MOCK_OPENAI=1` to avoid calling the real OpenAI API; you can run the mock test with:

```bash
MOCK_OPENAI=1 DRY_RUN=1 OPENAI_API_KEY=mock npm run codex:dry
```



## Security & best practices 🔐
- Use fine‑grained PATs with expiration and least privilege.
- Rotate and revoke tokens regularly.
- Do not print secrets in logs.
- Consider enabling "Allow for prebuilds" in the Codespaces UI if your agent must run during prebuilds (be cautious with secrets in prebuilds).

---

If you'd like, I can:
- Add an example that actually calls OpenAI (safely) and demonstrates a simple prompt/response loop, or
- Add a GitHub Actions workflow that validates the agent script and runs tests.

Tell me which you'd prefer next.