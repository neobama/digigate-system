#!/usr/bin/env node
/**
 * DigiGate ERP MCP — read-only tools for Claude Code / Virtual Employee.
 *
 * Env:
 *   DIGIGATE_API_URL   e.g. https://erp.digigate.id
 *   DIGIGATE_API_TOKEN AGENT_API_TOKEN from ERP .env
 */

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';

const BASE_URL = (process.env.DIGIGATE_API_URL || process.env.AGENT_API_URL || '').replace(/\/$/, '');
const TOKEN = process.env.DIGIGATE_API_TOKEN || process.env.AGENT_API_TOKEN || '';

if (!BASE_URL) {
  console.error('DIGIGATE_API_URL is required (e.g. https://erp.digigate.id)');
  process.exit(1);
}
if (!TOKEN) {
  console.error('DIGIGATE_API_TOKEN is required');
  process.exit(1);
}

async function apiGet(path, query = {}) {
  const url = new URL(`${BASE_URL}/api/agent/v1${path}`);
  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === '') continue;
    url.searchParams.set(key, String(value));
  }

  const response = await fetch(url, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${TOKEN}`,
      'X-Digigate-Agent-Token': TOKEN,
    },
  });

  const text = await response.text();
  let body;
  try {
    body = text ? JSON.parse(text) : null;
  } catch {
    body = { raw: text };
  }

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}: ${typeof body === 'object' ? JSON.stringify(body) : text}`);
  }

  return body;
}

function toolResult(data) {
  return {
    content: [
      {
        type: 'text',
        text: typeof data === 'string' ? data : JSON.stringify(data, null, 2),
      },
    ],
  };
}

const TOOLS = [
  {
    name: 'get_overview',
    description: 'Ringkasan ERP: jumlah karyawan, task terbuka, stok komponen, invoice, absensi hari ini.',
    inputSchema: { type: 'object', properties: {}, additionalProperties: false },
  },
  {
    name: 'search_erp',
    description: 'Cari lintas entitas: karyawan, task, invoice, assembly, komponen.',
    inputSchema: {
      type: 'object',
      properties: {
        q: { type: 'string', description: 'Kata kunci pencarian' },
        limit: { type: 'number', description: 'Maks hasil per jenis (default 10)' },
      },
      required: ['q'],
      additionalProperties: false,
    },
  },
  {
    name: 'list_employees',
    description: 'Daftar karyawan (tanpa data gaji).',
    inputSchema: {
      type: 'object',
      properties: {
        q: { type: 'string' },
        active_only: { type: 'boolean', default: true },
        limit: { type: 'number' },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'get_employee',
    description: 'Detail satu karyawan by UUID.',
    inputSchema: {
      type: 'object',
      properties: { id: { type: 'string' } },
      required: ['id'],
      additionalProperties: false,
    },
  },
  {
    name: 'list_tasks',
    description: 'Daftar pekerjaan/kalender. Filter from/to (YYYY-MM-DD), status, employee_id, category.',
    inputSchema: {
      type: 'object',
      properties: {
        from: { type: 'string' },
        to: { type: 'string' },
        status: { type: 'string' },
        employee_id: { type: 'string' },
        category: { type: 'string', description: 'pemasangan_ftth|komplain_ftth|digigate|internal' },
        limit: { type: 'number' },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'attendance_summary',
    description: 'Rekap absensi harian (lengkap / belum lengkap / tidak absen).',
    inputSchema: {
      type: 'object',
      properties: {
        date: { type: 'string', description: 'YYYY-MM-DD, default hari ini' },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'list_attendance',
    description: 'Riwayat tap in/out. Filter date dan/atau employee_id.',
    inputSchema: {
      type: 'object',
      properties: {
        date: { type: 'string' },
        employee_id: { type: 'string' },
        limit: { type: 'number' },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'list_components',
    description: 'Stok komponen. Default status=available.',
    inputSchema: {
      type: 'object',
      properties: {
        status: { type: 'string' },
        name: { type: 'string' },
        limit: { type: 'number' },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'component_stock_summary',
    description: 'Rekap jumlah komponen per nama + status (available/used/damaged).',
    inputSchema: { type: 'object', properties: {}, additionalProperties: false },
  },
  {
    name: 'list_assemblies',
    description: 'Daftar assembly perangkat.',
    inputSchema: {
      type: 'object',
      properties: {
        product_type: { type: 'string', description: 'Macan|Maleo|Komodo|...' },
        limit: { type: 'number' },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'list_invoices',
    description: 'Daftar invoice (proforma/paid/delivered).',
    inputSchema: {
      type: 'object',
      properties: {
        status: { type: 'string' },
        q: { type: 'string' },
        limit: { type: 'number' },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'list_leave_requests',
    description: 'Pengajuan izin/sakit/cuti/lembur.',
    inputSchema: {
      type: 'object',
      properties: {
        status: { type: 'string' },
        employee_id: { type: 'string' },
        limit: { type: 'number' },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'list_device_types',
    description: 'Katalog tipe perangkat + komponen wajib (BOM).',
    inputSchema: {
      type: 'object',
      properties: { active_only: { type: 'boolean', default: true } },
      additionalProperties: false,
    },
  },
  {
    name: 'list_logbooks',
    description: 'Daily logbook karyawan.',
    inputSchema: {
      type: 'object',
      properties: {
        from: { type: 'string' },
        to: { type: 'string' },
        employee_id: { type: 'string' },
        limit: { type: 'number' },
      },
      additionalProperties: false,
    },
  },
];

const server = new Server(
  { name: 'digigate-erp', version: '1.0.0' },
  { capabilities: { tools: {} } },
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({ tools: TOOLS }));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const name = request.params.name;
  const args = request.params.arguments || {};

  try {
    switch (name) {
      case 'get_overview':
        return toolResult(await apiGet('/overview'));
      case 'search_erp':
        return toolResult(await apiGet('/search', { q: args.q, limit: args.limit }));
      case 'list_employees':
        return toolResult(await apiGet('/employees', {
          q: args.q,
          active_only: args.active_only === undefined ? undefined : args.active_only ? '1' : '0',
          limit: args.limit,
        }));
      case 'get_employee':
        return toolResult(await apiGet(`/employees/${args.id}`));
      case 'list_tasks':
        return toolResult(await apiGet('/tasks', args));
      case 'attendance_summary':
        return toolResult(await apiGet('/attendance/summary', { date: args.date }));
      case 'list_attendance':
        return toolResult(await apiGet('/attendance', args));
      case 'list_components':
        return toolResult(await apiGet('/components', args));
      case 'component_stock_summary':
        return toolResult(await apiGet('/components/stock'));
      case 'list_assemblies':
        return toolResult(await apiGet('/assemblies', args));
      case 'list_invoices':
        return toolResult(await apiGet('/invoices', args));
      case 'list_leave_requests':
        return toolResult(await apiGet('/leave-requests', args));
      case 'list_device_types':
        return toolResult(await apiGet('/device-types', {
          active_only: args.active_only === undefined ? undefined : args.active_only ? '1' : '0',
        }));
      case 'list_logbooks':
        return toolResult(await apiGet('/logbooks', args));
      default:
        return {
          isError: true,
          content: [{ type: 'text', text: `Unknown tool: ${name}` }],
        };
    }
  } catch (error) {
    return {
      isError: true,
      content: [{ type: 'text', text: error instanceof Error ? error.message : String(error) }],
    };
  }
});

const transport = new StdioServerTransport();
await server.connect(transport);
