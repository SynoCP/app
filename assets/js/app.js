/* ── URL sanitisation ────────────────────────────────────────────────────────
   Blocks dangerous URL schemes (javascript:, vbscript:) that could cause XSS
   when used in href or src attributes.  Relative URLs and http/https are
   always allowed.                                                            */

/** Default card background colour – matches the CSS var(--app-color) fallback. */
var DEFAULT_CARD_COLOR = "#1e293b";

/**
 * Returns '#' when the supplied value is not a relative URL and does not use
 * an allowed scheme (http or https).  This prevents javascript:, vbscript:,
 * data:, and other dangerous URL injections from localStorage data being
 * placed in anchor href attributes.
 *
 * @param {string} url
 * @returns {string}
 */
function sanitizeUrl(url) {
  if (typeof url !== "string") return "#";
  // Normalise whitespace first (handles obfuscated schemes like "java\tscript:").
  var trimmed = url.trim().replace(/\s/g, "");
  if (trimmed === "") return "#";
  // Allow relative URLs (same-origin). Block protocol-relative URLs (//host)
  // which can resolve to a different origin despite starting with "/".
  if (trimmed[0] === "/" && trimmed.slice(0, 2) !== "//") return url.trim();
  if (trimmed.indexOf("./") === 0) return url.trim();
  var normalized = trimmed.toLowerCase();
  if (normalized.indexOf("http://") === 0 || normalized.indexOf("https://") === 0) {
    return url.trim();
  }
  // Block javascript:, vbscript:, data:, and all other schemes.
  return "#";
}

/**
 * Returns an empty string when the URL is unsafe for use as an img src.
 * Only relative same-origin paths (starting with /) and absolute http/https
 * URLs are permitted.  data: URIs are intentionally blocked: the app now
 * uses a proper file-based fallback icon (/assets/img/app-icon-fallback.svg)
 * and allowing data: URIs would violate the Content-Security-Policy
 * img-src 'self' directive, causing browser CSP errors.
 *
 * @param {string} url
 * @returns {string}
 */
function sanitizeImgSrc(url) {
  if (typeof url !== "string") return "";
  var trimmed = url.trim().replace(/\s/g, "");
  if (trimmed === "") return "";
  if (trimmed[0] === "/" || trimmed.indexOf("./") === 0) return url.trim();
  var normalized = trimmed.toLowerCase();
  if (
    normalized.indexOf("http://") === 0 ||
    normalized.indexOf("https://") === 0
  ) {
    return url.trim();
  }
  return "";
}

/* ── CSS color sanitization ──────────────────────────────────────────────────
   Validates CSS color values read from localStorage before they are applied
   as CSS custom-property values.  Accepted formats mirror the PHP-side
   sanitize_color() function: hex shorthand/full and rgb()/rgba() notation.
   All other values (hsl(), url(), expressions, …) fall back to the default. */

/**
 * Returns DEFAULT_CARD_COLOR when the supplied value is not a recognised,
 * safe CSS color format.  This prevents CSS injection via manipulated
 * localStorage entries from influencing the page appearance beyond what
 * the server-side sanitization already enforces.
 *
 * @param {string} color
 * @returns {string}
 */
function sanitizeColor(color) {
  if (typeof color !== "string") return DEFAULT_CARD_COLOR;
  var c = color.trim();
  if (/^#[0-9a-fA-F]{3,8}$/.test(c)) {
    var len = c.slice(1).length;
    if (len === 3 || len === 4 || len === 6 || len === 8) {
      return c;
    }
  }
  if (/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/.test(c)) {
    return c;
  }
  return DEFAULT_CARD_COLOR;
}



/* ── Search ──────────────────────────────────────────────────────────────────
   Filter visible app cards in real time by matching the query against each
   card's inner text (name + description).                                    */

function initSearch() {
  document.getElementById("search").addEventListener("input", function () {
    const value = this.value.toLowerCase();

    document.querySelectorAll(".app").forEach(function (app) {
      const text = app.textContent.toLowerCase();
      app.style.display = text.includes(value) ? "block" : "none";
    });
  });
}

/* ── Theme toggle ────────────────────────────────────────────────────────────
   Toggles the "light" class on <body> and persists the preference to
   localStorage under the key "theme".                                        */

function toggleTheme() {
  document.body.classList.toggle("light");
  localStorage.setItem(
    "theme",
    document.body.classList.contains("light") ? "light" : "dark"
  );
}

function initTheme() {
  if (localStorage.getItem("theme") === "light") {
    document.body.classList.add("light");
  }
}

