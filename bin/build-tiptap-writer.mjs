#!/usr/bin/env node
/**
 * Rebuild assets/js/vendor/tiptap-writer.mjs
 *
 * Prerequisites: TipTap + esbuild in tmp-tiptap-build/node_modules (or root).
 *   cd tmp-tiptap-build && npm i @tiptap/core@2 … esbuild@0.25
 *   node ../bin/build-tiptap-writer.mjs
 */
import { build } from 'esbuild';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { createRequire } from 'node:module';
import { existsSync } from 'node:fs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const entry = join(root, 'bin', 'tiptap-writer-entry.mjs');
const outfile = join(root, 'assets', 'js', 'vendor', 'tiptap-writer.mjs');
const nodePaths = [
  join(root, 'tmp-tiptap-build', 'node_modules'),
  join(root, 'node_modules'),
].filter((p) => existsSync(p));

if (nodePaths.length === 0) {
  console.error('No node_modules found. Install TipTap under tmp-tiptap-build/ first.');
  process.exit(1);
}

// Load esbuild from tmp build if not resolvable from this file
let esbuildBuild = build;
try {
  createRequire(import.meta.url).resolve('esbuild');
} catch {
  const req = createRequire(pathToFileURL(join(nodePaths[0], 'esbuild', 'package.json')).href);
  esbuildBuild = req('esbuild').build;
}

await esbuildBuild({
  entryPoints: [entry],
  bundle: true,
  format: 'esm',
  outfile,
  platform: 'browser',
  minify: true,
  legalComments: 'none',
  nodePaths,
});

console.log('Wrote', outfile);
