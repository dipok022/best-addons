/**
 * The contract between PHP and the admin app.
 *
 * Every shape here mirrors a PHP array that is actually returned by
 * `includes/Admin/`. The one rule: a field the PHP side may omit has to be typed
 * as optional. `AdminApp::bootstrap_data()` and the REST controllers all build
 * their arrays with conditionals, so nothing here may be assumed present.
 */

export type ModuleTier = "free" | "pro";

export type ModuleType = "widget" | "block" | "category";

export interface ModuleItem {
  id: string;
  type: ModuleType;
  tier: ModuleTier;
  title: string;
  description: string;
  keywords: string[];
  icon: string;
  version: string;
  category: string;
  hash: string;
  /** Dist paths per asset kind: `style`, `script`, `editor`. */
  assets: Record<string, string>;
  /** Whether the module is currently enabled. `false` when locked. */
  active: boolean;
  /** A Pro module on a site with no active licence. */
  locked: boolean;
  /** Whether it passed the runtime filters and is registered right now. */
  loaded: boolean;
  /** Human-readable explanation, set only when `locked`. */
  lock_reason: string;
}

export interface ModulesResponse {
  modules: ModuleItem[];
  stats: Record<string, number>;
  categories: string[];
  license: {
    active: boolean;
    reason: string;
  };
  meta: {
    plugin: string;
    generated: number;
    error: string | null;
  };
}

export interface SettingsResponse {
  /** Ids the owner has explicitly turned off. */
  disabled: string[];
  preferences: Record<string, unknown>;
  loaded: number;
  total: number;
}

export interface LicenseResponse {
  active: boolean;
  status: string;
  customer: string;
  expires: string;
  days_remaining: number;
  last_check: number;
  /** Never the full key — the options panel is readable by any admin. */
  key_masked: string;
}

export interface BootstrapData {
  restUrl: string;
  nonce: string;
  homeUrl: string;
  license: {
    active: boolean;
  };
  links: {
    widgetBuilder: string;
  };
  l10n: Record<string, string>;
}
