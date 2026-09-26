/**
 * The admin app.
 *
 * Three panels behind a tab strip: the module grid, the licence, and general
 * settings. State is deliberately shallow — the module list is the server's
 * truth, and every mutation refetches rather than patching local state, because
 * a toggle can be refused (a locked Pro module, a module that is a dependency of
 * another) and guessing at the result locally would show a lie.
 */

import { useCallback, useEffect, useMemo, useState } from "react";
import type { ReactElement } from "react";
import { ApiError, api, boot } from "./api";
import { LicensePanel } from "./LicensePanel";
import { ModuleGrid } from "./ModuleGrid";
import { SettingsPanel } from "./SettingsPanel";
import type { LicenseResponse, ModuleItem, SettingsResponse } from "./types";

type Tab = "modules" | "license" | "settings";

const TABS: Array<{ id: Tab; label: string; icon: string }> = [
  { id: "modules", label: "modules", icon: "dashicons-screenoptions" },
  { id: "license", label: "license", icon: "dashicons-admin-users" },
  { id: "settings", label: "settings", icon: "dashicons-admin-generic" },
];

const t = (key: string, fallback: string): string => boot?.l10n[key] ?? fallback;

export function App(): ReactElement {
  const [tab, setTab] = useState<Tab>("modules");
  const [modules, setModules] = useState<ModuleItem[] | null>(null);
  const [settings, setSettings] = useState<SettingsResponse | null>(null);
  const [license, setLicense] = useState<LicenseResponse | null>(null);

  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  /**
   * Load everything once on mount.
   *
   * The three requests are independent, so they go together. `Promise.all` would
   * discard two successes because one failed, which is exactly the wrong outcome
   * for a licence hiccup on a screen whose main job is the module list.
   */
  useEffect(() => {
    let live = true;

    const load = async (): Promise<void> => {
      const [modulesResult, settingsResult, licenseResult] = await Promise.allSettled([
        api.modules(),
        api.settings(),
        api.license(),
      ]);

      if (!live) return;

      const failure = [modulesResult, settingsResult, licenseResult].find(
        (r): r is PromiseRejectedResult => r.status === "rejected",
      );

      if (failure) {
        const reason = failure.reason;
        setError(
          reason instanceof ApiError ? reason.message : t("loadFailed", "Could not load."),
        );
      }

      if (modulesResult.status === "fulfilled") setModules(modulesResult.value.modules);
      if (settingsResult.status === "fulfilled") setSettings(settingsResult.value);
      if (licenseResult.status === "fulfilled") setLicense(licenseResult.value);
    };

    void load();

    return () => {
      live = false;
    };
  }, []);

  const flash = useCallback((message: string | null) => {
    setNotice(message);
    if (!message) return;

    // A notice that never clears reads as a permanent state change, which is
    // worse than no confirmation at all.
    const timer = window.setTimeout(() => setNotice(null), 4000);
    return () => window.clearTimeout(timer);
  }, []);

  /**
   * Persist a set of disabled ids, then refetch.
   *
   * The grid's optimistic toggle is the exception: it updates the visible row
   * immediately and this call resolves it, so a slow network does not make the
   * switch feel broken.
   */
  const saveDisabled = useCallback(
    async (disabled: string[], optimistic?: (next: string[]) => void): Promise<void> => {
      setSaving(true);
      setError(null);
      optimistic?.(disabled);

      try {
        const [settingsResult, modulesResult] = await Promise.all([
          api.saveSettings(disabled),
          api.modules(),
        ]);

        setSettings(settingsResult);
        setModules(modulesResult.modules);
        flash(t("saved", "Saved"));
      } catch (caught) {
        setError(
          caught instanceof ApiError ? caught.message : t("saveFailed", "Could not save."),
        );
        // Roll back by re-reading, because the refusal reason lives on the server.
        try {
          setModules((await api.modules()).modules);
        } catch {
          /* The error already on screen is the one that matters. */
        }
      } finally {
        setSaving(false);
      }
    },
    [flash],
  );

  const disabledIds = useMemo(
    () => new Set(settings?.disabled ?? []),
    [settings],
  );

  if (!boot) {
    return (
      <div className="ba-notice ba-notice--error">
        {t("loadFailed", "Could not load. Is the manifest built?")}
      </div>
    );
  }

  return (
    <div className="ba-admin">
      <header className="ba-admin__header">
        <div className="ba-admin__identity">
          <span className="ba-admin__logo dashicons dashicons-admin-plugins" aria-hidden="true" />
          <div>
            <h1 className="ba-admin__title">{t("title", "Best Addons")}</h1>
            <p className="ba-admin__subtitle">
              {settings
                ? `${settings.loaded} / ${settings.total} modules loaded`
                : t("saving", "Loading…")}
            </p>
          </div>
        </div>

        <a
          className="button button-secondary"
          href={boot.links.widgetBuilder}
          target="_blank"
          rel="noreferrer"
        >
          Widget Builder
        </a>
      </header>

      <nav className="ba-tabs" aria-label={t("title", "Best Addons")}>
        {TABS.map((entry) => (
          <button
            key={entry.id}
            type="button"
            className={`ba-tabs__tab${tab === entry.id ? " is-active" : ""}`}
            aria-current={tab === entry.id ? "page" : undefined}
            onClick={() => setTab(entry.id)}
          >
            <span className={`dashicons ${entry.icon}`} aria-hidden="true" />
            {t(entry.label, entry.label)}
            {entry.id === "license" && license?.active ? (
              <span className="ba-badge ba-badge--pro">{t("pro", "Pro")}</span>
            ) : null}
          </button>
        ))}
      </nav>

      {error ? (
        <div className="ba-notice ba-notice--error" role="alert">
          {error}
        </div>
      ) : null}

      {notice ? (
        <div className="ba-notice ba-notice--ok" role="status">
          {notice}
        </div>
      ) : null}

      {tab === "modules" ? (
        <ModuleGrid
          modules={modules}
          disabled={disabledIds}
          saving={saving}
          onToggle={(id, next) =>
            void saveDisabled(
              next,
              (ids) =>
                setModules((current) =>
                  current ? moduleWithEnabled(current, id, !ids.includes(id)) : current,
                ),
            )
          }
        />
      ) : null}

      {tab === "license" ? (
        <LicensePanel
          license={license}
          onChanged={(message) => {
            void api.license().then((next) => {
              setLicense(next);
              setModules((current) =>
                current
                  ? current.map((m) => (m.tier === "pro" ? { ...m, locked: !next.active } : m))
                  : current,
              );
              flash(message);
            });
          }}
          onError={setError}
        />
      ) : null}

      {tab === "settings" ? (
        <SettingsPanel
          settings={settings}
          saving={saving}
          onReset={() => void saveDisabled([])}
        />
      ) : null}
    </div>
  );
}

/**
 * Flip one module's `active` flag in place.
 *
 * `setModules` is called with a function so two toggles fired in the same tick
 * do not overwrite each other by reading the same stale array.
 */
const moduleWithEnabled = (
  modules: ModuleItem[],
  id: string,
  active: boolean,
): ModuleItem[] =>
  modules.map((module) => (module.id === id ? { ...module, active } : module));
