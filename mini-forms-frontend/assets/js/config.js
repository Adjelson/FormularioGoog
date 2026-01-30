export const DEFAULT_API_BASE = (() => {
  const saved = localStorage.getItem("api_base");
  return saved ?? "http://localhost/mini-forms-backend/public";
})();

export function setApiBase(url) {
  localStorage.setItem("api_base", (url || "").replace(/\/$/, ""));
}

export function getApiBase() {
  return (localStorage.getItem("api_base") || DEFAULT_API_BASE).replace(/\/$/, "");
}

export function getToken() {
  return localStorage.getItem("auth_token") || "";
}

export function setToken(token) {
  localStorage.setItem("auth_token", token || "");
}

export function clearToken() {
  localStorage.removeItem("auth_token");
}

export function escapeHtml(s) {
  return String(s ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

export function qs(name) {
  const u = new URL(window.location.href);
  return u.searchParams.get(name);
}

export function setStatus(el, msg, kind="info") {
  if (!el) return;
  const map = {
    info: "bg-blue-50 text-blue-800 border-blue-200",
    ok: "bg-emerald-50 text-emerald-800 border-emerald-200",
    warn: "bg-amber-50 text-amber-900 border-amber-200",
    err: "bg-red-50 text-red-800 border-red-200",
  };
  el.className = "mt-3 border rounded-lg px-3 py-2 text-sm " + (map[kind] || map.info);
  el.textContent = msg || "";
  el.hidden = !msg;
}

export function copyToClipboard(text) {
  return navigator.clipboard.writeText(String(text || ""));
}

export function humanBytes(bytes) {
  const n = Number(bytes || 0);
  if (!n) return "0 B";
  const units = ["B","KB","MB","GB"];
  let v = n, i = 0;
  while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
  return `${v.toFixed(v >= 10 || i === 0 ? 0 : 1)} ${units[i]}`;
}
