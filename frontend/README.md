# Frontend (SPA)

React 19 + Vite + TypeScript + Tailwind 4 + React Query. Interface em pt-BR.

```bash
npm install
npm run dev        # http://localhost:5173, proxy /api -> http://localhost:8000 (VITE_API_PROXY para mudar)
npm run build      # gera dist/ (servido pelo container nginx em produção)
npx tsc -b && npx eslint src
```

- `src/lib/api.ts`: axios com token Bearer (Sanctum) e helpers de erro e download.
- `src/components/Razonete.tsx`: o resultado da simulação desenhado como razonete (conta em T).
- A cor principal vem do cadastro do escritório (`--brand`), por isso cada escritório vê o app com a própria marca.
