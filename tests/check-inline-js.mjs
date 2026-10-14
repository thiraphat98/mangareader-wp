import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

for (const filename of ['archive-manga.php', 'page-manga-archive.php']) {
  const source = readFileSync(new URL(`../${filename}`, import.meta.url), 'utf8');
  const script = source.match(/<script>([\s\S]*?)<\/script>/i);
  assert.ok(script, `${filename} has an inline script`);
  const javascript = script[1].replace(/<\?php[\s\S]*?\?>/g, 'dummy');
  execFileSync(process.execPath, ['--check'], { input: javascript, stdio: ['pipe', 'pipe', 'pipe'] });
  process.stdout.write(`JavaScript syntax OK: ${filename}\n`);
}
