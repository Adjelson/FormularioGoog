import { escapeHtml } from "./config.js";

export function renderEmpty(target, title, hint="") {
  target.innerHTML = `
    <div class="rounded-2xl border border-dashed p-6 text-center bg-white">
      <div class="text-base font-semibold">${escapeHtml(title)}</div>
      ${hint ? `<div class="text-sm text-gray-600 mt-1">${escapeHtml(hint)}</div>` : ""}
    </div>
  `;
}

export function pill(text, kind="gray") {
  const map = {
    gray: "bg-gray-100 text-gray-800",
    green: "bg-emerald-100 text-emerald-800",
    amber: "bg-amber-100 text-amber-900",
    blue: "bg-blue-100 text-blue-800",
    red: "bg-red-100 text-red-800",
  };
  return `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${map[kind]||map.gray}">${escapeHtml(text)}</span>`;
}
