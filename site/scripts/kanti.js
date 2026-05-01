const categories = [
  { id: "all", label: "All" },
  { id: "crafts", label: "Crafts" },
  { id: "merch", label: "Merch" },
  { id: "digital", label: "Digital" },
  { id: "art", label: "Art" },
  { id: "other", label: "Other" },
];

const params = new URLSearchParams(window.location.search);
const requestedCategory = params.get("category");
const activeCategory = categories.some((category) => category.id === requestedCategory)
  ? requestedCategory
  : "all";

const categoryRoot = document.querySelector("[data-kanti-categories]");
const productRoot = document.querySelector("[data-kanti-products]");

function createCategoryLink(category) {
  const link = document.createElement("a");
  link.className = "pill-link";
  link.href =
    category.id === "all"
      ? "/kanti/"
      : `/kanti/?category=${encodeURIComponent(category.id)}`;
  link.textContent = category.label;

  if (category.id === activeCategory) {
    link.classList.add("is-active");
    link.setAttribute("aria-current", "page");
  }

  return link;
}

function createProductCard(product) {
  const item = document.createElement("article");
  item.className = "product-card";

  item.innerHTML = `
    <div class="product-card__media">
      <img src="${product.image}" alt="${product.title}" loading="lazy">
    </div>
    <div class="product-card__body">
      <p class="card-subtle">${product.category}</p>
      <h2 class="card-title">${product.title}</h2>
      <p class="card-copy">${product.shortDescription}</p>
      <a class="button" href="${product.flutterwaveUrl}" target="_blank" rel="noopener noreferrer">Buy with Flutterwave</a>
    </div>
  `;

  return item;
}

function renderProducts(products) {
  if (!productRoot) return;

  const filtered =
    activeCategory === "all"
      ? products
      : products.filter((product) => product.category === activeCategory);

  productRoot.innerHTML = "";

  if (!filtered.length) {
    const empty = document.createElement("p");
    empty.className = "empty-state";
    empty.textContent =
      "Nothing in this category yet. Try another filter or add products to the JSON file.";
    productRoot.append(empty);
    return;
  }

  const grid = document.createElement("div");
  grid.className = "product-grid";

  for (const product of filtered) {
    grid.append(createProductCard(product));
  }

  productRoot.append(grid);
}

async function initKanti() {
  if (!categoryRoot || !productRoot) return;

  categoryRoot.innerHTML = "";
  for (const category of categories) {
    categoryRoot.append(createCategoryLink(category));
  }

  try {
    const endpoint =
      activeCategory === "all"
        ? window.cavemenApiEndpoint("products")
        : window.cavemenApiEndpoint("products", { category: activeCategory });
    const response = await fetch(endpoint);
    if (!response.ok) {
      throw new Error(`Failed to load products: ${response.status}`);
    }

    const payload = await response.json();
    renderProducts(payload.products || []);
  } catch (error) {
    productRoot.innerHTML = "";
    const failure = document.createElement("p");
    failure.className = "empty-state";
    failure.textContent = "The marketplace catalog could not be loaded right now.";
    productRoot.append(failure);
    console.error(error);
  }
}

void initKanti();
