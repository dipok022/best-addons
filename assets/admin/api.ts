/**
 * REST client.
 *
 * One place that knows how to talk to `best-addons/v1`, so the nonce header and
 * the error shape are handled once rather than at every call site.
 */

import type {
  BootstrapData,
  LicenseResponse,
  ModulesResponse,
  SettingsResponse,
} from "./types";

/** An error carrying the message the server sent, ready to show a user. */
export class ApiError extends Error {
  /** WordPress's machine-readable error code, when it sent one. */
  readonly code: string;

  constructor(message: string, code = "unknown") {
    super(message);
    this.name = "ApiError";
    this.code = code;
  }
}

const bootstrap: BootstrapData | null = (() => {
  const element = document.getElementById("ba-admin-data");
  if (!element?.textContent) return null;

  try {
    return JSON.parse(element.textContent) as BootstrapData;
  } catch {
    // A malformed payload means the PHP side changed shape. Reporting it as a
    // null bootstrap gets the app to show its error state, which is more useful
    // than a stack trace inside a mount callback.
    return null;
  }
})();

export const boot = bootstrap;

/**
 * `true` when the nonce is absent or the session has expired.
 *
 * WordPress returns 403 with `rest_cookie_invalid_nonce` for a stale nonce, and
 * the only recovery is a reload — so the app treats that specific case as a hard
 * stop rather than something to retry.
 */
const isAuthError = (status: number, code: string): boolean =>
  status === 401 ||
  status === 403 ||
  code === "rest_cookie_invalid_nonce" ||
  code === "rest_forbidden";

const request = async <T>(path: string, init: RequestInit = {}): Promise<T> => {
  if (!bootstrap) {
    throw new ApiError("The admin payload is missing.", "no_bootstrap");
  }

  const response = await fetch(`${bootstrap.restUrl}${path}`, {
    ...init,
    credentials: "same-origin",
    headers: {
      "Content-Type": "application/json",
      "X-WP-Nonce": bootstrap.nonce,
      ...(init.headers ?? {}),
    },
  });

  let body: unknown = null;

  try {
    body = await response.json();
  } catch {
    // A non-JSON body here means a PHP fatal or an HTML error page, neither of
    // which should surface as a JSON parse error to the user.
    if (!response.ok) {
      throw new ApiError(`Request failed (${response.status}).`, String(response.status));
    }
  }

  if (!response.ok) {
    const payload = body as { code?: string; message?: string } | null;
    const code = payload?.code ?? String(response.status);

    if (isAuthError(response.status, code)) {
      throw new ApiError("Your session has expired. Reload the page.", code);
    }

    throw new ApiError(payload?.message ?? `Request failed (${response.status}).`, code);
  }

  return body as T;
};

export interface LicenseMutationResult {
  active: boolean;
  message: string;
}

export const api = {
  modules: (): Promise<ModulesResponse> => request<ModulesResponse>("modules"),

  settings: (): Promise<SettingsResponse> => request<SettingsResponse>("settings"),

  saveSettings: (disabled: string[]): Promise<SettingsResponse> =>
    request<SettingsResponse>("settings", {
      method: "POST",
      body: JSON.stringify({ disabled }),
    }),

  license: (): Promise<LicenseResponse> => request<LicenseResponse>("license"),

  /**
   * Both mutations answer with a status message rather than the full licence
   * record, so callers refetch with `license()` afterwards. That is deliberate:
   * `LicenseManager` clears its caches on both paths, and a refetch is the only
   * way to be sure the UI shows what the server now believes.
   */
  activate: (key: string): Promise<LicenseMutationResult> =>
    request<LicenseMutationResult>("license", {
      method: "POST",
      body: JSON.stringify({ action: "activate", key }),
    }),

  deactivate: (): Promise<LicenseMutationResult> =>
    request<LicenseMutationResult>("license", {
      method: "POST",
      body: JSON.stringify({ action: "deactivate" }),
    }),
};
