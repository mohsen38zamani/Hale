// One-off helper: (re)inline the built app CSS as the FIRST <style> block of the preview file.
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';

const asset = readdirSync('public/build/assets').find((f) => f.startsWith('app-') && f.endsWith('.css'));
const css = readFileSync(`public/build/assets/${asset}`, 'utf8');
const html = readFileSync('.preview-landing.html', 'utf8');
const block = `<style>\n${css}\n</style>`;
const next = html.includes('<style>')
  ? html.replace(/<style>[\s\S]*?<\/style>/, block)
  : html.replace('<link rel="stylesheet" href=".preview-app.css">', block);
writeFileSync('.preview-landing.html', next);
console.log(`Inlined ${asset} (${css.length} bytes) into .preview-landing.html`);