/* ── Recent Apps ─────────────────────────────────────────────────────────────
   Stores the last 6 visited apps in localStorage ("recentApps") and renders
   them as cards in the Recent section on page load.                          */

function storeRecent(name, url, icon, color) {
  let apps = JSON.parse(localStorage.getItem("recentApps") || "[]");

  apps = apps.filter(function (a) { return a.url !== url; });

  apps.unshift({ name: name, url: url, icon: icon, color: color });

  apps = apps.slice(0, 6);

  localStorage.setItem("recentApps", JSON.stringify(apps));
}

function renderRecent() {
  const container = document.getElementById("recent");
  const apps = JSON.parse(localStorage.getItem("recentApps") || "[]");

  container.innerHTML = "";

  apps.forEach(function (app) {
    const a = document.createElement("a");
    a.href = sanitizeUrl(app.url);
    a.className = "app";
    a.style.setProperty("--app-color", sanitizeColor(app.color));

    const img = document.createElement("img");
    img.className = "icon";
    img.src = sanitizeImgSrc(app.icon) || "/assets/img/app-icon-fallback.svg";
    img.alt = "";

    const h3 = document.createElement("h3");
    h3.textContent = app.name;

    a.appendChild(img);
    a.appendChild(h3);
    container.appendChild(a);
  });
}

/* ── Settings Modal ──────────────────────────────────────────────────────────
   Allows editing assets/config.json (site title, subtitle, nav links) via a
   modal dialog.  Changes are persisted by POST-ing to api/config.php.       */

/**
 * Opens the settings modal and pre-populates it with the current config
 * fetched from the API endpoint.
 */
function openSettingsModal() {
  const overlay = document.getElementById("settings-modal");
  if (!overlay) {
    return;
  }
  overlay.hidden = false;
  document.body.style.overflow = "hidden";

  hideFeedback();

  fetch("./api/config.php")
    .then(function (res) { return res.json(); })
    .then(function (cfg) {
      document.getElementById("cfg-title").value    = cfg.site_title    || "";
      document.getElementById("cfg-subtitle").value = cfg.site_subtitle || "";

      var list = document.getElementById("nav-items-list");
      list.innerHTML = "";
      (cfg.nav || []).forEach(function (item) {
        addNavItem(item.label || "", item.url || "");
      });
    })
    .catch(function () {
      showFeedback("error", "Konfiguration konnte nicht geladen werden.");
    });
}

/**
 * Closes the settings modal and restores body scroll.
 */
function closeSettingsModal() {
  var overlay = document.getElementById("settings-modal");
  if (!overlay) {
    return;
  }
  overlay.hidden = true;
  document.body.style.overflow = "";
}

/**
 * Appends a new nav-item row to the nav items list inside the modal.
 *
 * @param {string} label  Pre-filled label value (empty for a new blank row).
 * @param {string} url    Pre-filled URL value (empty for a new blank row).
 */
function addNavItem(label, url) {
  var list = document.getElementById("nav-items-list");
  if (!list) {
    return;
  }

  var row = document.createElement("div");
  row.className = "nav-item-row";

  var labelInput = document.createElement("input");
  labelInput.type        = "text";
  labelInput.className   = "form-input nav-item-label";
  labelInput.placeholder = "Label (z.B. START)";
  labelInput.value       = label;
  labelInput.setAttribute("aria-label", "Nav-Link Label");

  var urlInput = document.createElement("input");
  urlInput.type        = "text";
  urlInput.className   = "form-input nav-item-url";
  urlInput.placeholder = "URL (z.B. https://…)";
  urlInput.value       = url;
  urlInput.setAttribute("aria-label", "Nav-Link URL");

  var removeBtn = document.createElement("button");
  removeBtn.type      = "button";
  removeBtn.className = "btn-remove-nav";
  removeBtn.innerHTML = "✕";
  removeBtn.setAttribute("aria-label", "Nav-Link entfernen");
  removeBtn.addEventListener("click", function () {
    list.removeChild(row);
  });

  row.appendChild(labelInput);
  row.appendChild(urlInput);
  row.appendChild(removeBtn);
  list.appendChild(row);
}

/**
 * Collects form values, validates them, and POSTs the updated config to the
 * API endpoint.  Shows success or error feedback inside the modal.
 */
