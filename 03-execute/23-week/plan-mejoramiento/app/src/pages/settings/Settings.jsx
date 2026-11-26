
import { useState } from "react";

import {
  Settings as SettingsIcon,
  UserRound,
  ShieldCheck,
  Bell,
  Monitor,
  LockKeyhole,
  Info,
  Save,
  CheckCircle2
} from "lucide-react";

import { getUser } from "../../services/auth";
import settingsBanner from "../../assets/images/Settings.png";
import "./Settings.css";

export default function Settings() {
  const user = getUser();

  const [preferences, setPreferences] = useState(() => ({
    notifications: localStorage.getItem("mobility_notifications") !== "false",
    compactView: localStorage.getItem("mobility_compact_view") === "true"
  }));

  const [saved, setSaved] = useState(false);

  const userName =
    user?.name ||
    user?.full_name ||
    user?.username ||
    "Mobility Aids User";

  const userEmail = user?.email || "Email not available";
  const userRole = user?.role || "System User";

  const handlePreference = (key) => {
    setPreferences((current) => ({
      ...current,
      [key]: !current[key]
    }));

    setSaved(false);
  };

  const savePreferences = () => {
    localStorage.setItem(
      "mobility_notifications",
      String(preferences.notifications)
    );

    localStorage.setItem(
      "mobility_compact_view",
      String(preferences.compactView)
    );

    setSaved(true);
  };

  return (
    <main className="settings-page">

      {/* PAGE HEADER */}
      <section
        className="settings-hero"
        style={{
          backgroundImage: `
            linear-gradient(
              90deg,
              rgba(9, 29, 49, 0.38),
              rgba(9, 29, 49, 0.08)
            ),
            url("${settingsBanner}")
          `
        }}
      >
        <div className="settings-hero-icon">
          <SettingsIcon size={36} strokeWidth={1.8} />
        </div>

        <div className="settings-hero-content">
          <span>ACCOUNT & SYSTEM CONFIGURATION</span>

          <h1>
            System <strong>Settings</strong>
          </h1>

          <p>
            Manage your account information, preferences and
            system experience.
          </p>

          <div className="settings-hero-line"></div>
        </div>
      </section>

      {/* ACCOUNT OVERVIEW */}
      <section className="settings-section">
        <div className="settings-section-heading">
          <div className="settings-heading-icon">
            <UserRound size={22} />
          </div>

          <div>
            <h2>Account Information</h2>
            <p>Your currently authenticated account.</p>
          </div>
        </div>

        <div className="settings-account-card">
          <div className="settings-avatar">
            <UserRound size={34} />
          </div>

          <div className="settings-account-details">
            <h3>{userName}</h3>
            <p>{userEmail}</p>

            <span className="settings-role">
              <ShieldCheck size={14} />
              {userRole}
            </span>
          </div>

          <div className="settings-account-status">
            <span></span>
            Authenticated
          </div>
        </div>
      </section>

      {/* SYSTEM PREFERENCES */}
      <section className="settings-section">
        <div className="settings-section-heading">
          <div className="settings-heading-icon">
            <Monitor size={22} />
          </div>

          <div>
            <h2>System Preferences</h2>
            <p>Customize your Mobility Aids experience.</p>
          </div>
        </div>

        <div className="settings-options">

          <article className="settings-option">
            <div className="settings-option-icon">
              <Bell size={21} />
            </div>

            <div className="settings-option-info">
              <strong>Notifications</strong>
              <p>Enable notification preferences for your account.</p>
            </div>

            <label className="settings-switch">
              <input
                type="checkbox"
                checked={preferences.notifications}
                onChange={() => handlePreference("notifications")}
              />
              <span className="settings-switch-slider"></span>
            </label>
          </article>

          <article className="settings-option">
            <div className="settings-option-icon">
              <Monitor size={21} />
            </div>

            <div className="settings-option-info">
              <strong>Compact View</strong>
              <p>Save your preference for a more compact interface.</p>
            </div>

            <label className="settings-switch">
              <input
                type="checkbox"
                checked={preferences.compactView}
                onChange={() => handlePreference("compactView")}
              />
              <span className="settings-switch-slider"></span>
            </label>
          </article>

        </div>

        <div className="settings-save-area">
          {saved && (
            <span className="settings-saved-message">
              <CheckCircle2 size={17} />
              Preferences saved successfully.
            </span>
          )}

          <button
            type="button"
            className="settings-save-button"
            onClick={savePreferences}
          >
            <Save size={17} />
            Save Preferences
          </button>
        </div>
      </section>

      {/* SECURITY */}
      <section className="settings-section">
        <div className="settings-section-heading">
          <div className="settings-heading-icon">
            <LockKeyhole size={22} />
          </div>

          <div>
            <h2>Security & Privacy</h2>
            <p>Information about your account protection.</p>
          </div>
        </div>

        <div className="settings-security-card">
          <div className="settings-security-icon">
            <ShieldCheck size={27} />
          </div>

          <div>
            <strong>Protected Account</strong>
            <p>
              Mobility Aids uses authenticated access and protected
              API requests to help secure administrative information.
            </p>
          </div>

          <span className="settings-security-label">
            Active session
          </span>
        </div>
      </section>

      {/* SYSTEM INFORMATION */}
      <section className="settings-information">
        <Info size={20} />

        <div>
          <strong>Mobility Aids Management System</strong>
          <p>
            Technical Aids Bank for Mobility · Account and system settings.
          </p>
        </div>

        <span>Version 1.0</span>
      </section>

      {/* FOOTER */}
      <footer className="settings-footer">
        <span>Mobility Aids · System Configuration</span>
        <span>Empowering Mobility, Changing Lives</span>
      </footer>

    </main>
  );
}
