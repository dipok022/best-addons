/**
 * Licence panel.
 *
 * Activation is a call to the site owner's own store: `LicenseController` fires
 * the `best_addons_activate_license` filter and treats a `WP_Error` as a
 * rejection. Until that filter is wired to a real store, a void handler accepts
 * the key locally — which is why this panel says "saved locally" rather than
 * claiming a remote activation happened.
 */

import { useState } from "react";
import type { ReactElement } from "react";
import { ApiError, api, boot } from "./api";
import type { LicenseResponse } from "./types";

interface LicensePanelProps {
  license: LicenseResponse | null;
  onChanged: (message: string) => void;
  onError: (message: string) => void;
}

const t = (key: string, fallback: string): string => boot?.l10n[key] ?? fallback;

export function LicensePanel({
  license,
  onChanged,
  onError,
}: LicensePanelProps): ReactElement {
  const [key, setKey] = useState("");
  const [busy, setBusy] = useState(false);

  const run = async (work: () => Promise<{ message: string }>): Promise<void> => {
    setBusy(true);

    try {
      const result = await work();
      setKey("");
      onChanged(result.message);
    } catch (caught) {
      onError(caught instanceof ApiError ? caught.message : t("saveFailed", "Could not save."));
    } finally {
      setBusy(false);
    }
  };

  if (!license) {
    return <p className="ba-admin__loading">Loading licence…</p>;
  }

  return (
    <section className="ba-license">
      <div className={`ba-license__status ba-license__status--${license.active ? "on" : "off"}`}>
        <span
          className={`dashicons ${license.active ? "dashicons-yes-alt" : "dashicons-lock"}`}
          aria-hidden="true"
        />
        <div>
          <h2 className="ba-license__headline">
            {license.active ? t("licenceActive", "Pro licence active") : t("licenceNone", "")}
          </h2>
          {license.active ? (
            <p className="ba-license__detail">
              {license.customer ? `${license.customer} · ` : ""}
              {license.key_masked}
              {license.expires
                ? ` · ${
                    license.days_remaining >= 0
                      ? t("expiresIn", "Expires in %d days").replace(
                          "%d",
                          String(license.days_remaining),
                        )
                      : t("perpetual", "Perpetual licence")
                  }`
                : ""}
            </p>
          ) : (
            <p className="ba-license__detail">{license.status}</p>
          )}
        </div>
      </div>

      {license.active ? (
        <button
          type="button"
          className="button button-secondary"
          disabled={busy}
          onClick={() => void run(() => api.deactivate())}
        >
          {busy ? t("saving", "Saving…") : t("deactivate", "Deactivate")}
        </button>
      ) : (
        <form
          className="ba-license__form"
          onSubmit={(event) => {
            event.preventDefault();
            const trimmed = key.trim();

            if (!trimmed) return;
            void run(() => api.activate(trimmed));
          }}
        >
          <label className="ba-field">
            <span>{t("licenceKey", "Licence key")}</span>
            <input
              type="text"
              value={key}
              autoComplete="off"
              spellCheck={false}
              onChange={(event) => setKey(event.target.value)}
              placeholder="00000000-0000-0000-0000-000000000000"
            />
          </label>

          <button type="submit" className="button button-primary" disabled={busy || !key.trim()}>
            {busy ? t("saving", "Saving…") : t("activate", "Activate")}
          </button>
        </form>
      )}
    </section>
  );
}
