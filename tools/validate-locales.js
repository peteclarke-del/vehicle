#!/usr/bin/env node
const fs = require('fs');
const path = require('path');

const localesDir = path.join(__dirname, '..', 'frontend', 'public', 'locales');

function indexToLineCol(text, idx) {
  const lines = text.slice(0, idx).split(/\r?\n/);
  const line = lines.length;
  const col = lines[lines.length - 1].length + 1;
  return { line, col };
}

function validateFile(filePath) {
  try {
    const txt = fs.readFileSync(filePath, 'utf8');
    JSON.parse(txt);
    console.log(`OK: ${path.relative(process.cwd(), filePath)}`);
  } catch (err) {
    const message = err && err.message ? err.message : String(err);
    // try to extract position
    const m = message.match(/at position (\d+)/i) || message.match(/char (\d+)/i) || message.match(/column (\d+)/i);
    console.error(`ERROR: ${path.relative(process.cwd(), filePath)} -> ${message}`);
    if (m && m[1]) {
      const pos = parseInt(m[1], 10);
      const txt = fs.readFileSync(filePath, 'utf8');
      const { line, col } = indexToLineCol(txt, pos);
      console.error(`  at approx line ${line}, column ${col}`);
    }
  }
}

if (!fs.existsSync(localesDir)) {
  console.error('Locales directory not found:', localesDir);
  process.exit(1);
}

const entries = fs.readdirSync(localesDir, { withFileTypes: true });
for (const e of entries) {
  if (e.isDirectory()) {
    const f = path.join(localesDir, e.name, 'translation.json');
    if (fs.existsSync(f)) validateFile(f);
  }
}
