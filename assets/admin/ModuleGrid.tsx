/**
 * The module grid: everything the extractor found, and a switch per module.
 *
 * A module appears here the moment its folder exists. Nothing in this file knows
 * any module by name — the grid is generated from `GET /modules`, which is the
 * whole reason the extractor can add a widget without a line of admin code.
 */

import { useMemo, useState } from "react";
import type { ReactElement } from "react";
import type { ModuleItem } from "./types";

interface ModuleGridProps {
  modules: ModuleItem[] | null;
  disabled: Set<string>;
  saving: boolean;
  onToggle: (id: string, nextDisabled: string[]) => void;
}

const TYPE_LABEL: Record<ModuleItem["type"], string> = {
  widget: "Widget",
  block: "Block",
  category: "Category",
};

export function ModuleGrid({
  modules,
  disabled,
  saving,
  onToggle,
}: ModuleGridProps): ReactElement {
  const [query, setQuery] = useState("");

  const filtered = useMemo(() => {
    if (!modules) return [];

    const needle = query.trim().toLowerCase();
    if (!needle) return modules;

    // Title, id, type and keywords all count: a user searching "faq" is looking
    // for the accordion whose keywords say "faq", not one titled "FAQ".
    return modules.filter((module) =>
      [module.title, module.id, module.type, module.category, ...module.keywords]
        .join(" ")
        .toLowerCase()
        .includes(needle),
    );
  }, [modules, query]);

  if (!modules) {
    return <p className="ba-admin__loading">Loading modules…</p>;
  }

  const nextDisabled = (id: string): string[] =>
    disabled.has(id) ? [...disabled].filter((each) => each !== id) : [...disabled, id];

  return (
    <section className="ba-grid-wrap">
      <div className="ba-grid-toolbar">
        <label className="ba-search">
          <span className="screen-reader-text">Search modules</span>
          <span className="dashicons dashicons-search" aria-hidden="true" />
          <input
            type="search"
            value={query}
            placeholder="Search modules…"
            onChange={(event) => setQuery(event.target.value)}
          />
        </label>

        <p className="ba-grid-toolbar__count">
          {filtered.length === modules.length
            ? `${modules.length} modules`
            : `${filtered.length} of ${modules.length}`}
        </p>
      </div>

      {filtered.length === 0 ? (
        <p className="ba-admin__empty">No modules match your search.</p>
      ) : (
        <ul className="ba-grid">
          {filtered.map((module) => {
            const off = !module.active;

            return (
              <li
                key={module.id}
                className={`ba-card ba-card--${module.tier}${off ? " is-off" : ""}`}
              >
                <div className="ba-card__head">
                  <span
                    className="ba-card__icon dashicons dashicons-editor-ul"
                    aria-hidden="true"
                  />
                  <div className="ba-card__headings">
                    <h3 className="ba-card__title">
                      {module.title}
                      {module.locked ? (
                        <span className="ba-badge ba-badge--locked">Locked</span>
                      ) : null}
                    </h3>
                    <p className="ba-card__meta">
                      <span className="ba-chip">{TYPE_LABEL[module.type]}</span>
                      <span className={`ba-chip ba-chip--${module.tier}`}>{module.tier}</span>
                      <code className="ba-card__id">{module.id}</code>
                      <span className="ba-card__hash" title="Content hash">
                        {module.hash}
                      </span>
                    </p>
                  </div>
                </div>

                {module.description ? (
                  <p className="ba-card__description">{module.description}</p>
                ) : null}

                {module.locked && module.lock_reason ? (
                  <p className="ba-card__reason">{module.lock_reason}</p>
                ) : null}

                <div className="ba-card__foot">
                  <label className="ba-switch">
                    <input
                      type="checkbox"
                      checked={!off}
                      disabled={saving || module.locked}
                      onChange={() => onToggle(module.id, nextDisabled(module.id))}
                    />
                    <span className="ba-switch__track" aria-hidden="true">
                      <span className="ba-switch__thumb" />
                    </span>
                    <span className="screen-reader-text">
                      {off ? "Enable" : "Disable"} {module.title}
                    </span>
                  </label>

                  <span className={`ba-state ba-state--${module.loaded ? "on" : "off"}`}>
                    {module.loaded ? "Enabled" : off ? "Disabled" : "Pending"}
                  </span>
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}
