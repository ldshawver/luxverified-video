const { exec } = require('child_process');
const path = require('path');

const runScript = (env = {}, cb) => {
  const opts = { cwd: path.resolve(__dirname, '..'), env: { ...process.env, ...env } };
  exec('node scripts/run-codex-agent.js', opts, (err, stdout, stderr) => cb(err, stdout, stderr));
};

jest.setTimeout(10000);

test('DRY_RUN prints TODOs and does not create GH issue', (done) => {
  runScript(
    { DRY_RUN: '1', FAKE_TODOS: './docs/CODEX.md:37:- scans the repo for `TODO` comments' },
    (err, out, errOut) => {
      expect(out).toMatch(/Starting Codex agent/);
      expect(out).toMatch(/Found TODOs/);
      expect(out).toMatch(/DRY_RUN enabled — not creating GitHub issue/); // message in script
      expect(errOut).not.toMatch(/Codex agent failed/);
      done();
    }
  );
});

test('MOCK_OPENAI produces AI summary when OPENAI_API_KEY set', (done) => {
  runScript({ DRY_RUN: '1', MOCK_OPENAI: '1', OPENAI_API_KEY: 'mock', FAKE_TODOS: 'file:1: TODO: test' }, (err, out, errOut) => {
    expect(out).toMatch(/MOCK_OPENAI enabled/);
    expect(out).toMatch(/AI summary generated/);
    expect(out).toMatch(/Mock summary/);
    expect(errOut).not.toMatch(/Codex agent failed/);
    done();
  });
});