function saveSettings() {
  var saveBtn = document.querySelector(".btn-primary");
  if (saveBtn) {
    saveBtn.disabled = true;
  }

  hideFeedback();

  var navRows = document.querySelectorAll("#nav-items-list .nav-item-row");
  var nav = [];
  navRows.forEach(function (row) {
    var label = row.querySelector(".nav-item-label").value.trim();
    var url   = row.querySelector(".nav-item-url").value.trim();
    if (label) {
      nav.push({ label: label, url: url || "#" });
    }
  });

  var payload = {
    site_title:    document.getElementById("cfg-title").value.trim(),
    site_subtitle: document.getElementById("cfg-subtitle").value.trim(),
    nav:           nav,
  };

  fetch("./api/config.php", {
    method:  "POST",
    headers: { "Content-Type": "application/json" },
    body:    JSON.stringify(payload),
  })
    .then(function (res) {
      if (!res.ok) {
        return res.json().then(function (d) { throw new Error(d.error || "Unbekannter Fehler"); });
      }
      return res.json();
    })
    .then(function () {
      showFeedback("success", "Einstellungen gespeichert. Bitte Seite neu laden.");
    })
    .catch(function (err) {
      showFeedback("error", "Fehler beim Speichern: " + err.message);
    })
    .finally(function () {
      if (saveBtn) {
        saveBtn.disabled = false;
      }
    });
}

/** Shows a styled feedback message inside the modal. */
function showFeedback(type, message) {
  var el = document.getElementById("settings-feedback");
  if (!el) {
    return;
  }
  el.textContent  = message;
  el.className    = "settings-feedback " + type;
  el.hidden       = false;
}

/** Hides the feedback element inside the modal. */
function hideFeedback() {
  var el = document.getElementById("settings-feedback");
  if (el) {
    el.hidden = true;
  }
}

/* ── Card colours ─────────────────────────────────────────────────────────────
   Reads the data-color attribute from every server-rendered .app card and
   applies it as the --app-color CSS custom property so that the card
   background is set without any inline style= attributes in the HTML
   (which would violate a strict style-src CSP directive).               */

function applyCardColors() {
  document.querySelectorAll(".app[data-color]").forEach(function (card) {
    card.style.setProperty("--app-color", card.dataset.color);
  });
}

/* ── Bootstrap ───────────────────────────────────────────────────────────────
   Wire up all interactive behaviours once the DOM is ready.                  */

/* istanbul ignore next */
if (typeof document !== "undefined") {
  document.addEventListener("DOMContentLoaded", function () {
    initSearch();
    initTheme();
    renderRecent();
    applyCardColors();

    // Theme toggle button.
    var themeBtn = document.querySelector(".theme-toggle");
    if (themeBtn) {
      themeBtn.addEventListener("click", toggleTheme);
    }

    // Open settings modal button.
    var openSettingsBtn = document.getElementById("open-settings");
    if (openSettingsBtn) {
      openSettingsBtn.addEventListener("click", openSettingsModal);
    }

    // Close settings modal (X button and Cancel button).
    document.querySelectorAll(".modal-close, .btn-secondary").forEach(function (btn) {
      btn.addEventListener("click", closeSettingsModal);
    });

    // Add nav-item row button.
    var addNavBtn = document.querySelector(".btn-add-nav");
    if (addNavBtn) {
      addNavBtn.addEventListener("click", function () { addNavItem("", ""); });
    }

    // Save settings button.
    var saveBtn = document.querySelector(".btn-primary");
    if (saveBtn) {
      saveBtn.addEventListener("click", saveSettings);
    }

    // Store recent apps on card click (delegated handler replaces inline
    // onclick attributes, enabling a strict Content-Security-Policy).
    document.addEventListener("click", function (e) {
      var card = e.target.closest ? e.target.closest(".app[data-label]") : null;
      if (card) {
        storeRecent(
          card.dataset.label || "",
          card.dataset.url   || card.getAttribute("href") || "#",
          card.dataset.icon  || "",
          card.dataset.color || ""
        );
      }
    });

    // Close settings modal on backdrop click.
    var overlay = document.getElementById("settings-modal");
    if (overlay) {
      overlay.addEventListener("click", function (e) {
        if (e.target === overlay) {
          closeSettingsModal();
        }
      });
    }

    // Close settings modal on Escape key only when it is open.
    document.addEventListener("keydown", function (e) {
      var modal = document.getElementById("settings-modal");
      if (e.key === "Escape" && modal && !modal.hidden) {
        closeSettingsModal();
      }
    });
  });
}

/* ── CommonJS export (used by Jest) ─────────────────────────────────────────
   Guarded so the export doesn't execute in the browser.                      */

/* istanbul ignore next */
if (typeof module !== "undefined") {
  module.exports = {
    sanitizeUrl, sanitizeImgSrc, sanitizeColor,
    toggleTheme, storeRecent, renderRecent, initSearch, initTheme,
    applyCardColors,
    openSettingsModal, closeSettingsModal, addNavItem, saveSettings,
    showFeedback, hideFeedback,
  };
}
