#!/usr/bin/env node
/*
 * extract_raw.js — deterministic extractor for legacy PHP CRUD files.
 *
 * Parses raw SQL, $_POST/$_GET fields, and form 'required' attributes from a
 * legacy PHP file and emits a JSON dump of the FACTS. It does NO design work:
 * no type inference, no naming, no DTO shaping. The decompose-legacy skill
 * reads this dump and applies design judgment (types, required semantics, DTO
 * shapes, contracts) on top.
 *
 * Why a script instead of the LLM?
 *   - Column names, table names, JOINs, form fields: deterministic. Regex gets
 *     them right every time. Zero hallucination.
 *   - Types, required semantics, DTO design, validation rules: judgment. LLM.
 *
 * Pure Node built-ins only — no dependencies, no package.json, no npm install.
 *
 * Usage:
 *   node extract_raw.js <legacy_php_file> [output_json]
 *   # default output: output/specs/_raw_extract.json
 */
'use strict';

const fs = require('fs');
const path = require('path');

// A string is SQL if it starts with a DML keyword AND has a real clause.
// The clause check rejects false positives like form value="delete" /
// value="update", which start with a keyword but have no FROM/SET/etc.
function isSql(s) {
  if (!/^\s*(SELECT|INSERT|UPDATE|DELETE)\b/i.test(s)) return false;
  return /\b(FROM|INTO|VALUES|SET|WHERE|JOIN)\b/i.test(s);
}

function findSqlStrings(source) {
  const strings = [];
  for (const m of source.matchAll(/"((?:[^"\\]|\\.)*)"/gs)) strings.push(m[1]);
  for (const m of source.matchAll(/'((?:[^'\\]|\\.)*)'/gs)) strings.push(m[1]);
  return strings.filter(isSql);
}

function parseSql(sql) {
  const info = { raw: sql.replace(/\s+/g, ' ').trim() };
  const head = sql.match(/^\s*(SELECT|INSERT|UPDATE|DELETE)\b/i);
  const stmt = head ? head[1].toUpperCase() : 'UNKNOWN';
  info.statement = stmt;

  if (stmt === 'INSERT') {
    const m = sql.match(/INSERT\s+INTO\s+(\w+)\s*\(([^)]+)\)/i);
    if (m) {
      info.table = m[1];
      info.columns = m[2].split(',').map((c) => c.trim()).filter(Boolean);
    }
  } else if (stmt === 'UPDATE') {
    const m = sql.match(/UPDATE\s+(\w+)\s+SET\s+(.+?)(?:\bWHERE\b|$)/is);
    if (m) {
      info.table = m[1];
      info.columns = m[2]
        .split(',')
        .map((p) => p.split('=')[0].trim())
        .filter(Boolean);
    }
  } else if (stmt === 'DELETE') {
    const m = sql.match(/DELETE\s+FROM\s+(\w+)/i);
    if (m) info.table = m[1];
  } else if (stmt === 'SELECT') {
    const m = sql.match(/FROM\s+(\w+)/i);
    if (m) info.table = m[1];
    const sm = sql.match(/SELECT\s+(.+?)\s+FROM/is);
    if (sm) info.select_raw = sm[1].replace(/\s+/g, ' ').trim();
    const joins = [];
    for (const jm of sql.matchAll(
      /JOIN\s+(\w+)\s+\w+\s+ON\s+([\w.]+)\s*=\s*([\w.]+)/gi,
    )) {
      joins.push({ target_table: jm[1], left: jm[2], right: jm[3] });
    }
    if (joins.length) info.joins = joins;
  }

  // WHERE <col> =  -> likely PK / lookup column (e.g. WHERE id = $id)
  const wm = sql.match(/\bWHERE\s+(\w+)\s*=/i);
  if (wm) info.where_column = wm[1];

  return info;
}

function main() {
  const args = process.argv.slice(2);
  if (args.length < 1) {
    console.error('usage: extract_raw.js <legacy_php_file> [output_json]');
    process.exit(2);
  }

  const srcPath = args[0];
  const source = fs.readFileSync(srcPath, 'utf-8');
  const parsedSql = findSqlStrings(source).map(parseSql);

  // Merge per-table columns. INSERT/UPDATE are authoritative; the WHERE col
  // (id) fills in PKs that never appear in INSERT/UPDATE column lists.
  const tables = {};
  for (const s of parsedSql) {
    const t = s.table;
    if (!t) continue;
    if (!tables[t]) tables[t] = { name: t, statements: new Set(), columns: [] };
    const entry = tables[t];
    entry.statements.add(s.statement);
    for (const c of s.columns || []) {
      if (c.toLowerCase() !== 'now()' && !entry.columns.includes(c)) {
        entry.columns.push(c);
      }
    }
    if (s.where_column && !entry.columns.includes(s.where_column)) {
      entry.columns.push(s.where_column);
      entry.likely_primary_key = s.where_column;
    }
  }

  const tableList = Object.values(tables).map((t) => {
    const o = { name: t.name, statements: [...t.statements].sort(), columns: t.columns };
    if (t.likely_primary_key) o.likely_primary_key = t.likely_primary_key;
    return o;
  });

  const relationships = [];
  for (const s of parsedSql) {
    for (const j of s.joins || []) {
      relationships.push({
        source_table: s.table,
        target_table: j.target_table,
        join_left: j.left,
        join_right: j.right,
      });
    }
  }

  const uniq = (arr) => [...new Set(arr)].sort();
  const postFields = uniq(
    [...source.matchAll(/\$_POST\s*\[\s*['"]([^'"]+)['"]\s*\]/g)].map((m) => m[1]),
  );
  const getParams = uniq(
    [...source.matchAll(/\$_GET\s*\[\s*['"]([^'"]+)['"]\s*\]/g)].map((m) => m[1]),
  );

  // Form fields marked 'required' in the HTML (<input ... name="x" ... required>)
  const required = [];
  for (const tag of source.matchAll(/<(?:input|textarea|select)\b[^>]*>/gi)) {
    const text = tag[0];
    const nm = text.match(/name\s*=\s*["']([^"']+)['"]/i);
    if (nm && /(?:^|\s)required(?:\s|=|>|\/)/i.test(text)) required.push(nm[1]);
  }

  const dump = {
    source_file: srcPath,
    tables: tableList,
    relationships,
    post_fields: postFields,
    get_params: getParams,
    form_required_fields: uniq(required),
    parsed_sql: parsedSql,
  };

  const outPath = args[1] || 'output/specs/_raw_extract.json';
  fs.mkdirSync(path.dirname(outPath), { recursive: true });
  fs.writeFileSync(outPath, JSON.stringify(dump, null, 2) + '\n');

  console.log(
    `[extract_raw] ${tableList.length} table(s), ${relationships.length} relationship(s), ` +
      `${postFields.length} post field(s), ${getParams.length} get param(s) -> ${outPath}`,
  );
  console.log(JSON.stringify(dump, null, 2));
}

main();
