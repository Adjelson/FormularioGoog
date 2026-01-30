// assets/js/api.js
import { getApiBase, getToken, clearToken } from "./config.js";

async function request(
  path,
  { method = "GET", headers = {}, body = null, isFormData = false } = {},
) {
  const base = getApiBase();
  const url = base + path;

  const token = getToken();
  const h = new Headers(headers);

  if (token) {
    h.set("Authorization", "Bearer " + token);
  }
  if (!isFormData) {
    h.set("Content-Type", "application/json");
  }

  const res = await fetch(url, {
    method,
    headers: h,
    body: isFormData ? body : body ? JSON.stringify(body) : null,
  });

  const ct = res.headers.get("content-type") || "";
  let payload = null;

  if (ct.includes("application/json")) {
    payload = await res.json().catch(() => null);
  } else {
    payload = await res.text().catch(() => null);
  }

  if (res.status === 401) {
    clearToken();
  }

  if (!res.ok) {
    const err = new Error(
      (payload && payload.error && payload.error.message) ||
        `HTTP ${res.status}`,
    );
    err.status = res.status;
    err.payload = payload;
    throw err;
  }

  return payload;
}

export async function fetchFileBlob(uploadId) {
  const base = getApiBase();
  const token = getToken();

  const res = await fetch(base + `/api/admin/files/${uploadId}`, {
    method: "GET",
    headers: token ? { Authorization: "Bearer " + token } : {},
  });

  if (!res.ok) {
    let payload = null;
    const ct = res.headers.get("content-type") || "";
    if (ct.includes("application/json")) {
      try {
        payload = await res.json();
      } catch {
        // ignore
      }
    }
    const msg = payload?.error?.message || `HTTP ${res.status}`;
    const err = new Error(msg);
    err.status = res.status;
    err.payload = payload;
    throw err;
  }

  const blob = await res.blob();

  const dispo = res.headers.get("content-disposition") || "";
  let filename = null;
  const m = dispo.match(/filename="?([^"]+)"?/i);
  if (m) filename = m[1];

  return { blob, filename };
}

export const api = {
  // Auth
  login: (email, password) =>
    request("/api/login", { method: "POST", body: { email, password } }),

  // Forms (admin)
  listForms: () => request("/api/admin/forms"),
  listArchivedForms: () => request("/api/admin/forms/archived"),
  createForm: (data) =>
    request("/api/admin/forms", { method: "POST", body: data }),
  getForm: (id) => request(`/api/admin/forms/${id}`),
  updateForm: (id, data) =>
    request(`/api/admin/forms/${id}`, { method: "PUT", body: data }),
  publishForm: (id) =>
    request(`/api/admin/forms/${id}/publish`, { method: "POST", body: {} }),
  unpublishForm: (id) =>
    request(`/api/admin/forms/${id}/unpublish`, { method: "POST", body: {} }),
  archiveForm: (id) =>
    request(`/api/admin/forms/${id}/archive`, { method: "POST", body: {} }),

  // Questions & options
  createQuestion: (formId, data) =>
    request(`/api/admin/forms/${formId}/questions`, {
      method: "POST",
      body: data,
    }),
  updateQuestion: (qid, data) =>
    request(`/api/admin/questions/${qid}`, { method: "PUT", body: data }),

  // 🔥 NOVO: arquivar (eliminar) pergunta
  archiveQuestion: (qid) =>
    request(`/api/admin/questions/${qid}/archive`, {
      method: "POST",
      body: {},
    }),

  createOption: (qid, data) =>
    request(`/api/admin/questions/${qid}/options`, {
      method: "POST",
      body: data,
    }),
  updateOption: (oid, data) =>
    request(`/api/admin/options/${oid}`, { method: "PUT", body: data }),
  deleteOption: (oid) =>
    request(`/api/admin/options/${oid}`, { method: "DELETE" }),

  // Responses (admin)
  listResponses: (formId) => request(`/api/admin/forms/${formId}/responses`),
  getResponse: (rid) => request(`/api/admin/responses/${rid}`),

  // Public side
  getPublicForm: (slug) =>
    request(`/api/public/forms/${encodeURIComponent(slug)}`),
  uploadFile: async (file) => {
    const fd = new FormData();
    fd.append("file", file);
    return request("/api/public/uploads", {
      method: "POST",
      body: fd,
      isFormData: true,
    });
  },
  submitPublicResponse: (slug, payload) =>
    request(`/api/public/forms/${encodeURIComponent(slug)}/responses`, {
      method: "POST",
      body: payload,
    }),
};

export function downloadUrl(uploadId) {
  const base = getApiBase();
  return base + `/api/admin/files/${uploadId}`;
}
