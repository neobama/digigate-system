# DigiGate ERP MCP (Claude Code / Virtual Employee)

Read-only bridge dari Claude Code ke DigiGate ERP — akses penuh ke data operasional & keuangan (gaji, pengeluaran, reimburse, dll).

## Arsitektur

1. **Laravel Agent API** di `https://erp.digigate.id/api/agent/v1/*` (Bearer token)
2. **MCP stdio** di folder ini — Claude Code memanggil tools → MCP → HTTP API ERP

## Setup server (sekali)

Di production `.env`:

```env
AGENT_API_ENABLED=true
AGENT_API_TOKEN=<generate dengan: php artisan agent-api:token>
AGENT_API_RATE_LIMIT=120
```

Lalu:

```bash
php artisan config:cache
```

Uji:

```bash
curl -s -H "Authorization: Bearer $AGENT_API_TOKEN" \
  https://erp.digigate.id/api/agent/v1/overview | jq .
```

## Setup Claude Code / MCP

```bash
cd mcp/digigate-erp
npm install
```

Tambahkan ke config Claude Code MCP (lihat `claude_mcp.example.json`):

```json
{
  "mcpServers": {
    "digigate-erp": {
      "command": "node",
      "args": ["/path/to/digigate-system/mcp/digigate-erp/index.mjs"],
      "env": {
        "DIGIGATE_API_URL": "https://erp.digigate.id",
        "DIGIGATE_API_TOKEN": "same-token-as-server"
      }
    }
  }
}
```

Restart Claude Code.

## Tools (read-only)

**Operasional:** `get_overview`, `search_erp`, `list_employees`, `get_employee`, `list_tasks`, `attendance_summary`, `list_attendance`, `list_components`, `component_stock_summary`, `list_assemblies`, `list_invoices`, `list_leave_requests`, `list_device_types`, `list_logbooks`

**Keuangan:** `list_expenses`, `list_incomes`, `list_reimbursements`, `list_cashbons`, `list_budget_requests`, `list_salary_payments`, `financial_summary`

**Lainnya:** `list_warranty_claims`, `list_device_returns`, `list_documents`, `list_activity_logs`

## Catatan keamanan

- API **read-only** (GET saja)
- Token memberi akses penuh termasuk gaji & finance — jaga kerahasiaan token
- Jangan commit token ke git
- Rate limit default 120 req/menit
