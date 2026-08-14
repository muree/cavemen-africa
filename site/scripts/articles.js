(function () {
  const searchInput = document.querySelector("[data-articles-search]");
  const tabs = document.querySelectorAll("[data-articles-filter]");
  const cards = document.querySelectorAll("[data-article-card]");
  const empty = document.querySelector("[data-articles-empty]");

  if (!cards.length) return;

  let activeFilter = "all";

  function normalize(value) {
    return String(value || "")
      .toLowerCase()
      .trim();
  }

  function applyFilters() {
    const query = normalize(searchInput && searchInput.value);
    let visible = 0;

    for (const card of cards) {
      const category = normalize(card.getAttribute("data-category"));
      const haystack = normalize(card.getAttribute("data-search"));
      const matchesFilter = activeFilter === "all" || category === activeFilter;
      const matchesSearch = !query || haystack.includes(query);
      const show = matchesFilter && matchesSearch;

      card.hidden = !show;
      if (show) visible += 1;
    }

    if (empty) {
      empty.hidden = visible > 0;
    }
  }

  for (const tab of tabs) {
    tab.addEventListener("click", function () {
      const nextFilter = tab.getAttribute("data-articles-filter") || "all";
      activeFilter = nextFilter;

      for (const item of tabs) {
        const selected = item === tab;
        item.classList.toggle("is-active", selected);
        item.setAttribute("aria-selected", selected ? "true" : "false");
      }

      applyFilters();
    });
  }

  if (searchInput) {
    searchInput.addEventListener("input", applyFilters);
  }
})();
