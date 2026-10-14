document.addEventListener("DOMContentLoaded", function () {
    const scents = {
        bloom: ["Bloom", "Floral, sweet jasmine, fresh green notes"],
        wilt: ["Wilt", "Slight floral and bergamot"],
        dry: ["Dry", "Oud notes and slight sandal"],
        thorns: ["Thorns", "Stronger intense rich oud"],
        ashes: ["Ashes", "Sandalwood notes, dry cedar"],
        roots: ["Roots", "Earthy notes and fresh citrusy scents"],
        epiphany: ["Epiphany", "Chocolate notes, rich arabian notes"],
    };
    const params = new URLSearchParams(window.location.search);
    const type = params.get("type") || "book";
    const scentId = (params.get("scent") || "bloom").toLowerCase();
    const scent = scents[scentId] || scents.bloom;
    let title = "Diary of a Heartbreak Kid (DAHK)";
    let detail = "By Salamatu Adamu • Hardcover Anthology";
    let price = 20000;
    let productType = "book";
    let selected = "bloom";

    if (type === "single-vial") {
        title = "DAHK Vial: " + scent[0];
        detail = scent[1] + " • 20ml dropper format";
        price = 3000;
        productType = "single-vial";
        selected = Object.prototype.hasOwnProperty.call(scents, scentId) ? scentId : "bloom";
    } else if (type === "full-vials-set") {
        title = "DAHK Seasons Vials — Full Set";
        detail = "7 Curated oil blends (Bloom to Epiphany) with custom set box";
        price = 21000;
        productType = "full-vials-set";
    }

    const productTypeInput = document.getElementById("product-type");
    const scentInput = document.getElementById("selected-scent");
    const priceInput = document.getElementById("unit-price");
    const summaryTitle = document.getElementById("summary-title");
    const summaryDetail = document.getElementById("summary-detail");
    if (!productTypeInput || !priceInput || !summaryTitle) return;

    productTypeInput.value = productType;
    if (scentInput) scentInput.value = selected;
    priceInput.value = String(price);
    summaryTitle.textContent = title;
    if (summaryDetail) summaryDetail.textContent = detail;
    document.title = title + " · Kanti";
});
