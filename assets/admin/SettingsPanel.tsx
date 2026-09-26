/**
 * Settings panel.
 *
 * Reads the same `best_addons_module_state` option the module grid writes, so
 * this panel is about recovery rather than configuration: "enable everything" is
 * the one action a site owner needs when a disable switch has locked them out of
 * their own site.
 */

import type { ReactElement } from "react";
import { boot } from "./api";
import type { SettingsResponse } from "./types";

interface SettingsPanelProps {
  settings: SettingsResponse | null;
  saving: boolean;
  onReset: () => void;
}

const t = (key: string, fallback: string): string => boot?.l10n[key] ?? fallback;

export function SettingsPanel({
  settings,
  saving,
  onReset,
}: SettingsPanelProps): ReactElement {
  if (!settings) {
    return <p className="ba-admin__loading">Loading settings…</p>;
  }

  const disabled = settings.disabled;

  return (
    <section className="ba-settings">
      <div className="ba-settings__row">
        <div>
          <h2 className="ba-settings__title">{t("reset", "Enable all modules")}</h2>
          <p className="ba-settings__description">
            Clears every disabled flag. Use this if a toggle has left the plugin in a
            state you cannot get out of.
          </p>
        </div>

        <button
          type="button"
          className="button button-secondary"
          disabled={saving || disabled.length === 0}
          onClick={onReset}
        >
          {t("reset", "Enable all modules")}
        </button>
      </div>

      <dl className="ba-settings__facts">
        <div>
          <dt>Modules in manifest</dt>
          <dd>{settings.total}</dd>
        </div>
        <div>
          <dt>Registered this request</dt>
          <dd>{settings.loaded}</dd>
        </div>
        <div>
          <dt>Disabled</dt>
          <dd>{disabled.length}</dd>
        </div>
      </dl>

      {disabled.length > 0 ? (
        <p className="ba-settings__list">
          {disabled.map((id) => (
            <code key={id}>{id}</code>
          ))}
        </p>
      ) : null}

      <p className="ba-settings__note">
        Preferences are stored in <code>best_addons_preferences</code> and are read by
        modules through <code>Enablement</code>. The switch above writes
        <code> best_addons_module_state</code>.
      </p>
    </section>
  );
}
