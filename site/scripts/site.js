(function () {
  const root =
    typeof window !== "undefined" && typeof window.CAVEMEN_SITE_ROOT === "string"
      ? window.CAVEMEN_SITE_ROOT.replace(/\/$/, "")
      : "";
  window.cavemenSiteRoot = root;
  /**
   * Prefix for API paths when the site lives in a subdirectory (set window.CAVEMEN_SITE_ROOT).
   * @param {string} path e.g. "/api/dahk-registrations.php"
   */
  window.cavemenApiUrl = function (path) {
    const p = path.startsWith("/") ? path : "/" + path;
    return root + p;
  };
  /**
   * Calls unified /cavemen-api.php (avoids /api/* if your host blocks it).
   * @param {string} route e.g. "dahk-registrations"
   * @param {Record<string, string|number>|undefined} query extra query params
   */
  window.cavemenApiEndpoint = function (route, query) {
    const params = new URLSearchParams();
    params.set("route", route);
    if (query) {
      for (const [k, v] of Object.entries(query)) {
        if (v !== undefined && v !== null) {
          params.set(k, String(v));
        }
      }
    }
    return root + "/cavemen-api.php?" + params.toString();
  };
})();

const yearTargets = document.querySelectorAll("[data-current-year]");

for (const target of yearTargets) {
  target.textContent = new Date().getFullYear();
}

const currentPath = window.location.pathname.replace(/\/$/, "") || "/";
const navLinks = document.querySelectorAll("[data-nav-link]");

for (const link of navLinks) {
  const href = link.getAttribute("href");
  if (!href) continue;

  const normalized = href.replace(/\/$/, "") || "/";
  const isSectionMatch =
    normalized !== "/" && currentPath.startsWith(`${normalized}/`);

  if (normalized === currentPath || isSectionMatch) {
    link.classList.add("is-active");
    link.setAttribute("aria-current", "page");
  }
}
