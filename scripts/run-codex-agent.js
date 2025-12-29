#!/usr/bin/env node
// scripts/run-codex-agent.js
// Minimal Codex/AI agent to run inside Codespaces to control tasks.
// NOTE: This is an example scaffold. It supports optional OPENAI_API_KEY and a dry-run mode via DRY_RUN=1.

const { execSync } = require('child_process');
const fs = require('fs');

const DRY_RUN = !!process.env.DRY_RUN;
function requireEnv(name) {
  const v = process.env[name];
  if (!v) {
    console.error(`Missing env var: ${name}. Set it as a Codespaces secret.`);
    process.exit(1);
  }
  return v;
}

// CODEX_GH_PAT is only required when not in DRY_RUN (we avoid requiring it in dry runs)
const CODEX_GH_PAT = DRY_RUN ? process.env.CODEX_GH_PAT || null : requireEnv('CODEX_GH_PAT');
const OPENAI_API_KEY = process.env.OPENAI_API_KEY || null;

console.log('Starting Codex agent (demo)...', DRY_RUN ? '(dry run)' : '');

(async () => {
  try {
    const todos = process.env.FAKE_TODOS
      ? process.env.FAKE_TODOS
      : execSync('rg "TODO" -n . | head -n 20', { encoding: 'utf8' });
    console.log('Found TODOs:\n', todos);

    let aiSummary = '';
    if (OPENAI_API_KEY) {
      if (process.env.MOCK_OPENAI) {
        console.log('MOCK_OPENAI enabled — using fake response');
        aiSummary = '- Mock summary: consolidate TODOs and assign to owners.';
        console.log('AI summary generated.');
      } else {
        try {
          const { OpenAI } = require('openai');
          const client = new OpenAI({ apiKey: OPENAI_API_KEY });
          const prompt = `Summarize the following TODOs into a short actionable summary (bullet list) and suggest next steps:\n\n${todos}`;
          const resp = await client.responses.create({ model: 'gpt-4o-mini', input: prompt });
          // Best-effort extraction of text output
          const out =
            resp.output && resp.output[0] && resp.output[0].content
              ? resp.output[0].content.map((c) => c.text || '').join('\n')
              : '';
          aiSummary = out || '';
          console.log('AI summary generated.');
        } catch (err) {
          console.error('OpenAI call failed:', err.message);
        }
      }
    } else {
      console.log('OPENAI_API_KEY not set — skipping AI summarization.');
    }

    const title = 'Automated: TODOs found in repository';
    const body = `Automated scan found the following TODOs:\n\n${todos}${aiSummary ? '\n\nAI summary:\n' + aiSummary : ''}`;

    const tmp = '/tmp/codex_issue_body.txt';
    fs.writeFileSync(tmp, body);

    if (DRY_RUN) {
      console.log('DRY_RUN enabled — not creating GitHub issue. Issue body:\n');
      console.log(body);
    } else {
      console.log('Creating GitHub issue (uses gh CLI with token from CODEX_GH_PAT)...');
      execSync('gh auth login --with-token', { input: `${CODEX_GH_PAT}\n`, stdio: ['pipe', 'inherit', 'inherit'] });
      execSync(
        `gh issue create --repo ldshawver/luxverified-video --title "${title}" --body-file ${tmp}`,
        { stdio: 'inherit' }
      );
      execSync('gh auth logout -h github.com -s', { stdio: 'inherit' });
    }
  } catch (e) {
    console.error('Codex agent failed:', e.message);
  }

  console.log('Codex agent finished.');
})();
